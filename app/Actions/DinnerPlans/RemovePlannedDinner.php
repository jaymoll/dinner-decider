<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class RemovePlannedDinner
{
    public function __construct(private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PlannedDinner $dinner): void
    {
        Gate::forUser($user)->authorize('delete', $dinner);

        DB::transaction(function () use ($dinner): void {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $lockedDinner = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($dinner->id);
            if ($lockedDinner->status !== PlannedDinnerStatus::Planned) {
                throw new InvalidArgumentException('Only an unprocessed planned dinner can be removed.');
            }

            $lockedDinner->delete();
            $remaining = PlannedDinner::query()->whereBelongsTo($plan)->active()->orderBy('position')->oldest('id')->lockForUpdate()->get();
            foreach ($remaining as $index => $item) {
                $item->update(['position' => $index + 1]);
            }
            $this->reconcile->handle($plan);
        }, attempts: 3);
    }
}
