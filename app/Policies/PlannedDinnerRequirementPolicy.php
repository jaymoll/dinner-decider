<?php

namespace App\Policies;

use App\Models\PlannedDinnerRequirement;
use App\Models\User;

class PlannedDinnerRequirementPolicy
{
    public function view(User $user, PlannedDinnerRequirement $requirement): bool
    {
        return $requirement->plannedDinner()->whereHas('dinnerPlan', fn ($query) => $query->whereBelongsTo($user))->exists();
    }
}
