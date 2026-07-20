<?php

namespace Database\Factories;

use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\PlannedDinner;
use App\Models\PlannedDinnerRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannedDinnerRequirement> */
class PlannedDinnerRequirementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'planned_dinner_id' => PlannedDinner::factory(),
            'ingredient_id' => Ingredient::factory(),
            'ingredient_name' => fake()->word(),
            'quantity_type' => QuantityType::Exact,
            'source_entered_amount' => '100',
            'source_entered_unit' => UnitCode::Gram,
            'source_normalized_amount' => '100',
            'scaled_amount' => '100',
            'compatibility_key' => 'mass',
            'coverage' => RequirementCoverage::Missing,
            'missing_amount' => '100',
            'position' => 1,
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (): array => ['coverage' => RequirementCoverage::Partial, 'missing_amount' => '50']);
    }

    public function incompatible(): static
    {
        return $this->state(fn (): array => ['coverage' => RequirementCoverage::Incompatible]);
    }

    public function unresolved(): static
    {
        return $this->state(fn (): array => ['unresolved_at_cooking' => ['coverage' => 'missing', 'missing_amount' => '100']]);
    }

    public function nonExact(): static
    {
        return $this->state(fn (): array => [
            'quantity_type' => QuantityType::NonExact,
            'source_entered_amount' => null,
            'source_entered_unit' => null,
            'source_normalized_amount' => null,
            'scaled_amount' => null,
            'compatibility_key' => null,
            'quantity_description' => 'To taste',
            'non_exact_status' => NonExactStatus::Required,
            'coverage' => RequirementCoverage::NonExact,
            'missing_amount' => null,
        ]);
    }
}
