<?php

namespace Database\Factories;

use App\Enums\GroceryCategory;
use App\Enums\GroceryItemSource;
use App\Models\GroceryItem;
use App\Models\GroceryList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryItem>
 */
class GroceryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grocery_list_id' => GroceryList::factory(),
            'source' => GroceryItemSource::Manual,
            'name' => fake()->words(2, true),
            'quantity_description' => '1 item',
            'category' => GroceryCategory::Other,
        ];
    }

    public function generated(): static
    {
        return $this->state(fn (): array => [
            'source' => GroceryItemSource::Generated,
            'generation_key' => hash('sha256', fake()->uuid()),
            'calculated_amount' => '100',
            'calculated_unit' => 'g',
            'quantity_description' => null,
        ]);
    }
}
