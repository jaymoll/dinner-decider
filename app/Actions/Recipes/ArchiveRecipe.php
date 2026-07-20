<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ArchiveRecipe
{
    public function handle(User $user, Recipe $recipe): Recipe
    {
        Gate::forUser($user)->authorize('delete', $recipe);
        $recipe->update(['archived_at' => now()]);

        return $recipe;
    }
}
