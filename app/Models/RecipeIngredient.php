<?php

namespace App\Models;

use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use Database\Factories\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $recipe_id
 * @property int $ingredient_id
 * @property int|null $ingredient_package_id
 * @property QuantityType $quantity_type
 * @property string|null $entered_amount
 * @property UnitCode|null $entered_unit
 * @property string|null $normalized_amount
 * @property string|null $compatibility_key
 * @property string|null $quantity_description
 * @property NonExactStatus|null $non_exact_status
 * @property int $position
 * @property-read Recipe $recipe
 * @property-read Ingredient $ingredient
 * @property-read IngredientPackage|null $ingredientPackage
 */
#[Fillable(['recipe_id', 'ingredient_id', 'ingredient_package_id', 'quantity_type', 'entered_amount', 'entered_unit', 'normalized_amount', 'compatibility_key', 'quantity_description', 'non_exact_status', 'position'])]
class RecipeIngredient extends Model
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use HasFactory;

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
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

    public function isExact(): bool
    {
        return $this->quantity_type === QuantityType::Exact;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_type' => QuantityType::class,
            'entered_amount' => 'decimal:6',
            'entered_unit' => UnitCode::class,
            'normalized_amount' => 'decimal:6',
            'non_exact_status' => NonExactStatus::class,
            'position' => 'integer',
        ];
    }
}
