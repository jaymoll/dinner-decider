<?php

namespace App\Services\Recommendations;

use App\Data\Measurements\QuantityInput;
use App\Data\Pantry\PantryAvailability;
use App\Data\Recommendations\IngredientMatch;
use App\Data\Recommendations\RecommendationResult;
use App\Enums\MeasurementGroup;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Measurements\UnitConverter;
use App\Services\Recipes\RecipeScaler;
use App\ValueObjects\Quantity;

/**
 * Scores one recipe against a pantry snapshot while preventing stock from being counted twice.
 */
final readonly class RecommendationEngine
{
    public function __construct(
        private UnitConverter $converter,
        private RecipeScaler $scaler,
    ) {}

    /** @param numeric-string|null $servings */
    public function score(Recipe $recipe, PantryAvailability $pantry, ?string $servings = null): RecommendationResult
    {
        $selectedServings = $servings ?? (string) $recipe->default_servings;

        // This mutable copy is consumed line by line so repeated recipe requirements compete for
        // the same pantry stock instead of each receiving the bucket's original full amount.
        /** @var array<string, numeric-string> $remaining */
        $remaining = $pantry->buckets->mapWithKeys(fn ($bucket): array => [$bucket->key() => $bucket->availableAmount])->all();
        $matches = [];
        $coverageSum = '0';
        $counts = ['full' => 0, 'partial' => 0, 'missing' => 0, 'incompatible' => 0];
        $exactCount = 0;

        foreach ($recipe->ingredients as $requirement) {
            if (! $requirement->isExact()) {
                $matches[] = $this->nonExactMatch($requirement, $pantry);

                continue;
            }

            $exactCount++;
            $quantity = $this->scaler->scaleQuantity(
                $this->quantity($requirement),
                $selectedServings,
                (string) $recipe->default_servings,
            );
            $ingredient = $requirement->ingredient;
            $key = $ingredient->id.'|'.(string) $quantity->compatibilityKey;

            if ($ingredient->is_staple && $ingredient->is_currently_available) {
                $counts['full']++;
                $coverageSum = bcadd($coverageSum, '1', $this->scale());
                $matches[] = $this->exactMatch($requirement, $quantity, 'staple', $quantity->normalizedAmount, '0');

                continue;
            }

            if (! $ingredient->is_currently_available) {
                $counts['missing']++;
                $matches[] = $this->exactMatch($requirement, $quantity, 'missing', '0', $quantity->normalizedAmount);

                continue;
            }

            $available = $remaining[$key] ?? '0';
            if (bccomp($available, '0', $this->scale()) > 0) {
                $covered = bccomp($available, $quantity->normalizedAmount, $this->scale()) >= 0
                    ? $quantity->normalizedAmount
                    : $available;
                $missing = bcsub($quantity->normalizedAmount, $covered, $this->scale());
                $remaining[$key] = bcsub($available, $covered, $this->scale());
                $coverage = bcdiv($covered, $quantity->normalizedAmount, $this->scale());
                $coverageSum = bcadd($coverageSum, $coverage, $this->scale());
                $status = bccomp($missing, '0', $this->scale()) === 0 ? 'full' : 'partial';
                $counts[$status]++;
                $matches[] = $this->exactMatch($requirement, $quantity, $status, $covered, $missing);

                continue;
            }

            $hasIncompatibleStock = collect($remaining)->contains(
                fn (string $amount, string $bucketKey): bool => str_starts_with($bucketKey, $ingredient->id.'|')
                    && bccomp($amount, '0', $this->scale()) > 0,
            );

            // Stock for the ingredient is only useful when its compatibility key matches; keeping
            // this distinct from "missing" explains semantic-count and package mismatches.
            $status = $hasIncompatibleStock ? 'incompatible' : 'missing';
            $counts[$status]++;
            $matches[] = $this->exactMatch($requirement, $quantity, $status, '0', $quantity->normalizedAmount);
        }

        // Non-exact lines are explanatory only and never dilute the quantity-based score.
        $quantityCoverage = $exactCount === 0 ? '0' : bcdiv($coverageSum, (string) $exactCount, $this->scale());
        $score = $exactCount === 0 ? '0' : $this->calculateScore($quantityCoverage, $counts, $exactCount);

        return new RecommendationResult(
            $recipe,
            $selectedServings,
            $score,
            $quantityCoverage,
            $matches,
            $counts['full'],
            $counts['partial'],
            $counts['missing'],
            $counts['incompatible'],
            $exactCount,
        );
    }

    private function quantity(RecipeIngredient $requirement): Quantity
    {
        $package = $requirement->ingredientPackage;

        return $this->converter->normalize(new QuantityInput(
            amount: (string) $requirement->entered_amount,
            unit: $package === null ? $requirement->entered_unit : null,
            ingredientId: $requirement->ingredient_id,
            ingredientPackageId: $package?->id,
            packageContentAmount: $package?->content_amount,
            packageContentUnit: $package?->content_unit,
        ));
    }

    /**
     * @param  numeric-string  $available
     * @param  numeric-string  $missing
     */
    private function exactMatch(RecipeIngredient $requirement, Quantity $quantity, string $status, string $available, string $missing): IngredientMatch
    {
        return new IngredientMatch(
            $requirement->ingredient_id,
            $requirement->ingredient->name,
            $status,
            true,
            $quantity->normalizedAmount,
            $available,
            $missing,
            (string) $quantity->compatibilityKey,
            unitLabel: $this->unitLabel($quantity),
        );
    }

    private function nonExactMatch(RecipeIngredient $requirement, PantryAvailability $pantry): IngredientMatch
    {
        $ingredient = $requirement->ingredient;

        // Presence is useful UI context for non-exact lines, but remains excluded from scoring.
        $hasStock = $ingredient->is_currently_available && $pantry->buckets->contains(
            fn ($bucket): bool => $bucket->ingredientId === $ingredient->id
                && ($bucket->unlimited || bccomp($bucket->availableAmount, '0', $this->scale()) > 0),
        );

        return new IngredientMatch(
            $ingredient->id,
            $ingredient->name,
            $ingredient->is_staple && $ingredient->is_currently_available ? 'staple' : 'non_exact',
            false,
            description: $requirement->quantity_description,
            unitLabel: $hasStock ? 'Available; excluded from score' : 'Excluded from score',
            nonExactStatus: $requirement->non_exact_status?->value,
        );
    }

    /**
     * @param  numeric-string  $quantityCoverage
     * @param  array{full: int, partial: int, missing: int, incompatible: int}  $counts
     * @return numeric-string
     */
    private function calculateScore(string $quantityCoverage, array $counts, int $exactCount): string
    {
        $score = bcmul($this->weight('quantity_coverage'), $quantityCoverage, $this->scale());
        foreach (['full', 'partial', 'missing', 'incompatible'] as $factor) {
            $proportion = bcdiv((string) $counts[$factor], (string) $exactCount, $this->scale());
            $score = bcadd($score, bcmul($this->weight($factor), $proportion, $this->scale()), $this->scale());
        }

        // Clamp the configurable weighted formula so tuning cannot escape the UI's score range.
        $minimum = $this->configuredDecimal('recommendations.minimum_score');
        $maximum = $this->configuredDecimal('recommendations.maximum_score');
        $score = bccomp($score, $minimum, $this->scale()) < 0
            ? $minimum
            : $score;

        return bccomp($score, $maximum, $this->scale()) > 0
            ? $maximum
            : $score;
    }

    private function unitLabel(Quantity $quantity): string
    {
        return match ($quantity->measurementGroup) {
            MeasurementGroup::Mass => 'g',
            MeasurementGroup::Volume => 'ml',
            MeasurementGroup::Count => $quantity->unit->value,
            MeasurementGroup::Package => 'packages',
        };
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }

    /** @return numeric-string */
    private function weight(string $factor): string
    {
        return $this->configuredDecimal('recommendations.weights.'.$factor);
    }

    /** @return numeric-string */
    private function configuredDecimal(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || ! is_numeric($value)) {
            throw new \LogicException("Recommendation configuration [{$key}] must be a decimal string.");
        }

        return $value;
    }
}
