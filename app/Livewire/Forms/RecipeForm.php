<?php

namespace App\Livewire\Forms;

use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\User;
use App\Rules\CompatibleUnitForIngredient;
use App\Rules\PositiveDecimalQuantity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator as ValidationValidator;
use Livewire\Form;

class RecipeForm extends Form
{
    public ?int $recipeId = null;

    public string $name = '';

    public string $description = '';

    public int|string $default_servings = 4;

    public int|string|null $preparation_minutes = null;

    public int|string|null $cooking_minutes = null;

    public string $difficulty = '';

    public string $cuisine = '';

    public string $meal_type = '';

    public string $notes = '';

    public string $source_url = '';

    public mixed $image = null;

    public bool $remove_image = false;

    public string $categoryNames = '';

    public string $tagNames = '';

    /** @var array<int, array{key: string, ingredient_id: int|string, quantity_type: string, amount: string, unit: string, ingredient_package_id: int|string|null, description: string, non_exact_status: string}> */
    public array $ingredients = [];

    /** @var array<int, array{key: string, instruction: string}> */
    public array $steps = [];

    public function setRecipe(Recipe $recipe): void
    {
        $recipe->loadMissing(['ingredients', 'steps', 'categories', 'tags']);
        $this->recipeId = $recipe->id;
        $this->name = $recipe->name;
        $this->description = $recipe->description ?? '';
        $this->default_servings = $recipe->default_servings;
        $this->preparation_minutes = $recipe->preparation_minutes;
        $this->cooking_minutes = $recipe->cooking_minutes;
        $this->difficulty = $recipe->difficulty ?? '';
        $this->cuisine = $recipe->cuisine ?? '';
        $this->meal_type = $recipe->meal_type ?? '';
        $this->notes = $recipe->notes ?? '';
        $this->source_url = $recipe->source_url ?? '';
        $this->categoryNames = $recipe->categories->pluck('name')->implode(', ');
        $this->tagNames = $recipe->tags->pluck('name')->implode(', ');
        $this->ingredients = $recipe->ingredients->map(fn (RecipeIngredient $line): array => [
            'key' => (string) Str::uuid(),
            'ingredient_id' => $line->ingredient_id,
            'quantity_type' => $line->quantity_type->value,
            'amount' => $line->entered_amount ?? '',
            'unit' => $line->entered_unit->value ?? '',
            'ingredient_package_id' => $line->ingredient_package_id,
            'description' => $line->quantity_description ?? '',
            'non_exact_status' => $line->non_exact_status->value ?? NonExactStatus::Required->value,
        ])->values()->all();
        $this->steps = $recipe->steps->map(fn (RecipeStep $step): array => ['key' => (string) Str::uuid(), 'instruction' => $step->instruction])->values()->all();
    }

    public function initializeEmpty(): void
    {
        if ($this->ingredients === []) {
            $this->addIngredient();
        }
        if ($this->steps === []) {
            $this->addStep();
        }
    }

    /** @return array<string, mixed> */
    public function validated(User $user): array
    {
        $payload = $this->all();
        $payload['categories'] = $this->splitNames($this->categoryNames);
        $payload['tags'] = $this->splitNames($this->tagNames);

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_servings' => ['required', 'integer', 'min:1', 'max:1000'],
            'preparation_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'cooking_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'difficulty' => ['nullable', 'string', 'max:80'],
            'cuisine' => ['nullable', 'string', 'max:80'],
            'meal_type' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])
                ->max((int) config('measurements.limits.recipe_image_kilobytes'))
                ->dimensions(Rule::dimensions()
                    ->maxWidth((int) config('measurements.limits.recipe_image_dimension_pixels'))
                    ->maxHeight((int) config('measurements.limits.recipe_image_dimension_pixels')))],
            'remove_image' => ['boolean'],
            'ingredients' => ['required', 'array', 'min:1', 'max:'.config('measurements.limits.ingredients_per_recipe')],
            'ingredients.*.ingredient_id' => ['required', 'integer', Rule::exists(Ingredient::class, 'id')->where('user_id', $user->id)],
            'ingredients.*.quantity_type' => ['required', Rule::enum(QuantityType::class)],
            'ingredients.*.amount' => ['nullable', new PositiveDecimalQuantity],
            'ingredients.*.unit' => ['nullable', Rule::enum(UnitCode::class)],
            'ingredients.*.ingredient_package_id' => ['nullable', 'integer'],
            'ingredients.*.description' => ['nullable', 'string', 'max:255'],
            'ingredients.*.non_exact_status' => ['nullable', Rule::enum(NonExactStatus::class)],
            'steps' => ['required', 'array', 'min:1', 'max:'.config('measurements.limits.steps_per_recipe')],
            'steps.*.instruction' => ['required', 'string', 'max:5000'],
            'categories' => ['array', 'max:'.config('measurements.limits.categories_per_recipe')],
            'categories.*' => ['string', 'max:80'],
            'tags' => ['array', 'max:'.config('measurements.limits.tags_per_recipe')],
            'tags.*' => ['string', 'max:80'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($user): void {
            $this->validateIngredientRows($validator, $user);
        });

        return $validator->validate();
    }

    public function addIngredient(): void
    {
        $this->ingredients[] = ['key' => (string) Str::uuid(), 'ingredient_id' => '', 'quantity_type' => QuantityType::Exact->value, 'amount' => '', 'unit' => 'g', 'ingredient_package_id' => null, 'description' => '', 'non_exact_status' => NonExactStatus::Required->value];
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function addStep(): void
    {
        $this->steps[] = ['key' => (string) Str::uuid(), 'instruction' => ''];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function moveIngredient(string $key, int $position): void
    {
        $this->ingredients = $this->moveByKey($this->ingredients, $key, $position);
    }

    public function moveStep(string $key, int $position): void
    {
        $this->steps = $this->moveByKey($this->steps, $key, $position);
    }

    private function validateIngredientRows(ValidationValidator $validator, User $user): void
    {
        foreach ($this->ingredients as $index => $row) {
            $type = QuantityType::tryFrom($row['quantity_type']);
            if ($type === QuantityType::NonExact) {
                if (blank($row['description'])) {
                    $validator->errors()->add("ingredients.{$index}.description", 'Describe the non-exact quantity.');
                }
                if (filled($row['amount']) || filled($row['unit']) || filled($row['ingredient_package_id'])) {
                    $validator->errors()->add("ingredients.{$index}.amount", 'Non-exact ingredients cannot have an amount, unit, or package.');
                }

                continue;
            }

            if (blank($row['amount'])) {
                $validator->errors()->add("ingredients.{$index}.amount", 'Enter an amount.');
            }
            $ingredient = Ingredient::query()->whereBelongsTo($user)->find($row['ingredient_id']);
            if ($ingredient === null) {
                continue;
            }

            $isExistingRecipeIngredient = $this->recipeId !== null
                && $ingredient->recipeIngredients()->where('recipe_id', $this->recipeId)->exists();
            if ($ingredient->archived_at !== null && ! $isExistingRecipeIngredient) {
                $validator->errors()->add("ingredients.{$index}.ingredient_id", 'Archived ingredients cannot be added to a recipe.');

                continue;
            }

            if (filled($row['ingredient_package_id'])) {
                if (! IngredientPackage::query()->whereBelongsTo($ingredient)->whereKey($row['ingredient_package_id'])->exists()) {
                    $validator->errors()->add("ingredients.{$index}.ingredient_package_id", 'Select a package belonging to this ingredient.');
                }
            } elseif (blank($row['unit'])) {
                $validator->errors()->add("ingredients.{$index}.unit", 'Select a unit.');
            } else {
                $unitValidator = Validator::make(['unit' => $row['unit']], ['unit' => [new CompatibleUnitForIngredient($ingredient)]]);
                if ($unitValidator->fails()) {
                    $validator->errors()->add("ingredients.{$index}.unit", $unitValidator->errors()->first('unit'));
                }
            }
        }
    }

    /** @return list<string> */
    private function splitNames(string $value): array
    {
        return array_values(collect(explode(',', $value))->map(fn (string $name): string => Str::of($name)->trim()->squish()->toString())->filter()->unique(fn (string $name): string => Str::lower($name))->all());
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  array<int, T>  $rows
     * @return array<int, T>
     */
    private function moveByKey(array $rows, string $key, int $position): array
    {
        $current = collect($rows)->search(fn (array $row): bool => $row['key'] === $key);
        if ($current === false) {
            return $rows;
        }
        $row = array_splice($rows, $current, 1)[0];
        array_splice($rows, max(0, min($position, count($rows))), 0, [$row]);

        return $rows;
    }
}
