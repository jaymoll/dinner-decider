<?php

namespace Database\Factories;

use App\Enums\PlannedDinnerStatus;
use App\Enums\PlannedDinnerStatusEventType;
use App\Models\PlannedDinner;
use App\Models\PlannedDinnerStatusEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedDinnerStatusEvent>
 */
class PlannedDinnerStatusEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'planned_dinner_id' => PlannedDinner::factory(),
            'event_type' => PlannedDinnerStatusEventType::Planned,
            'from_status' => null,
            'to_status' => PlannedDinnerStatus::Planned,
            'occurred_at' => now(),
            'actor_user_id' => User::factory(),
            'is_reconstructed' => false,
        ];
    }
}
