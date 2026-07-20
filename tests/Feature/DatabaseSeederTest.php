<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_a_verified_account_with_known_credentials_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $user = User::query()->where('email', 'test@example.com')->sole();

        $this->assertSame('Test User', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNull($user->two_factor_secret);
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
    }

    public function test_it_seeds_comprehensive_demo_data_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $user = User::query()->where('email', 'test@example.com')->sole();

        $this->assertSame(17, Ingredient::query()->whereBelongsTo($user)->count());
        $this->assertSame(1, Ingredient::query()->whereBelongsTo($user)->archived()->count());
        $this->assertSame(4, IngredientPackage::query()->count());
        $this->assertSame(10, PantryEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(6, Recipe::query()->whereBelongsTo($user)->count());
        $this->assertSame(5, Recipe::query()->whereBelongsTo($user)->active()->count());
        $this->assertSame(3, RecipeCategory::query()->whereBelongsTo($user)->count());
        $this->assertSame(4, Tag::query()->whereBelongsTo($user)->count());

        $recipe = Recipe::query()
            ->whereBelongsTo($user)
            ->with(['ingredients', 'steps', 'categories', 'tags'])
            ->where('name', 'Spaghetti Aglio e Olio')
            ->sole();

        $this->assertCount(5, $recipe->ingredients);
        $this->assertCount(3, $recipe->steps);
        $this->assertCount(2, $recipe->categories);
        $this->assertCount(1, $recipe->tags);
        $this->assertSame([1, 2, 3, 4, 5], $recipe->ingredients->pluck('position')->all());
    }
}
