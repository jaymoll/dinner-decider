<?php

namespace Tests\Feature\Recipes;

use App\Actions\Recipes\CreateRecipe;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_can_create_a_recipe_from_the_nested_form(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test('pages::recipes.create')
            ->set('form.name', 'Simple pasta')
            ->set('form.default_servings', 4)
            ->set('form.ingredients.0.ingredient_id', $ingredient->id)
            ->call('ingredientChanged', 0)
            ->set('form.ingredients.0.amount', '400')
            ->set('form.steps.0.instruction', 'Cook the pasta')
            ->call('save')
            ->assertHasNoErrors();

        $recipe = $user->recipes()->where('name', 'Simple pasta')->firstOrFail();
        $this->assertSame('400.000000', $recipe->ingredients()->sole()->normalized_amount);
    }

    public function test_the_recipe_page_previews_scaled_and_non_exact_quantities(): void
    {
        $user = User::factory()->create();
        $pasta = Ingredient::factory()->for($user)->create();
        $salt = Ingredient::factory()->for($user)->create(['name' => 'Salt', 'normalized_name' => 'salt']);
        $recipe = app(CreateRecipe::class)->handle($user, [
            'name' => 'Preview recipe', 'default_servings' => 4,
            'ingredients' => [
                ['ingredient_id' => $pasta->id, 'quantity_type' => 'exact', 'amount' => '400', 'unit' => 'g', 'ingredient_package_id' => null, 'description' => null, 'non_exact_status' => null],
                ['ingredient_id' => $salt->id, 'quantity_type' => 'non_exact', 'amount' => null, 'unit' => null, 'ingredient_package_id' => null, 'description' => 'To taste', 'non_exact_status' => 'required'],
            ],
            'steps' => [['instruction' => 'Cook']], 'categories' => [], 'tags' => [],
        ]);

        Livewire::actingAs($user)
            ->test('pages::recipes.show', ['recipe' => $recipe])
            ->set('selectedServings', 2)
            ->assertSee('200 g')
            ->assertSee('To taste');

        $this->assertSame('400.000000', $recipe->ingredients()->firstOrFail()->entered_amount);
    }
}
