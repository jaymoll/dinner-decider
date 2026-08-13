<?php

namespace App\Actions\Favourites;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class AddRecipeFavourite
{
    public function handle(User $user, Recipe $recipe): void
    {
        Gate::forUser($user)->authorize('favourite', $recipe);

        // The unique pair is the concurrency boundary; insertOrIgnore keeps repeated clicks and
        // simultaneous requests idempotent without weakening ownership authorization.
        DB::table('recipe_favourites')->insertOrIgnore([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
