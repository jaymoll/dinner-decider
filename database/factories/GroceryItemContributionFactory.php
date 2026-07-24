<?php

namespace Database\Factories;

use App\Models\GroceryItem;
use App\Models\GroceryItemContribution;
use App\Models\PlannedDinnerRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryItemContribution>
 */
class GroceryItemContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grocery_item_id' => GroceryItem::factory()->generated(),
            'planned_dinner_requirement_id' => PlannedDinnerRequirement::factory(),
            'normalized_amount' => '100',
        ];
    }
}
