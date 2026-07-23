<?php

namespace App\Actions\Pantry;

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Data\Measurements\QuantityInput;
use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\DinnerPlan;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\User;
use App\Services\Measurements\UnitConverter;
use App\ValueObjects\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Normalizes a stock addition, merges it into the matching row, and reallocates affected demand.
 */
final readonly class AddPantryStock
{
    public function __construct(
        private UnitConverter $converter,
        private EnsureDinnerPlan $ensureDinnerPlan,
        private ReconcilePlanReservations $reconcile,
    ) {}

    /** @param array{ingredient_id: int, amount: string, unit?: string|null, ingredient_package_id?: int|null} $data */
    public function handle(User $user, array $data): PantryEntry
    {
        Gate::forUser($user)->authorize('create', PantryEntry::class);
        $ingredient = Ingredient::query()->whereBelongsTo($user)->active()->findOrFail($data['ingredient_id']);
        $quantity = $this->quantity($ingredient, $data);
        $mergeKey = PantryEntry::mergeKeyFor($quantity);
        $plan = $this->ensureDinnerPlan->handle($user);

        return DB::transaction(function () use ($plan, $user, $ingredient, $quantity, $mergeKey): PantryEntry {
            $lockedPlan = DinnerPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $entry = PantryEntry::query()
                ->whereBelongsTo($user)
                ->where('ingredient_id', $ingredient->id)
                ->where('merge_key', $mergeKey)
                ->lockForUpdate()
                ->first();

            // The merge key keeps direct compatible units together while retaining package rows as
            // distinct display contexts, even when known contents normalize to the same metric key.
            if ($entry === null) {
                $entry = PantryEntry::query()->create([
                    'user_id' => $user->id,
                    'ingredient_id' => $ingredient->id,
                    'ingredient_package_id' => $quantity->ingredientPackageId,
                    'display_unit' => $quantity->ingredientPackageId === null ? $quantity->unit : null,
                    'total_normalized_amount' => $quantity->normalizedAmount,
                    'compatibility_key' => (string) $quantity->compatibilityKey,
                    'merge_key' => $mergeKey,
                ]);
            } else {
                $entry->update([
                    'total_normalized_amount' => bcadd($entry->total_normalized_amount, $quantity->normalizedAmount, $this->scale()),
                    'display_unit' => $entry->ingredient_package_id === null ? $quantity->unit : null,
                ]);
            }

            $this->reconcile->handle($lockedPlan, [$ingredient->id]);

            return $entry->refresh()->load(['ingredient', 'ingredientPackage']);
        }, attempts: 3);
    }

    /** @param array{ingredient_id: int, amount: string, unit?: string|null, ingredient_package_id?: int|null} $data */
    private function quantity(Ingredient $ingredient, array $data): Quantity
    {
        $packageId = filled($data['ingredient_package_id'] ?? null) ? (int) $data['ingredient_package_id'] : null;
        $unitValue = filled($data['unit'] ?? null) ? (string) $data['unit'] : null;

        // Exactly one representation is required; accepting both would make entered meaning ambiguous.
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
        // Semantic count units are not interchangeable even within the same measurement group.
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
