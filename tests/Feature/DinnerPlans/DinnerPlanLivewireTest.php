<?php

namespace Tests\Feature\DinnerPlans;

use App\Actions\DinnerPlans\PlanDinner;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DinnerPlanLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_page_renders_coverage_validates_edits_and_opens_unresolved_confirmation(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create(['name' => 'Potatoes']);
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Mash']);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create(['entered_amount' => '100', 'normalized_amount' => '100']);
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '50']);
        $dinner = app(PlanDinner::class)->handle($user, $recipe, '4');

        Livewire::actingAs($user)
            ->test('pages::dinner-plans.index')
            ->assertSee('Mash')
            ->assertSee('Partial')
            ->set("servings.{$dinner->id}", '0')
            ->call('updateServings', $dinner->id)
            ->assertHasErrors("servings.{$dinner->id}")
            ->call('cook', $dinner->id)
            ->assertSet('pendingCookDinnerId', $dinner->id)
            ->assertSee('Cook with unresolved requirements?');
    }

    public function test_sorting_persists_the_new_zero_based_livewire_position(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = Recipe::factory()->for($user)->create();
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create();
        $first = app(PlanDinner::class)->handle($user, $recipe, '4');
        $second = app(PlanDinner::class)->handle($user, $recipe, '4');

        Livewire::actingAs($user)->test('pages::dinner-plans.index')->call('sortDinner', $second->id, 0);

        $this->assertSame(1, $second->refresh()->position);
        $this->assertSame(2, $first->refresh()->position);
    }
}
