<?php

namespace Tests\Feature\Recipes;

use App\Actions\Recipes\ArchiveRecipe;
use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\RestoreRecipe;
use App\Actions\Recipes\UpdateRecipe;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_can_create_a_recipe_with_exact_package_and_non_exact_lines(): void
    {
        $user = User::factory()->create();
        $pasta = Ingredient::factory()->for($user)->create();
        $tomatoes = Ingredient::factory()->for($user)->create(['name' => 'Tomatoes', 'normalized_name' => 'tomatoes']);
        $salt = Ingredient::factory()->for($user)->create(['name' => 'Salt', 'normalized_name' => 'salt']);
        $package = IngredientPackage::factory()->for($tomatoes)->create();

        $recipe = app(CreateRecipe::class)->handle($user, $this->recipeData([
            ['ingredient_id' => $pasta->id, 'quantity_type' => 'exact', 'amount' => '0.4', 'unit' => 'kg', 'ingredient_package_id' => null, 'description' => null, 'non_exact_status' => null],
            ['ingredient_id' => $tomatoes->id, 'quantity_type' => 'exact', 'amount' => '1', 'unit' => null, 'ingredient_package_id' => $package->id, 'description' => null, 'non_exact_status' => null],
            ['ingredient_id' => $salt->id, 'quantity_type' => 'non_exact', 'amount' => null, 'unit' => null, 'ingredient_package_id' => null, 'description' => 'To taste', 'non_exact_status' => 'required'],
        ]));

        $this->assertCount(3, $recipe->ingredients);
        $this->assertSame('400.000000', $recipe->ingredients[0]->normalized_amount);
        $this->assertSame('400.000000', $recipe->ingredients[1]->normalized_amount);
        $this->assertNull($recipe->ingredients[2]->normalized_amount);
        $this->assertSame(['Dinner'], $recipe->categories->pluck('name')->all());
        $this->assertSame(['Quick'], $recipe->tags->pluck('name')->all());
    }

    public function test_recipe_updates_replace_ordered_details_atomically(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = app(CreateRecipe::class)->handle($user, $this->recipeData([
            ['ingredient_id' => $ingredient->id, 'quantity_type' => 'exact', 'amount' => '100', 'unit' => 'g', 'ingredient_package_id' => null, 'description' => null, 'non_exact_status' => null],
        ]));

        $updated = app(UpdateRecipe::class)->handle($user, $recipe, array_merge($this->recipeData([
            ['ingredient_id' => $ingredient->id, 'quantity_type' => 'exact', 'amount' => '250', 'unit' => 'g', 'ingredient_package_id' => null, 'description' => null, 'non_exact_status' => null],
        ]), ['name' => 'Updated pasta', 'steps' => [['instruction' => 'First'], ['instruction' => 'Second']]]));

        $this->assertSame('Updated pasta', $updated->name);
        $this->assertSame('250.000000', $updated->ingredients->sole()->normalized_amount);
        $this->assertSame([1, 2], $updated->steps->pluck('position')->all());
    }

    public function test_recipe_images_are_stored_with_generated_names_and_removed_on_replacement(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $data = $this->recipeData([['ingredient_id' => $ingredient->id, 'quantity_type' => 'exact', 'amount' => '100', 'unit' => 'g', 'ingredient_package_id' => null, 'description' => null, 'non_exact_status' => null]]);
        $recipe = app(CreateRecipe::class)->handle($user, array_merge($data, ['image' => UploadedFile::fake()->image('unsafe-name.jpg')]));
        $oldPath = $recipe->image_path;

        Storage::disk('public')->assertExists($oldPath);

        $updated = app(UpdateRecipe::class)->handle($user, $recipe, array_merge($data, ['image' => UploadedFile::fake()->image('replacement.png')]));

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($updated->image_path);
    }

    public function test_recipe_archive_restore_and_ownership_are_enforced(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = app(CreateRecipe::class)->handle($user, $this->recipeData([['ingredient_id' => $ingredient->id, 'quantity_type' => 'exact', 'amount' => '100', 'unit' => 'g', 'ingredient_package_id' => null, 'description' => null, 'non_exact_status' => null]]));

        app(ArchiveRecipe::class)->handle($user, $recipe);
        $this->assertNotNull($recipe->refresh()->archived_at);
        app(RestoreRecipe::class)->handle($user, $recipe);
        $this->assertNull($recipe->refresh()->archived_at);

        $this->expectException(AuthorizationException::class);
        app(ArchiveRecipe::class)->handle(User::factory()->create(), $recipe);
    }

    /** @param list<array<string, mixed>> $ingredients @return array<string, mixed> */
    private function recipeData(array $ingredients): array
    {
        return [
            'name' => 'Pasta dinner', 'description' => null, 'default_servings' => 4,
            'preparation_minutes' => null, 'cooking_minutes' => null, 'difficulty' => null,
            'cuisine' => null, 'meal_type' => null, 'notes' => null, 'source_url' => null,
            'ingredients' => $ingredients, 'steps' => [['instruction' => 'Cook everything']],
            'categories' => ['Dinner'], 'tags' => ['Quick'],
        ];
    }
}
