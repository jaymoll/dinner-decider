<?php

namespace Database\Factories;

use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'ingredient_id' => Ingredient::factory(),
            'ingredient_package_id' => null,
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => '100',
            'entered_unit' => UnitCode::Gram,
            'normalized_amount' => '100',
            'compatibility_key' => 'mass',
            'quantity_description' => null,
            'non_exact_status' => null,
            'position' => 1,
        ];
    }

    public function nonExact(): static
    {
        return $this->state(fn (): array => [
            'quantity_type' => QuantityType::NonExact,
            'entered_amount' => null,
            'entered_unit' => null,
            'normalized_amount' => null,
            'compatibility_key' => null,
            'ingredient_package_id' => null,
            'quantity_description' => 'To taste',
            'non_exact_status' => NonExactStatus::Required,
        ]);
    }
}
