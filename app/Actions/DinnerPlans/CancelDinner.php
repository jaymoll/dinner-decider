<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Enums\PlannedDinnerStatusEventType;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class CancelDinner
{
    public function __construct(private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PlannedDinner $dinner): PlannedDinner
    {
        Gate::forUser($user)->authorize('update', $dinner);

        return DB::transaction(function () use ($user, $dinner): PlannedDinner {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $lockedDinner = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($dinner->id);
            if ($lockedDinner->status === PlannedDinnerStatus::Cancelled) {
                return $lockedDinner;
            }
            if ($lockedDinner->status === PlannedDinnerStatus::Cooked) {
                throw new InvalidArgumentException('A cooked dinner is terminal.');
            }

            $occurredAt = now();
            $lockedDinner->update(['status' => PlannedDinnerStatus::Cancelled, 'cancelled_at' => $occurredAt]);
            $lockedDinner->statusEvents()->create([
                'event_type' => PlannedDinnerStatusEventType::Cancelled,
                'from_status' => PlannedDinnerStatus::Planned,
                'to_status' => PlannedDinnerStatus::Cancelled,
                'occurred_at' => $occurredAt,
                'actor_user_id' => $user->id,
            ]);
            $this->reconcile->handle($plan);

            return $lockedDinner->refresh();
        }, attempts: 3);
    }
}
