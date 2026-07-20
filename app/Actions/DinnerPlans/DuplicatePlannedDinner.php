<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use App\Services\DinnerPlans\RequirementSnapshotter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class DuplicatePlannedDinner
{
    public function __construct(private RequirementSnapshotter $snapshotter, private ReconcilePlanReservations $reconcile) {}

    /** @param numeric-string|null $servings */
    public function handle(User $user, PlannedDinner $source, ?string $servings = null, ?string $plannedDate = null): PlannedDinner
    {
        Gate::forUser($user)->authorize('view', $source);
        $servings ??= $source->servings;
        $this->assertPositive($servings);

        return DB::transaction(function () use ($source, $servings, $plannedDate): PlannedDinner {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($source->dinner_plan_id);
            $lockedSource = PlannedDinner::query()->whereBelongsTo($plan)->lockForUpdate()->findOrFail($source->id);
            $requirements = $lockedSource->requirements()->lockForUpdate()->get();
            $dinner = $lockedSource->replicate([
                'status', 'position', 'planned_date', 'cooked_at', 'cancelled_at', 'restored_at', 'created_at', 'updated_at',
            ]);
            $dinner->status = PlannedDinnerStatus::Planned;
            $dinner->servings = $servings;
            $dinner->planned_date = $this->date($plannedDate);
            $dinner->position = ((int) PlannedDinner::query()->whereBelongsTo($plan)->active()->max('position')) + 1;
            $dinner->save();

            foreach ($requirements as $requirement) {
                $copy = $requirement->replicate(['coverage', 'missing_amount', 'unresolved_at_cooking', 'created_at', 'updated_at']);
                $scaledAmount = $this->snapshotter->scaledAmount($requirement, $servings, $lockedSource->source_servings);
                $copy->forceFill([
                    'planned_dinner_id' => $dinner->id,
                    'scaled_amount' => $scaledAmount,
                    'coverage' => $requirement->quantity_type->value === 'exact' ? 'missing' : 'non_exact',
                    'missing_amount' => $scaledAmount,
                ])->save();
            }

            $this->reconcile->handle($plan);

            return $dinner->refresh()->load(['requirements.reservations']);
        }, attempts: 3);
    }

    /** @param numeric-string $value */
    private function assertPositive(string $value): void
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value) || bccomp($value, '0', 6) <= 0) {
            throw new InvalidArgumentException('Servings must be a positive decimal value.');
        }
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
