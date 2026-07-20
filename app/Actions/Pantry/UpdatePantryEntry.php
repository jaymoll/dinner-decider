<?php

namespace App\Actions\Pantry;

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Data\Measurements\QuantityInput;
use App\Models\DinnerPlan;
use App\Models\PantryEntry;
use App\Models\User;
use App\Services\Measurements\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdatePantryEntry
{
    public function __construct(
        private UnitConverter $converter,
        private EnsureDinnerPlan $ensureDinnerPlan,
        private ReconcilePlanReservations $reconcile,
    ) {}

    public function handle(User $user, PantryEntry $pantryEntry, string $amount): PantryEntry
    {
        Gate::forUser($user)->authorize('update', $pantryEntry);
        $plan = $this->ensureDinnerPlan->handle($user);

        return DB::transaction(function () use ($plan, $pantryEntry, $amount): PantryEntry {
            $lockedPlan = DinnerPlan::query()->lockForUpdate()->findOrFail($plan->id);
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

            $lockedEntry->update(['total_normalized_amount' => $quantity->normalizedAmount]);
            $this->reconcile->handle($lockedPlan, [$lockedEntry->ingredient_id]);

            return $lockedEntry->refresh()->load(['ingredient', 'ingredientPackage']);
        }, attempts: 3);
    }
}
