<?php

namespace App\Actions\Ingredients;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ArchiveIngredient
{
    public function handle(User $user, Ingredient $ingredient): Ingredient
    {
        Gate::forUser($user)->authorize('delete', $ingredient);
        $ingredient->update(['archived_at' => now()]);

        return $ingredient;
    }
}
