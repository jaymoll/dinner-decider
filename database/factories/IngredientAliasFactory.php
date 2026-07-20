<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\IngredientAlias;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IngredientAlias>
 */
class IngredientAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'ingredient_id' => Ingredient::factory(),
            'name' => Str::headline($name),
            'normalized_name' => Str::lower($name),
        ];
    }
}
