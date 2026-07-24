<?php

namespace Tests\Feature;

use App\Models\GroceryItem;
use App\Models\GroceryList;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
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
        $countsAfterFirstSeed = [
            Ingredient::class => Ingredient::query()->count(),
            IngredientPackage::class => IngredientPackage::query()->count(),
            PantryEntry::class => PantryEntry::query()->count(),
            Recipe::class => Recipe::query()->count(),
            PlannedDinner::class => PlannedDinner::query()->count(),
            GroceryItem::class => GroceryItem::query()->count(),
        ];
        $pantryTotalsAfterFirstSeed = PantryEntry::query()->orderBy('id')->pluck('total_normalized_amount', 'id')->all();
        $this->seed();

        $user = User::query()->where('email', 'test@example.com')->sole();

        $this->assertSame($countsAfterFirstSeed, [
            Ingredient::class => Ingredient::query()->count(),
            IngredientPackage::class => IngredientPackage::query()->count(),
            PantryEntry::class => PantryEntry::query()->count(),
            Recipe::class => Recipe::query()->count(),
            PlannedDinner::class => PlannedDinner::query()->count(),
            GroceryItem::class => GroceryItem::query()->count(),
        ]);
        $this->assertSame($pantryTotalsAfterFirstSeed, PantryEntry::query()->orderBy('id')->pluck('total_normalized_amount', 'id')->all());
        $this->assertSame(34, Ingredient::query()->whereBelongsTo($user)->count());
        $this->assertSame(1, Ingredient::query()->whereBelongsTo($user)->archived()->count());
        $this->assertSame(10, IngredientPackage::query()->count());
        $this->assertSame(10, PantryEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(11, Recipe::query()->whereBelongsTo($user)->count());
        $this->assertSame(10, Recipe::query()->whereBelongsTo($user)->active()->count());
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

        $this->assertTrue(IngredientPackage::query()->where('label', '145 g can')->sole()->hasKnownContents());
        $this->assertFalse(IngredientPackage::query()->where('label', 'Bakery loaf')->sole()->hasKnownContents());

        $minimalRecipe = Recipe::query()->whereBelongsTo($user)->with(['ingredients', 'steps'])->where('name', 'Butter Toast')->sole();
        $this->assertNull($minimalRecipe->description);
        $this->assertNull($minimalRecipe->preparation_minutes);
        $this->assertNull($minimalRecipe->cooking_minutes);
        $this->assertNull($minimalRecipe->difficulty);
        $this->assertNull($minimalRecipe->cuisine);
        $this->assertNull($minimalRecipe->meal_type);
        $this->assertNull($minimalRecipe->notes);
        $this->assertNull($minimalRecipe->source_url);
        $this->assertCount(1, $minimalRecipe->ingredients);
        $this->assertCount(1, $minimalRecipe->steps);

        $this->assertSame('bulb', Recipe::query()->whereBelongsTo($user)->where('name', 'Garlic Bulb Soup')->sole()->ingredients()->firstOrFail()->entered_unit->value);

        $this->assertTrue(PlannedDinner::query()->where('recipe_name', 'Spinach Omelette')->where('status', 'cooked')->exists());
        $this->assertTrue(PlannedDinner::query()->where('recipe_name', 'Butter Toast')->where('status', 'cancelled')->exists());
        $groceryList = GroceryList::query()->whereHas('dinnerPlan', fn ($query) => $query->whereBelongsTo($user))->sole();
        $this->assertTrue($groceryList->items()->where('source', 'manual')->where('name', 'Paper towels')->exists());
        $this->assertTrue($groceryList->items()->whereNotNull('checked_at')->exists());
        $this->assertTrue($groceryList->items()->where('is_manually_adjusted', true)->exists());
    }

    public function test_database_seeder_never_creates_demo_credentials_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        (new DatabaseSeeder)->run();

        $this->assertFalse(User::query()->where('email', 'test@example.com')->exists());
    }
}
