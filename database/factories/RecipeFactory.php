<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
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
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'default_servings' => 4,
            'preparation_minutes' => fake()->optional()->numberBetween(5, 30),
            'cooking_minutes' => fake()->optional()->numberBetween(10, 90),
            'difficulty' => fake()->optional()->randomElement(['Easy', 'Medium', 'Hard']),
            'cuisine' => fake()->optional()->country(),
            'meal_type' => fake()->optional()->randomElement(['Dinner', 'Lunch']),
            'notes' => fake()->optional()->sentence(),
            'image_path' => null,
            'source_url' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
