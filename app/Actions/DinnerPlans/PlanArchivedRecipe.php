<?php

namespace App\Actions\DinnerPlans;

use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\User;

final readonly class PlanArchivedRecipe
{
    public function __construct(private PlanDinner $planDinner) {}

    /** @param numeric-string $servings */
    public function handle(User $user, Recipe $recipe, string $servings, ?string $plannedDate = null): PlannedDinner
    {
        return $this->planDinner->handleArchived($user, $recipe, $servings, $plannedDate);
    }
}
