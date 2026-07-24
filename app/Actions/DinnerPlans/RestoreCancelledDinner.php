<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class RestoreCancelledDinner
{
    public function __construct(private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PlannedDinner $dinner): PlannedDinner
    {
        Gate::forUser($user)->authorize('update', $dinner);

        return DB::transaction(function () use ($dinner): PlannedDinner {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $lockedDinner = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($dinner->id);
            if ($lockedDinner->status === PlannedDinnerStatus::Planned) {
                return $lockedDinner;
            }
            if ($lockedDinner->status === PlannedDinnerStatus::Cooked) {
                throw new InvalidArgumentException('A cooked dinner is terminal.');
            }

            $lockedDinner->update([
                'status' => PlannedDinnerStatus::Planned,
                'cancelled_at' => null,
                'restored_at' => now(),
                'position' => ((int) PlannedDinner::query()->whereBelongsTo($plan)->active()->max('position')) + 1,
            ]);
            $this->reconcile->handle($plan);

            return $lockedDinner->refresh()->load(['requirements.reservations']);
        }, attempts: 3);
    }
}
