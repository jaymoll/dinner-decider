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
final readonly class CreateRecipe
{
    public function __construct(private SaveRecipeDetails $saveRecipeDetails) {}

    /** @param RecipeData $data */
    public function handle(User $user, array $data): Recipe
    {
        Gate::forUser($user)->authorize('create', Recipe::class);
        $imagePath = $this->storeImage($data['image'] ?? null);

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
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $throwable;
        }
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
