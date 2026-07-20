<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use App\Services\DinnerPlans\RequirementSnapshotter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class ChangePlannedDinnerServings
{
    public function __construct(private RequirementSnapshotter $snapshotter, private ReconcilePlanReservations $reconcile) {}

    /** @param numeric-string $servings */
    public function handle(User $user, PlannedDinner $dinner, string $servings): PlannedDinner
    {
        Gate::forUser($user)->authorize('update', $dinner);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $servings) || bccomp($servings, '0', 6) <= 0) {
            throw new InvalidArgumentException('Servings must be a positive decimal value.');
        }

        return DB::transaction(function () use ($dinner, $servings): PlannedDinner {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $lockedDinner = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($dinner->id);
            $this->assertPlanned($lockedDinner);

            foreach ($lockedDinner->requirements()->lockForUpdate()->get() as $requirement) {
                $scaled = $this->snapshotter->scaledAmount($requirement, $servings, $lockedDinner->source_servings);
                $requirement->update(['scaled_amount' => $scaled, 'missing_amount' => $scaled]);
            }

            $lockedDinner->update(['servings' => $servings]);
            $this->reconcile->handle($plan);

            return $lockedDinner->refresh()->load(['requirements.reservations']);
        }, attempts: 3);
    }

    private function assertPlanned(PlannedDinner $dinner): void
    {
        if ($dinner->status !== PlannedDinnerStatus::Planned) {
            throw new InvalidArgumentException('Only a planned dinner can be changed.');
        }
    }
}
