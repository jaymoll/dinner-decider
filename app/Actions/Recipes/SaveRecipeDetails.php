<?php

namespace App\Actions\Recipes;

use App\Data\Measurements\QuantityInput;
use App\Enums\MeasurementGroup;
use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\Tag;
use App\Services\Measurements\UnitConverter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @phpstan-type RecipeIngredientData array{ingredient_id: int, quantity_type: string, amount: string|null, unit: string|null, ingredient_package_id: int|null, description: string|null, non_exact_status: string|null}
 * @phpstan-type RecipeData array{name: string, description?: string|null, default_servings: int, preparation_minutes?: int|null, cooking_minutes?: int|null, difficulty?: string|null, cuisine?: string|null, meal_type?: string|null, notes?: string|null, source_url?: string|null, ingredients: list<RecipeIngredientData>, steps: list<array{instruction: string}>, categories?: list<string>, tags?: list<string>, image?: mixed, remove_image?: bool}
 */
final readonly class SaveRecipeDetails
{
    public function __construct(private UnitConverter $converter) {}

    /**
     * @param  RecipeData  $data
     */
    public function handle(Recipe $recipe, array $data): Recipe
    {
        $recipe->update([
            'name' => $this->clean($data['name']),
            'description' => $this->nullableClean($data['description'] ?? null),
            'default_servings' => $data['default_servings'],
            'preparation_minutes' => $data['preparation_minutes'] ?? null,
            'cooking_minutes' => $data['cooking_minutes'] ?? null,
            'difficulty' => $this->nullableClean($data['difficulty'] ?? null),
            'cuisine' => $this->nullableClean($data['cuisine'] ?? null),
            'meal_type' => $this->nullableClean($data['meal_type'] ?? null),
            'notes' => $this->nullableClean($data['notes'] ?? null),
            'source_url' => $this->nullableClean($data['source_url'] ?? null),
        ]);

        // Child rows are replaced in their submitted order inside the caller's transaction. Keep
        // the previous ingredient IDs so an archived ingredient already in this recipe remains
        // editable, while archived catalogue entries cannot be newly introduced.
        $existingIngredientIds = $recipe->ingredients()->pluck('ingredient_id');
        $recipe->ingredients()->delete();
        foreach ($data['ingredients'] as $index => $ingredientData) {
            $ingredient = Ingredient::query()->whereBelongsTo($recipe->user)
                ->where(fn ($query) => $query->active()->orWhereIn('id', $existingIngredientIds))
                ->findOrFail($ingredientData['ingredient_id']);
            $quantityType = QuantityType::from($ingredientData['quantity_type']);

            if ($quantityType === QuantityType::NonExact) {
                if (blank($ingredientData['description']) || $ingredientData['non_exact_status'] === null) {
                    throw new InvalidArgumentException('Non-exact ingredients require a description and status.');
                }

                $recipe->ingredients()->create([
                    'ingredient_id' => $ingredient->id,
                    'quantity_type' => $quantityType,
                    'quantity_description' => $this->clean((string) $ingredientData['description']),
                    'non_exact_status' => NonExactStatus::from((string) $ingredientData['non_exact_status']),
                    'position' => $index + 1,
                ]);

                continue;
            }

            $package = filled($ingredientData['ingredient_package_id'] ?? null)
                ? IngredientPackage::query()->whereBelongsTo($ingredient)->findOrFail((int) $ingredientData['ingredient_package_id'])
                : null;

            if ($package !== null) {
                $quantity = $this->converter->normalize(new QuantityInput(
                    amount: (string) $ingredientData['amount'],
                    ingredientId: $ingredient->id,
                    ingredientPackageId: $package->id,
                    packageContentAmount: $package->content_amount,
                    packageContentUnit: $package->content_unit,
                ));
                $enteredUnit = null;
            } else {
                $unit = UnitCode::from((string) $ingredientData['unit']);
                $this->assertUnitCompatible($ingredient, $unit);
                $quantity = $this->converter->normalize(new QuantityInput((string) $ingredientData['amount'], $unit, $ingredient->id));
                $enteredUnit = $unit;
            }

            $recipe->ingredients()->create([
                'ingredient_id' => $ingredient->id,
                'ingredient_package_id' => $package?->id,
                'quantity_type' => $quantityType,
                'entered_amount' => $quantity->amount,
                'entered_unit' => $enteredUnit,
                'normalized_amount' => $quantity->normalizedAmount,
                'compatibility_key' => (string) $quantity->compatibilityKey,
                'position' => $index + 1,
            ]);
        }

        $recipe->steps()->delete();
        foreach ($data['steps'] as $index => $step) {
            $recipe->steps()->create(['instruction' => $this->clean($step['instruction']), 'position' => $index + 1]);
        }

        $recipe->categories()->sync($this->catalogueIds(RecipeCategory::class, $recipe, $data['categories'] ?? []));
        $recipe->tags()->sync($this->catalogueIds(Tag::class, $recipe, $data['tags'] ?? []));

        return $recipe->refresh()->load(['ingredients.ingredient', 'ingredients.ingredientPackage', 'steps', 'categories', 'tags']);
    }

    private function assertUnitCompatible(Ingredient $ingredient, UnitCode $unit): void
    {
        if ($unit->measurementGroup() !== $ingredient->preferred_measurement_group) {
            throw new InvalidArgumentException('The selected unit is incompatible with the ingredient.');
        }

        // Count units carry ingredient-specific meaning; a clove cannot be inferred from a bulb.
        if ($unit->measurementGroup() === MeasurementGroup::Count && $unit !== $ingredient->preferred_unit) {
            throw new InvalidArgumentException('Semantic count units cannot be converted automatically.');
        }
    }

    /**
     * @param  class-string<RecipeCategory|Tag>  $modelClass
     * @param  list<string>  $names
     * @return list<int>
     */
    private function catalogueIds(string $modelClass, Recipe $recipe, array $names): array
    {
        // Catalogue labels are user-owned and case-insensitively deduplicated before sync.
        return array_values(collect($names)
            ->map(fn (string $name): string => $this->clean($name))
            ->filter()
            ->unique(fn (string $name): string => $this->normalize($name))
            ->map(fn (string $name): int => $modelClass::query()->firstOrCreate(
                ['user_id' => $recipe->user_id, 'normalized_name' => $this->normalize($name)],
                ['name' => $name],
            )->id)
            ->values()
            ->all());
    }

    private function clean(string $value): string
    {
        return Str::of($value)->trim()->squish()->toString();
    }

    private function nullableClean(?string $value): ?string
    {
        return filled($value) ? $this->clean((string) $value) : null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->trim()->squish()->lower()->toString();
    }
}
