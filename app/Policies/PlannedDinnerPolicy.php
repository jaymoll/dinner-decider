<?php

namespace App\Policies;

use App\Models\PlannedDinner;
use App\Models\User;

class PlannedDinnerPolicy
{
    public function view(User $user, PlannedDinner $plannedDinner): bool
    {
        return $plannedDinner->dinnerPlan()->whereBelongsTo($user)->exists();
    }

    public function update(User $user, PlannedDinner $plannedDinner): bool
    {
        return $this->view($user, $plannedDinner);
    }

    public function delete(User $user, PlannedDinner $plannedDinner): bool
    {
        return $this->view($user, $plannedDinner);
    }
}
