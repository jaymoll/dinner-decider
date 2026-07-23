<?php

namespace App\Actions\Pantry;

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Exceptions\PantryEntryRemovalRequiresConfirmation;
use App\Models\DinnerPlan;
use App\Models\IngredientReservation;
use App\Models\PantryEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RemovePantryEntry
{
    public function __construct(private EnsureDinnerPlan $ensureDinnerPlan, private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PantryEntry $pantryEntry, bool $confirmed = false): void
    {
        Gate::forUser($user)->authorize('delete', $pantryEntry);
        $plan = $this->ensureDinnerPlan->handle($user);

        DB::transaction(function () use ($plan, $pantryEntry, $confirmed): void {
            $lockedPlan = DinnerPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $lockedEntry = PantryEntry::query()->with('reservations.requirement.plannedDinner')->lockForUpdate()->findOrFail($pantryEntry->id);

            // Surface the dinners that will lose reservations before making a destructive change.
            if ($lockedEntry->reservations->isNotEmpty() && ! $confirmed) {
                throw new PantryEntryRemovalRequiresConfirmation($lockedEntry->reservations->map(fn (IngredientReservation $reservation): array => [
                    'dinner' => $reservation->requirement->plannedDinner->recipe_name,
                    'date' => $reservation->requirement->plannedDinner->planned_date?->format('Y-m-d'),
                    'amount' => $reservation->normalized_amount,
                ])->values()->all());
            }

            $ingredientId = $lockedEntry->ingredient_id;
            $lockedEntry->delete();
            $this->reconcile->handle($lockedPlan, [$ingredientId]);
        }, attempts: 3);
    }
}
