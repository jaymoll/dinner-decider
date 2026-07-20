<?php

namespace App\Actions\Pantry;

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Models\DinnerPlan;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateIngredientPantryStatus
{
    public function __construct(private EnsureDinnerPlan $ensureDinnerPlan, private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, Ingredient $ingredient, bool $isStaple, bool $isCurrentlyAvailable): Ingredient
    {
        Gate::forUser($user)->authorize('update', $ingredient);
        $plan = $this->ensureDinnerPlan->handle($user);

        return DB::transaction(function () use ($plan, $ingredient, $isStaple, $isCurrentlyAvailable): Ingredient {
            $lockedPlan = DinnerPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $lockedIngredient = Ingredient::query()->where('user_id', $lockedPlan->user_id)->lockForUpdate()->findOrFail($ingredient->id);
            $lockedIngredient->update([
                'is_staple' => $isStaple,
                'is_currently_available' => $isCurrentlyAvailable,
            ]);
            $this->reconcile->handle($lockedPlan, [$lockedIngredient->id]);

            return $lockedIngredient->refresh();
        }, attempts: 3);
    }
}
