<?php

namespace Database\Factories;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannedDinner> */
class PlannedDinnerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dinner_plan_id' => DinnerPlan::factory(),
            'recipe_id' => null,
            'recipe_name' => fake()->words(3, true),
            'recipe_description' => fake()->optional()->sentence(),
            'source_servings' => '4',
            'servings' => '4',
            'recipe_steps' => [],
            'recipe_categories' => [],
            'recipe_tags' => [],
            'position' => 1,
            'status' => PlannedDinnerStatus::Planned,
        ];
    }

    public function cooked(): static
    {
        return $this->state(fn (): array => ['status' => PlannedDinnerStatus::Cooked, 'cooked_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => PlannedDinnerStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
