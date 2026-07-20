<?php

namespace Tests\Unit\Recommendations;

use App\Data\Pantry\PantryAvailability;
use App\Data\Pantry\PantryBucket;
use App\Data\Recommendations\RecommendationResult;
use App\Enums\MeasurementGroup;
use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Measurements\QuantityInputParser;
use App\Services\Measurements\UnitConverter;
use App\Services\Recipes\RecipeScaler;
use App\Services\Recommendations\RecommendationEngine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    public function test_golden_full_partial_missing_and_incompatible_scores(): void
    {
        $this->assertSame('80.000000', $this->scoreFor('100')->score);
        $this->assertSame('20.000000', $this->scoreFor('50')->score);
        $this->assertSame('0', $this->scoreFor('0')->score);

        $incompatible = new PantryAvailability(collect(), collect([
            '1|volume' => new PantryBucket(1, 'volume', '100'),
        ]));
        $result = $this->engine()->score($this->recipe([$this->exactRequirement()]), $incompatible);

        $this->assertSame('incompatible', $result->matches[0]->status);
        $this->assertSame('0', $result->score);
    }

    public function test_duplicate_requirements_cannot_reuse_the_same_stock(): void
    {
        $recipe = $this->recipe([$this->exactRequirement('75', 1), $this->exactRequirement('75', 2)]);
        $pantry = new PantryAvailability(collect(), collect(['1|mass' => new PantryBucket(1, 'mass', '100')]));

        $result = $this->engine()->score($recipe, $pantry);

        $this->assertSame(['full', 'partial'], array_column($result->matches, 'status'));
        $this->assertSame('25.000000', $result->matches[1]->availableAmount);
        $this->assertSame('50.000000', $result->matches[1]->missingAmount);
    }

    public function test_available_staples_are_fully_covered_and_non_exact_lines_do_not_score(): void
    {
        $staple = $this->ingredient(staple: true);
        $exact = $this->exactRequirement(ingredient: $staple);
        $full = $this->engine()->score($this->recipe([$exact]), new PantryAvailability(collect(), collect()));

        $this->assertSame('staple', $full->matches[0]->status);
        $this->assertSame('80.000000', $full->score);

        $nonExact = new RecipeIngredient([
            'ingredient_id' => 1,
            'quantity_type' => QuantityType::NonExact,
            'quantity_description' => 'To taste',
            'non_exact_status' => NonExactStatus::Required,
            'position' => 1,
        ]);
        $nonExact->setRelation('ingredient', $this->ingredient());
        $result = $this->engine()->score($this->recipe([$nonExact]), new PantryAvailability(collect(), collect()));

        $this->assertSame('0', $result->score);
        $this->assertSame('non_exact', $result->matches[0]->status);
        $this->assertSame('required', $result->matches[0]->nonExactStatus);
    }

    public function test_unavailable_ingredients_and_staples_are_missing_even_when_stock_is_recorded(): void
    {
        $ingredient = $this->ingredient(staple: true);
        $ingredient->is_currently_available = false;
        $requirement = $this->exactRequirement(ingredient: $ingredient);
        $pantry = new PantryAvailability(collect(), collect(['1|mass' => new PantryBucket(1, 'mass', '1000')]));

        $result = $this->engine()->score($this->recipe([$requirement]), $pantry);

        $this->assertSame('missing', $result->matches[0]->status);
        $this->assertSame('0', $result->score);
    }

    private function scoreFor(string $available): RecommendationResult
    {
        $pantry = new PantryAvailability(collect(), collect(['1|mass' => new PantryBucket(1, 'mass', $available)]));

        return $this->engine()->score($this->recipe([$this->exactRequirement()]), $pantry);
    }

    /** @param list<RecipeIngredient> $requirements */
    private function recipe(array $requirements): Recipe
    {
        $recipe = new Recipe(['name' => 'Pasta', 'default_servings' => 4]);
        $recipe->id = 1;
        $recipe->setRelation('ingredients', new EloquentCollection($requirements));

        return $recipe;
    }

    private function exactRequirement(string $amount = '100', int $position = 1, ?Ingredient $ingredient = null): RecipeIngredient
    {
        $requirement = new RecipeIngredient([
            'ingredient_id' => 1,
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => $amount,
            'entered_unit' => UnitCode::Gram,
            'normalized_amount' => $amount,
            'compatibility_key' => 'mass',
            'position' => $position,
        ]);
        $requirement->setRelation('ingredient', $ingredient ?? $this->ingredient());
        $requirement->setRelation('ingredientPackage', null);

        return $requirement;
    }

    private function ingredient(bool $staple = false): Ingredient
    {
        $ingredient = new Ingredient([
            'name' => 'Flour',
            'preferred_measurement_group' => MeasurementGroup::Mass,
            'preferred_unit' => UnitCode::Gram,
            'is_staple' => $staple,
            'is_currently_available' => true,
        ]);
        $ingredient->id = 1;

        return $ingredient;
    }

    private function engine(): RecommendationEngine
    {
        return new RecommendationEngine(new UnitConverter(new QuantityInputParser), new RecipeScaler);
    }
}
