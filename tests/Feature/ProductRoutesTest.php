<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductRoutesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_routes_require_authentication(): void
    {
        $this->get(route('ingredients.index'))->assertRedirect(route('login'));
        $this->get(route('recipes.index'))->assertRedirect(route('login'));
    }

    public function test_product_routes_require_a_verified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('ingredients.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('recipes.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_can_view_catalogue_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('ingredients.index'))->assertOk()->assertSee('Ingredients');
        $this->actingAs($user)->get(route('recipes.index'))->assertOk()->assertSee('Recipes');
    }

    public function test_users_cannot_open_another_users_catalogue_records(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create();
        $recipe = Recipe::factory()->create();

        $this->actingAs($user)->get(route('ingredients.edit', $ingredient))->assertForbidden();
        $this->actingAs($user)->get(route('recipes.show', $recipe))->assertForbidden();
    }
}
