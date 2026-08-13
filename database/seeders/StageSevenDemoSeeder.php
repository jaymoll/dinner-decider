<?php

namespace Database\Seeders;

use App\Actions\Favourites\AddRecipeFavourite;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Adds personal preference inputs to the action-derived Stage 3 history fixture. */
class StageSevenDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first();
        if ($user === null) {
            return;
        }

        Recipe::query()->whereBelongsTo($user)->active()
            ->whereIn('name', ['Chickpea Tomato Curry', 'Spaghetti Aglio e Olio', 'Spinach Omelette'])
            ->get()
            ->each(fn (Recipe $recipe) => app(AddRecipeFavourite::class)->handle($user, $recipe));
    }
}
