<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class ReorderPlannedDinner
{
    public function __construct(private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PlannedDinner $dinner, int $position): PlannedDinner
    {
        Gate::forUser($user)->authorize('update', $dinner);

        return DB::transaction(function () use ($dinner, $position): PlannedDinner {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $dinners = PlannedDinner::query()->whereBelongsTo($plan)->active()->orderBy('position')->oldest('id')->lockForUpdate()->get();
            $lockedDinner = $dinners->firstWhere('id', $dinner->id);
            if ($lockedDinner === null || $lockedDinner->status !== PlannedDinnerStatus::Planned) {
                throw new InvalidArgumentException('Only a planned dinner can be reordered.');
            }

            $target = max(1, min($position, $dinners->count()));
            $ordered = $dinners->reject(fn (PlannedDinner $item): bool => $item->is($lockedDinner))->values();
            $ordered->splice($target - 1, 0, [$lockedDinner]);
            foreach ($ordered as $index => $item) {
                if ($item->position !== $index + 1) {
                    $item->update(['position' => $index + 1]);
                }
            }

            // Dinner position is part of allocation priority, so ordering changes can move scarce
            // stock between requirements even when total supply and demand are unchanged.
            $this->reconcile->handle($plan);

            return $lockedDinner->refresh();
        }, attempts: 3);
    }
}
