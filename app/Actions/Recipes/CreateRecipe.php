<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImageStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/** @phpstan-import-type RecipeData from SaveRecipeDetails */
final readonly class CreateRecipe
{
    public function __construct(
        private SaveRecipeDetails $saveRecipeDetails,
        private RecipeImageStorage $recipeImageStorage,
    ) {}

    /** @param RecipeData $data */
    public function handle(User $user, array $data): Recipe
    {
        Gate::forUser($user)->authorize('create', Recipe::class);
        $imagePath = $this->recipeImageStorage->store($data['image'] ?? null);

        try {
            return DB::transaction(function () use ($user, $data, $imagePath): Recipe {
                $recipe = Recipe::query()->create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'default_servings' => $data['default_servings'],
                    'image_path' => $imagePath,
                ]);

                return $this->saveRecipeDetails->handle($recipe, $data);
            });
        } catch (Throwable $throwable) {
            $this->recipeImageStorage->delete($imagePath);

            throw $throwable;
        }
    }
}
