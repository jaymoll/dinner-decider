<?php

namespace Database\Factories;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
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
            'user_id' => User::factory(),
            'name' => Str::headline($name),
            'normalized_name' => Str::lower($name),
            'category' => fake()->randomElement(['Dry goods', 'Vegetables', 'Dairy', null]),
            'preferred_measurement_group' => MeasurementGroup::Mass,
            'preferred_unit' => UnitCode::Gram,
            'is_staple' => false,
            'is_currently_available' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }

    public function staple(): static
    {
        return $this->state(fn (): array => ['is_staple' => true]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => ['is_currently_available' => false]);
    }
}
