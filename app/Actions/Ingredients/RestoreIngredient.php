<?php

namespace App\Actions\Ingredients;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class RestoreIngredient
{
    public function handle(User $user, Ingredient $ingredient): Ingredient
    {
        Gate::forUser($user)->authorize('restore', $ingredient);
        $ingredient->update(['archived_at' => null]);

        return $ingredient;
    }
}
