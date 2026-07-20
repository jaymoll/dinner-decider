<?php

namespace Database\Factories;

use App\Models\IngredientReservation;
use App\Models\PantryEntry;
use App\Models\PlannedDinnerRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IngredientReservation> */
class IngredientReservationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'planned_dinner_requirement_id' => PlannedDinnerRequirement::factory(),
            'pantry_entry_id' => PantryEntry::factory(),
            'normalized_amount' => '50',
        ];
    }
}
