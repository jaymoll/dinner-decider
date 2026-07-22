<?php

namespace App\Models;

use App\Enums\NonExactStatus;
use App\Enums\PackageType;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Enums\UnitCode;
use Database\Factories\PlannedDinnerRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $planned_dinner_id
 * @property int|null $ingredient_id
 * @property int|null $ingredient_package_id
 * @property string $ingredient_name
 * @property string|null $package_label
 * @property PackageType|null $package_type
 * @property numeric-string|null $package_content_amount
 * @property UnitCode|null $package_content_unit
 * @property numeric-string|null $package_normalized_content_amount
 * @property QuantityType $quantity_type
 * @property numeric-string|null $source_entered_amount
 * @property UnitCode|null $source_entered_unit
 * @property numeric-string|null $source_normalized_amount
 * @property numeric-string|null $scaled_amount
 * @property string|null $compatibility_key
 * @property string|null $quantity_description
 * @property NonExactStatus|null $non_exact_status
 * @property RequirementCoverage $coverage
 * @property numeric-string|null $missing_amount
 * @property array<string, mixed>|null $unresolved_at_cooking
 * @property int $position
 * @property numeric-string|null $reservations_sum_normalized_amount
 * @property-read PlannedDinner $plannedDinner
 * @property-read Collection<int, IngredientReservation> $reservations
 * @property-read Collection<int, GroceryItemContribution> $groceryContributions
 */
#[Fillable(['planned_dinner_id', 'ingredient_id', 'ingredient_package_id', 'ingredient_name', 'package_label', 'package_type', 'package_content_amount', 'package_content_unit', 'package_normalized_content_amount', 'quantity_type', 'source_entered_amount', 'source_entered_unit', 'source_normalized_amount', 'scaled_amount', 'compatibility_key', 'quantity_description', 'non_exact_status', 'coverage', 'missing_amount', 'unresolved_at_cooking', 'position'])]
class PlannedDinnerRequirement extends Model
{
    /** @use HasFactory<PlannedDinnerRequirementFactory> */
    use HasFactory;

    /** @return BelongsTo<PlannedDinner, $this> */
    public function plannedDinner(): BelongsTo
    {
        return $this->belongsTo(PlannedDinner::class);
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /** @return BelongsTo<IngredientPackage, $this> */
    public function ingredientPackage(): BelongsTo
    {
        return $this->belongsTo(IngredientPackage::class);
    }

    /** @return HasMany<IngredientReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(IngredientReservation::class);
    }

    /** @return HasMany<GroceryItemContribution, $this> */
    public function groceryContributions(): HasMany
    {
        return $this->hasMany(GroceryItemContribution::class);
    }

    /** @return numeric-string */
    public function reservedAmount(): string
    {
        return (string) ($this->reservations_sum_normalized_amount ?? $this->reservations()->sum('normalized_amount'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_type' => QuantityType::class,
            'package_type' => PackageType::class,
            'package_content_amount' => 'decimal:6',
            'package_content_unit' => UnitCode::class,
            'package_normalized_content_amount' => 'decimal:6',
            'source_entered_amount' => 'decimal:6',
            'source_entered_unit' => UnitCode::class,
            'source_normalized_amount' => 'decimal:6',
            'scaled_amount' => 'decimal:6',
            'non_exact_status' => NonExactStatus::class,
            'coverage' => RequirementCoverage::class,
            'missing_amount' => 'decimal:6',
            'unresolved_at_cooking' => 'array',
            'position' => 'integer',
        ];
    }
}
