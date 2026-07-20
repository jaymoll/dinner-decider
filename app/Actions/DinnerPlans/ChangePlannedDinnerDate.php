<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class ChangePlannedDinnerDate
{
    public function __construct(private ReconcilePlanReservations $reconcile) {}

    public function handle(User $user, PlannedDinner $dinner, ?string $plannedDate): PlannedDinner
    {
        Gate::forUser($user)->authorize('update', $dinner);
        $date = $this->date($plannedDate);

        return DB::transaction(function () use ($dinner, $date): PlannedDinner {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinner->dinner_plan_id);
            $lockedDinner = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($dinner->id);
            if ($lockedDinner->status !== PlannedDinnerStatus::Planned) {
                throw new InvalidArgumentException('Only a planned dinner can be changed.');
            }

            $lockedDinner->update(['planned_date' => $date]);
            $this->reconcile->handle($plan);
            $this->reconcile->handle($plan);

            return $lockedDinner->refresh();
        }, attempts: 3);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! filled($value)) {
            return null;
        }

        if (! CarbonImmutable::hasFormat((string) $value, 'Y-m-d')) {
            throw new InvalidArgumentException('The planned date must use ISO YYYY-MM-DD format.');
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', (string) $value);
    }
}
