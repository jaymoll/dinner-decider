<?php

namespace App\Actions\Pantry;

use App\Data\Measurements\QuantityInput;
use App\Models\PantryEntry;
use App\Models\User;
use App\Services\Measurements\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class UpdatePantryEntry
{
    public function __construct(private UnitConverter $converter) {}

    public function handle(User $user, PantryEntry $pantryEntry, string $amount): PantryEntry
    {
        Gate::forUser($user)->authorize('update', $pantryEntry);

        return DB::transaction(function () use ($pantryEntry, $amount): PantryEntry {
            $lockedEntry = PantryEntry::query()->lockForUpdate()->findOrFail($pantryEntry->id);
            $lockedEntry->loadMissing(['ingredient', 'ingredientPackage']);
            $package = $lockedEntry->ingredientPackage;
            $quantity = $this->converter->normalize(new QuantityInput(
                amount: $amount,
                unit: $package === null ? $lockedEntry->display_unit : null,
                ingredientId: $lockedEntry->ingredient_id,
                ingredientPackageId: $package?->id,
                packageContentAmount: $package?->content_amount,
                packageContentUnit: $package?->content_unit,
            ));

            $reservedAmount = $this->reservedAmount($lockedEntry);
            if (bccomp($quantity->normalizedAmount, $reservedAmount, $this->scale()) < 0) {
                throw new InvalidArgumentException('The pantry total cannot be lower than its reserved amount.');
            }

            $lockedEntry->update(['total_normalized_amount' => $quantity->normalizedAmount]);

            return $lockedEntry->refresh()->load(['ingredient', 'ingredientPackage']);
        }, attempts: 3);
    }

    /** @return numeric-string */
    private function reservedAmount(PantryEntry $pantryEntry): string
    {
        return '0';
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
