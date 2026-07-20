<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** @phpstan-import-type RecipeData from SaveRecipeDetails */
final readonly class UpdateRecipe
{
    public function __construct(private SaveRecipeDetails $saveRecipeDetails) {}

    /** @param RecipeData $data */
    public function handle(User $user, Recipe $recipe, array $data): Recipe
    {
        Gate::forUser($user)->authorize('update', $recipe);
        $previousImagePath = $recipe->image_path;
        $newImagePath = $this->storeImage($data['image'] ?? null);
        $imagePath = $newImagePath ?? (($data['remove_image'] ?? false) ? null : $previousImagePath);

        try {
            $updatedRecipe = DB::transaction(function () use ($recipe, $data, $imagePath): Recipe {
                $recipe->update(['image_path' => $imagePath]);

                return $this->saveRecipeDetails->handle($recipe, $data);
            });
        } catch (Throwable $throwable) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $throwable;
        }

        if ($previousImagePath !== null && $previousImagePath !== $imagePath) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return $updatedRecipe;
    }

    private function storeImage(mixed $image): ?string
    {
        if (! $image instanceof UploadedFile) {
            return null;
        }

        $path = $image->store('recipe-images', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The recipe image could not be stored.');
        }

        return $path;
    }
}
