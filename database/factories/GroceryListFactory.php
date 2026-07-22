<?php

namespace Database\Factories;

use App\Models\DinnerPlan;
use App\Models\GroceryList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryList>
 */
class GroceryListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dinner_plan_id' => DinnerPlan::factory(),
        ];
    }
}
