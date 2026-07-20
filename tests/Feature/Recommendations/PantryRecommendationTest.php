<?php

namespace Tests\Feature\Recommendations;

use App\Actions\Pantry\AddPantryStock;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Queries\GetPantryAwareRecommendations;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PantryRecommendationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_results_are_ranked_deterministically_and_archived_recipes_are_excluded(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '100']);
        $full = $this->recipe($user, $ingredient, 'Alpha', '100');
        $partial = $this->recipe($user, $ingredient, 'Beta', '200');
        $this->recipe($user, $ingredient, 'Archived', '100', true);

        $results = app(GetPantryAwareRecommendations::class)->get($user);

        $this->assertSame([$full->id, $partial->id], $results->getCollection()->pluck('recipe.id')->all());
        $this->assertSame('80.000000', $results[0]->score);
        $this->assertSame('20.000000', $results[1]->score);
    }

    public function test_query_count_is_constant_as_recipe_count_grows(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        PantryEntry::factory()->for($user)->for($ingredient)->create();
        foreach (range(1, 10) as $index) {
            $this->recipe($user, $ingredient, 'Recipe '.$index, '100');
        }

        $this->expectsDatabaseQueryCount(6);
        app(GetPantryAwareRecommendations::class)->get($user);
    }

    public function test_known_packages_and_direct_metric_stock_jointly_cover_requirements(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $package = IngredientPackage::factory()->for($ingredient)->create(['content_amount' => '400', 'normalized_content_amount' => '400']);
        $recipe = $this->recipe($user, $ingredient, 'Tomato sauce', '900');

        app(AddPantryStock::class)->handle($user, ['ingredient_id' => $ingredient->id, 'amount' => '2', 'unit' => null, 'ingredient_package_id' => $package->id]);
        app(AddPantryStock::class)->handle($user, ['ingredient_id' => $ingredient->id, 'amount' => '100', 'unit' => 'g', 'ingredient_package_id' => null]);

        $result = app(GetPantryAwareRecommendations::class)->get($user)[0];
        $this->assertSame($recipe->id, $result->recipe->id);
        $this->assertSame('full', $result->matches[0]->status);
    }

    public function test_unknown_packages_match_only_the_identical_definition(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $requiredPackage = IngredientPackage::factory()->unknownContents()->for($ingredient)->create(['label' => 'Required pack']);
        $otherPackage = IngredientPackage::factory()->unknownContents()->for($ingredient)->create(['label' => 'Other pack']);
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Packet dinner']);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create([
            'ingredient_package_id' => $requiredPackage->id,
            'entered_amount' => '2',
            'entered_unit' => null,
            'normalized_amount' => '2',
            'compatibility_key' => 'package:'.$requiredPackage->id,
        ]);
        app(AddPantryStock::class)->handle($user, ['ingredient_id' => $ingredient->id, 'amount' => '2', 'unit' => null, 'ingredient_package_id' => $otherPackage->id]);

        $incompatible = app(GetPantryAwareRecommendations::class)->get($user)[0];
        $this->assertSame('incompatible', $incompatible->matches[0]->status);

        app(AddPantryStock::class)->handle($user, ['ingredient_id' => $ingredient->id, 'amount' => '2', 'unit' => null, 'ingredient_package_id' => $requiredPackage->id]);
        $full = app(GetPantryAwareRecommendations::class)->get($user)[0];
        $this->assertSame('full', $full->matches[0]->status);
    }

    public function test_ties_use_name_then_recipe_id(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $zulu = $this->recipe($user, $ingredient, 'Zulu', '100');
        $alphaFirst = $this->recipe($user, $ingredient, 'Alpha', '100');
        $alphaSecond = $this->recipe($user, $ingredient, 'Alpha', '100');

        $ids = app(GetPantryAwareRecommendations::class)->get($user)->getCollection()->pluck('recipe.id')->all();

        $this->assertSame([$alphaFirst->id, $alphaSecond->id, $zulu->id], $ids);
    }

    private function recipe(User $user, Ingredient $ingredient, string $name, string $amount, bool $archived = false): Recipe
    {
        $recipe = Recipe::factory()->for($user)->create(['name' => $name, 'archived_at' => $archived ? now() : null]);
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
