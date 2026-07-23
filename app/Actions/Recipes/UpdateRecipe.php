<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImageStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/** @phpstan-import-type RecipeData from SaveRecipeDetails */
final readonly class UpdateRecipe
{
    public function __construct(
        private SaveRecipeDetails $saveRecipeDetails,
        private RecipeImageStorage $recipeImageStorage,
    ) {}

    /** @param RecipeData $data */
    public function handle(User $user, Recipe $recipe, array $data): Recipe
    {
        Gate::forUser($user)->authorize('update', $recipe);
        $previousImagePath = $recipe->image_path;
        $newImagePath = $this->recipeImageStorage->store($data['image'] ?? null);
        $imagePath = $newImagePath ?? (($data['remove_image'] ?? false) ? null : $previousImagePath);

        try {
            $updatedRecipe = DB::transaction(function () use ($recipe, $data, $imagePath): Recipe {
                $recipe->update(['image_path' => $imagePath]);

                return $this->saveRecipeDetails->handle($recipe, $data);
            });
        } catch (Throwable $throwable) {
            // Keep the previous image intact and remove only the uncommitted replacement.
            $this->recipeImageStorage->delete($newImagePath);

            throw $throwable;
        }

        // Delete the old image only after the database points safely at its replacement or null.
        if ($previousImagePath !== null && $previousImagePath !== $imagePath) {
            $this->recipeImageStorage->delete($previousImagePath);
        }

        return $updatedRecipe;
    }
}
