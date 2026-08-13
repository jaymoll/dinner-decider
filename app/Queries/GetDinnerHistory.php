<?php

namespace App\Queries;

use App\Enums\PlannedDinnerStatus;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GetDinnerHistory
{
    /**
     * @return LengthAwarePaginator<int, PlannedDinner>
     */
    public function get(
        User $user,
        ?int $recipeId = null,
        ?PlannedDinnerStatus $status = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        return PlannedDinner::query()
            ->whereHas('dinnerPlan', fn ($query) => $query->whereBelongsTo($user))
            ->history()
            ->when($recipeId !== null, fn ($query) => $query
                ->where('recipe_id', $recipeId)
                ->whereIn('recipe_id', Recipe::query()->whereBelongsTo($user)->select('id')))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($this->boundary($fromDate, false), fn ($query, CarbonImmutable $from) => $query->where(function ($query) use ($from): void {
                $query->where(fn ($query) => $query->where('status', PlannedDinnerStatus::Cooked)->where('cooked_at', '>=', $from))
                    ->orWhere(fn ($query) => $query->where('status', PlannedDinnerStatus::Cancelled)->where('cancelled_at', '>=', $from));
            }))
            ->when($this->boundary($toDate, true), fn ($query, CarbonImmutable $to) => $query->where(function ($query) use ($to): void {
                $query->where(fn ($query) => $query->where('status', PlannedDinnerStatus::Cooked)->where('cooked_at', '<=', $to))
                    ->orWhere(fn ($query) => $query->where('status', PlannedDinnerStatus::Cancelled)->where('cancelled_at', '<=', $to));
            }))
            ->with(['statusEvents.actor:id,name'])
            ->orderByRaw('COALESCE(cooked_at, cancelled_at, updated_at) DESC')
            ->latest('id')
            ->paginate($perPage, ['*'], 'history-page', $page);
    }

    private function boundary(?string $date, bool $endOfDay): ?CarbonImmutable
    {
        if ($date === null || ! CarbonImmutable::hasFormat($date, 'Y-m-d')) {
            return null;
        }

        $boundary = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Europe/Amsterdam');

        return ($endOfDay ? $boundary->endOfDay() : $boundary->startOfDay())->utc();
    }
}
