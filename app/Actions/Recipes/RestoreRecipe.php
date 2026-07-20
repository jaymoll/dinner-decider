<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class RestoreRecipe
{
    public function handle(User $user, Recipe $recipe): Recipe
    {
        Gate::forUser($user)->authorize('restore', $recipe);
        $recipe->update(['archived_at' => null]);

        return $recipe;
    }
}
