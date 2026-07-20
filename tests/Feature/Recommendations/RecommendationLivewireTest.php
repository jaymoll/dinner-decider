<?php

namespace Tests\Feature\Recommendations;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecommendationLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recommendations_render_explanations_and_recipe_links(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create(['name' => 'Rice']);
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Rice bowl']);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create();

        Livewire::actingAs($user)->test('pages::recommendations.index')
            ->assertSee('Rice bowl')->assertSee('Why this ranking?')->assertSee(route('recipes.show', $recipe));
    }

    public function test_servings_override_is_positive(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test('pages::recommendations.index')
            ->set('servings', '0')->call('applyServings')->assertHasErrors(['servings']);
    }

    public function test_recipe_without_exact_requirements_scores_zero_with_an_explanation(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Seasoned dish']);
        RecipeIngredient::factory()->nonExact()->for($recipe)->for($ingredient)->create();

        Livewire::actingAs($user)->test('pages::recommendations.index')
            ->assertSee('Seasoned dish')->assertSee('0')->assertSee('cannot be quantity-matched')->assertSee('Required')->assertSee('Excluded from score');
    }
}
