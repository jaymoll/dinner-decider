<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PantryEntry>
 */
class PantryEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ingredient_id' => Ingredient::factory(),
            'ingredient_package_id' => null,
            'display_unit' => UnitCode::Gram,
            'total_normalized_amount' => '500',
            'compatibility_key' => 'mass',
            'merge_key' => 'direct:mass',
        ];
    }
}
