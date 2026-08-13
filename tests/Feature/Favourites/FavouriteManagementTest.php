<?php

namespace Tests\Feature\Favourites;

use App\Actions\Favourites\AddRecipeFavourite;
use App\Actions\Favourites\RemoveRecipeFavourite;
use App\Actions\Recipes\ArchiveRecipe;
use App\Actions\Recipes\RestoreRecipe;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Queries\GetPantryAwareRecommendations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FavouriteManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_add_and_remove_are_idempotent_and_isolated_per_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $recipe = Recipe::factory()->for($owner)->create();

        app(AddRecipeFavourite::class)->handle($owner, $recipe);
        app(AddRecipeFavourite::class)->handle($owner, $recipe);

        $this->assertSame(1, $owner->favouriteRecipes()->whereKey($recipe->id)->count());
        $this->assertFalse($otherUser->favouriteRecipes()->whereKey($recipe->id)->exists());

        app(RemoveRecipeFavourite::class)->handle($owner, $recipe);
        app(RemoveRecipeFavourite::class)->handle($owner, $recipe);

        $this->assertFalse($owner->favouriteRecipes()->whereKey($recipe->id)->exists());
    }

    public function test_a_user_cannot_favourite_another_users_recipe(): void
    {
        $this->expectException(AuthorizationException::class);

        app(AddRecipeFavourite::class)->handle(User::factory()->create(), Recipe::factory()->create());
    }

    public function test_favourite_survives_archive_and_restore(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create();
        app(AddRecipeFavourite::class)->handle($user, $recipe);

        app(ArchiveRecipe::class)->handle($user, $recipe);
        $this->assertTrue($user->favouriteRecipes()->whereKey($recipe->id)->exists());

        app(RestoreRecipe::class)->handle($user, $recipe);
        $this->assertTrue($user->favouriteRecipes()->whereKey($recipe->id)->exists());
    }

    public function test_catalogue_and_detail_controls_filter_favourites_and_reset_pagination(): void
    {
        $user = User::factory()->create();
        $favourite = Recipe::factory()->for($user)->create(['name' => 'Favourite recipe']);
        $ordinary = Recipe::factory()->for($user)->create(['name' => 'Ordinary recipe']);

        $catalogue = Livewire::actingAs($user)
            ->test('pages::recipes.index')
            ->call('setPage', 2)
            ->call('addFavourite', $favourite->id)
            ->set('favouritesOnly', true)
            ->assertSet('paginators.page', 1)
            ->assertSee('Favourite recipe')
            ->assertDontSee('Ordinary recipe')
            ->call('removeFavourite', $favourite->id)
            ->assertDontSee('Favourite recipe');

        $catalogue->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test('pages::recipes.show', ['recipe' => $ordinary])
            ->assertSee('Add favourite')
            ->call('addFavourite')
            ->assertSee('Remove favourite')
            ->call('removeFavourite')
            ->assertSee('Add favourite');
    }

    public function test_catalogue_query_count_stays_constant_as_recipe_volume_grows(): void
    {
        $smallUser = User::factory()->create();
        Recipe::factory()->count(2)->for($smallUser)->create();
        $smallCount = $this->catalogueQueryCount($smallUser);

        $largeUser = User::factory()->create();
        Recipe::factory()->count(24)->for($largeUser)->create();
        $largeCount = $this->catalogueQueryCount($largeUser);

        $this->assertSame($smallCount, $largeCount);
    }

    public function test_pantry_gaps_precede_favourites_and_favourites_break_equivalent_ties(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '100']);
        $suitable = $this->recipe($user, $ingredient, 'Zulu suitable', '100');
        $favouriteButMissing = $this->recipe($user, $ingredient, 'Missing favourite', '200');
        $equivalentFavourite = $this->recipe($user, $ingredient, 'Zulu favourite', '100');
        app(AddRecipeFavourite::class)->handle($user, $favouriteButMissing);
        app(AddRecipeFavourite::class)->handle($user, $equivalentFavourite);

        $results = app(GetPantryAwareRecommendations::class)->ranked($user);

        $this->assertSame([$equivalentFavourite->id, $suitable->id, $favouriteButMissing->id], $results->pluck('recipe.id')->all());
        $this->assertSame($results[0]->score, $results[1]->score);
        $this->assertTrue($results[0]->isFavourite);
    }

    private function catalogueQueryCount(User $user): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        Livewire::actingAs($user)->test('pages::recipes.index')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function recipe(User $user, Ingredient $ingredient, string $name, string $amount): Recipe
    {
        $recipe = Recipe::factory()->for($user)->create(['name' => $name]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create([
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => $amount,
            'entered_unit' => UnitCode::Gram,
            'normalized_amount' => $amount,
            'compatibility_key' => 'mass',
        ]);

        return $recipe;
    }
}
