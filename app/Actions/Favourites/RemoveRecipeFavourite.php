<?php

namespace App\Actions\Favourites;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RemoveRecipeFavourite
{
    public function handle(User $user, Recipe $recipe): void
    {
        Gate::forUser($user)->authorize('favourite', $recipe);

        DB::table('recipe_favourites')
            ->where('user_id', $user->id)
            ->where('recipe_id', $recipe->id)
            ->delete();
    }
}
