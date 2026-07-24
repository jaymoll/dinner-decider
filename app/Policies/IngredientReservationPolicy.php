<?php

namespace App\Policies;

use App\Models\IngredientReservation;
use App\Models\User;

class IngredientReservationPolicy
{
    public function view(User $user, IngredientReservation $reservation): bool
    {
        return $reservation->pantryEntry()->whereBelongsTo($user)->exists();
    }
}
