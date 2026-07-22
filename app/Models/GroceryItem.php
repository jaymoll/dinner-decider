<?php

namespace App\Models;

use App\Enums\GroceryCategory;
use App\Enums\GroceryItemSource;
use App\Enums\UnitCode;
use Database\Factories\GroceryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $grocery_list_id
 * @property int|null $ingredient_id
 * @property int|null $ingredient_package_id
 * @property GroceryItemSource $source
 * @property string|null $generation_key
 * @property string $name
 * @property numeric-string|null $calculated_amount
 * @property UnitCode|null $calculated_unit
 * @property string|null $quantity_description
 * @property string|null $package_label
 * @property numeric-string|null $override_amount
 * @property UnitCode|null $override_unit
 * @property bool $is_manually_adjusted
 * @property GroceryCategory $category
 * @property Carbon|null $checked_at
 * @property numeric-string|null $previous_calculated_amount
 * @property Carbon|null $quantity_increased_at
 * @property-read GroceryList $groceryList
 * @property-read Collection<int, GroceryItemContribution> $contributions
 */
#[Fillable(['grocery_list_id', 'ingredient_id', 'ingredient_package_id', 'source', 'generation_key', 'name', 'calculated_amount', 'calculated_unit', 'quantity_description', 'package_label', 'override_amount', 'override_unit', 'is_manually_adjusted', 'category', 'checked_at', 'previous_calculated_amount', 'quantity_increased_at'])]
class GroceryItem extends Model
{
    /** @use HasFactory<GroceryItemFactory> */
    use HasFactory;

    protected $attributes = ['is_manually_adjusted' => false, 'category' => GroceryCategory::Other];

    /** @return BelongsTo<GroceryList, $this> */
    public function groceryList(): BelongsTo
    {
        return $this->belongsTo(GroceryList::class);
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

    /** @return HasMany<GroceryItemContribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(GroceryItemContribution::class);
    }

    protected function casts(): array
    {
        return [
            'source' => GroceryItemSource::class,
            'calculated_amount' => 'decimal:6',
            'calculated_unit' => UnitCode::class,
            'override_amount' => 'decimal:6',
            'override_unit' => UnitCode::class,
            'is_manually_adjusted' => 'boolean',
            'category' => GroceryCategory::class,
            'checked_at' => 'immutable_datetime',
            'previous_calculated_amount' => 'decimal:6',
            'quantity_increased_at' => 'immutable_datetime',
        ];
    }
}
