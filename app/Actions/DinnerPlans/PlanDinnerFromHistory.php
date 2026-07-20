<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\PlannedDinner;
use App\Models\User;
use InvalidArgumentException;

final readonly class PlanDinnerFromHistory
{
    public function __construct(private DuplicatePlannedDinner $duplicate) {}

    /** @param numeric-string|null $servings */
    public function handle(User $user, PlannedDinner $source, ?string $servings = null, ?string $plannedDate = null): PlannedDinner
    {
        if ($source->status === PlannedDinnerStatus::Planned) {
            throw new InvalidArgumentException('Only dinner history can be planned again.');
        }

        return $this->duplicate->handle($user, $source, $servings, $plannedDate);
    }
}
