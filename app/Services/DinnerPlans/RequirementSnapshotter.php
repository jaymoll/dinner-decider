<?php

namespace App\Services\DinnerPlans;

use App\Data\Measurements\QuantityInput;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Models\PlannedDinnerRequirement;
use App\Models\RecipeIngredient;
use App\Services\Measurements\UnitConverter;
use App\Services\Recipes\RecipeScaler;

/**
 * Freezes recipe requirements so planned dinner history survives later catalogue changes.
 */
final readonly class RequirementSnapshotter
{
    public function __construct(private UnitConverter $converter, private RecipeScaler $scaler) {}

    /**
     * @param  numeric-string  $servings
     * @param  numeric-string  $sourceServings
     * @return array<string, mixed>
     */
    public function fromRecipeIngredient(RecipeIngredient $line, string $servings, string $sourceServings): array
    {
        $line->loadMissing(['ingredient', 'ingredientPackage']);
        $package = $line->ingredientPackage;
        $scaledAmount = null;

        if ($line->quantity_type === QuantityType::Exact) {
            $quantity = $package === null
                ? $this->converter->normalize(new QuantityInput((string) $line->entered_amount, $line->entered_unit, $line->ingredient_id))
                : $this->converter->normalize(new QuantityInput(
                    amount: (string) $line->entered_amount,
                    ingredientId: $line->ingredient_id,
                    ingredientPackageId: $package->id,
                    packageContentAmount: $package->content_amount,
                    packageContentUnit: $package->content_unit,
                ));
            $scaledAmount = $this->scaler->scaleQuantity($quantity, $servings, $sourceServings)->normalizedAmount;
        }

        // Copy both presentation context and normalized source truth; future serving changes must
        // scale this immutable snapshot rather than whatever the recipe contains at that time.
        return [
            'ingredient_id' => $line->ingredient_id,
            'ingredient_package_id' => $line->ingredient_package_id,
            'ingredient_name' => $line->ingredient->name,
            'package_label' => $package?->label,
            'package_type' => $package?->package_type,
            'package_content_amount' => $package?->content_amount,
            'package_content_unit' => $package?->content_unit,
            'package_normalized_content_amount' => $package?->normalized_content_amount,
            'quantity_type' => $line->quantity_type,
            'source_entered_amount' => $line->entered_amount,
            'source_entered_unit' => $line->entered_unit,
            'source_normalized_amount' => $line->normalized_amount,
            'scaled_amount' => $scaledAmount,
            'compatibility_key' => $line->compatibility_key,
            'quantity_description' => $line->quantity_description,
            'non_exact_status' => $line->non_exact_status,
            'coverage' => $line->quantity_type === QuantityType::Exact ? RequirementCoverage::Missing : RequirementCoverage::NonExact,
            'missing_amount' => $scaledAmount,
            'position' => $line->position,
        ];
    }

    /**
     * @param  numeric-string  $servings
     * @param  numeric-string  $sourceServings
     * @return numeric-string|null
     */
    public function scaledAmount(PlannedDinnerRequirement $requirement, string $servings, string $sourceServings): ?string
    {
        if ($requirement->quantity_type === QuantityType::NonExact) {
            return null;
        }

        // Reconstruct from snapshotted package content so package edits cannot rewrite history.
        $quantity = $requirement->ingredient_package_id === null
            ? $this->converter->normalize(new QuantityInput(
                (string) $requirement->source_entered_amount,
                $requirement->source_entered_unit,
                $requirement->ingredient_id,
            ))
            : $this->converter->normalize(new QuantityInput(
                amount: (string) $requirement->source_entered_amount,
                ingredientId: $requirement->ingredient_id,
                ingredientPackageId: $requirement->ingredient_package_id,
                packageContentAmount: $requirement->package_content_amount,
                packageContentUnit: $requirement->package_content_unit,
            ));

        return $this->scaler->scaleQuantity($quantity, $servings, $sourceServings)->normalizedAmount;
    }
}
