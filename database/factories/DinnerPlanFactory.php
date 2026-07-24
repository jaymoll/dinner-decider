<?php

namespace Database\Factories;

use App\Models\DinnerPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DinnerPlan> */
class DinnerPlanFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory()];
    }
}
