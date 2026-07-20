<?php

namespace App\Policies;

use App\Models\DinnerPlan;
use App\Models\User;

class DinnerPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DinnerPlan $dinnerPlan): bool
    {
        return $user->id === $dinnerPlan->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DinnerPlan $dinnerPlan): bool
    {
        return $this->view($user, $dinnerPlan);
    }
}
