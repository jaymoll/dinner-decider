<?php

namespace App\Actions\Pantry;

use App\Data\Measurements\QuantityInput;
use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\User;
use App\Services\Measurements\UnitConverter;
use App\ValueObjects\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class AddPantryStock
{
    public function __construct(private UnitConverter $converter) {}

    /** @param array{ingredient_id: int, amount: string, unit?: string|null, ingredient_package_id?: int|null} $data */
    public function handle(User $user, array $data): PantryEntry
    {
        Gate::forUser($user)->authorize('create', PantryEntry::class);
        $ingredient = Ingredient::query()->whereBelongsTo($user)->active()->findOrFail($data['ingredient_id']);
        $quantity = $this->quantity($ingredient, $data);
        $mergeKey = PantryEntry::mergeKeyFor($quantity);

        return DB::transaction(function () use ($user, $ingredient, $quantity, $mergeKey): PantryEntry {
            $entry = PantryEntry::query()
                ->whereBelongsTo($user)
                ->where('ingredient_id', $ingredient->id)
                ->where('merge_key', $mergeKey)
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                return PantryEntry::query()->create([
                    'user_id' => $user->id,
                    'ingredient_id' => $ingredient->id,
                    'ingredient_package_id' => $quantity->ingredientPackageId,
                    'display_unit' => $quantity->ingredientPackageId === null ? $quantity->unit : null,
                    'total_normalized_amount' => $quantity->normalizedAmount,
                    'compatibility_key' => (string) $quantity->compatibilityKey,
                    'merge_key' => $mergeKey,
                ])->load(['ingredient', 'ingredientPackage']);
            }

            $entry->update([
                'total_normalized_amount' => bcadd($entry->total_normalized_amount, $quantity->normalizedAmount, $this->scale()),
                'display_unit' => $entry->ingredient_package_id === null ? $quantity->unit : null,
            ]);

            return $entry->refresh()->load(['ingredient', 'ingredientPackage']);
        }, attempts: 3);
    }

    /** @param array{ingredient_id: int, amount: string, unit?: string|null, ingredient_package_id?: int|null} $data */
    private function quantity(Ingredient $ingredient, array $data): Quantity
    {
        $packageId = filled($data['ingredient_package_id'] ?? null) ? (int) $data['ingredient_package_id'] : null;
        $unitValue = filled($data['unit'] ?? null) ? (string) $data['unit'] : null;

        if (($packageId === null) === ($unitValue === null)) {
            throw new InvalidArgumentException('Choose either a direct unit or a package definition.');
        }

        if ($packageId !== null) {
            $package = IngredientPackage::query()->whereBelongsTo($ingredient)->findOrFail($packageId);

            return $this->converter->normalize(new QuantityInput(
                amount: $data['amount'],
                ingredientId: $ingredient->id,
                ingredientPackageId: $package->id,
                packageContentAmount: $package->content_amount,
                packageContentUnit: $package->content_unit,
            ));
        }

        $unit = UnitCode::from((string) $unitValue);
        if ($unit->measurementGroup() !== $ingredient->preferred_measurement_group
            || ($unit->measurementGroup() === MeasurementGroup::Count && $unit !== $ingredient->preferred_unit)) {
            throw new InvalidArgumentException('The selected unit is incompatible with the ingredient.');
        }

        return $this->converter->normalize(new QuantityInput($data['amount'], $unit, $ingredient->id));
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
