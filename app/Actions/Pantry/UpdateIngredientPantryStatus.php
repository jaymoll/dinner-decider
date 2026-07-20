<?php

namespace App\Actions\Pantry;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateIngredientPantryStatus
{
    public function handle(User $user, Ingredient $ingredient, bool $isStaple, bool $isCurrentlyAvailable): Ingredient
    {
        Gate::forUser($user)->authorize('update', $ingredient);
        $ingredient->update([
            'is_staple' => $isStaple,
            'is_currently_available' => $isCurrentlyAvailable,
        ]);

        return $ingredient->refresh();
    }
}
