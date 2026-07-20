<?php

namespace App\Actions\DinnerPlans;

use App\Data\DinnerPlans\CookResult;
use App\Enums\PlannedDinnerStatus;
use App\Enums\RequirementCoverage;
use App\Models\DinnerPlan;
use App\Models\IngredientReservation;
use App\Models\PantryEntry;
use App\Models\PlannedDinner;
use App\Models\PlannedDinnerRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class MarkDinnerCooked
{
    public function __construct(private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PlannedDinner $dinner, ?string $confirmationFingerprint = null): CookResult
    {
        Gate::forUser($user)->authorize('update', $dinner);

        return DB::transaction(function () use ($dinner, $confirmationFingerprint): CookResult {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $lockedDinner = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($dinner->id);
            if ($lockedDinner->status === PlannedDinnerStatus::Cooked) {
                return new CookResult(cooked: true, alreadyCooked: true);
            }
            if ($lockedDinner->status === PlannedDinnerStatus::Cancelled) {
                throw new InvalidArgumentException('Restore a cancelled dinner before cooking it.');
            }

            $this->reconcile->handle($plan);
            $requirements = PlannedDinnerRequirement::query()->whereBelongsTo($lockedDinner)
                ->with('reservations')->orderBy('position')->lockForUpdate()->get();
            $unresolved = $requirements
                ->filter(fn (PlannedDinnerRequirement $requirement): bool => in_array($requirement->coverage, [
                    RequirementCoverage::Partial,
                    RequirementCoverage::Missing,
                    RequirementCoverage::Incompatible,
                    RequirementCoverage::Unavailable,
                ], true))
                ->map(fn (PlannedDinnerRequirement $requirement): array => [
                    'requirement_id' => $requirement->id,
                    'ingredient' => $requirement->ingredient_name,
                    'coverage' => $requirement->coverage->value,
                    'missing_amount' => $requirement->missing_amount,
                    'description' => $requirement->quantity_description,
                ])->values()->all();
            $fingerprint = hash('sha256', (string) json_encode($unresolved, JSON_THROW_ON_ERROR));

            if ($unresolved !== [] && ! hash_equals($fingerprint, (string) $confirmationFingerprint)) {
                return new CookResult(
                    cooked: false,
                    requiresConfirmation: true,
                    fingerprint: $fingerprint,
                    unresolved: $unresolved,
                );
            }

            $reservations = IngredientReservation::query()
                ->whereIn('planned_dinner_requirement_id', $requirements->modelKeys())
                ->oldest('pantry_entry_id')->oldest('id')->lockForUpdate()->get();
            $entries = PantryEntry::query()->whereIn('id', $reservations->pluck('pantry_entry_id')->unique()->sort()->values())
                ->oldest('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($reservations->groupBy('pantry_entry_id') as $entryId => $entryReservations) {
                $entry = $entries->get($entryId);
                $consumed = $entryReservations->reduce(
                    fn (string $sum, IngredientReservation $reservation): string => bcadd($sum, $reservation->normalized_amount, $this->scale()),
                    '0',
                );
                if ($entry === null || bccomp($entry->total_normalized_amount, $consumed, $this->scale()) < 0) {
                    throw new InvalidArgumentException('Reserved pantry stock is no longer available.');
                }
                $entry->update(['total_normalized_amount' => bcsub($entry->total_normalized_amount, $consumed, $this->scale())]);
            }

            foreach ($requirements as $requirement) {
                $details = collect($unresolved)->firstWhere('requirement_id', $requirement->id);
                $requirement->update(['unresolved_at_cooking' => $details]);
            }
            $reservations->each->delete();
            $lockedDinner->update(['status' => PlannedDinnerStatus::Cooked, 'cooked_at' => now()]);
            $this->reconcile->handle($plan);

            return new CookResult(cooked: true, unresolved: $unresolved);
        }, attempts: 3);
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
