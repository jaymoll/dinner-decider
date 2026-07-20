<?php

namespace App\Models;

use App\Enums\PackageType;
use App\Enums\UnitCode;
use Database\Factories\IngredientPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property PackageType $package_type
 * @property string $label
 * @property numeric-string|null $content_amount
 * @property UnitCode|null $content_unit
 * @property numeric-string|null $normalized_content_amount
 * @property-read Ingredient $ingredient
 */
#[Fillable(['ingredient_id', 'package_type', 'label', 'content_amount', 'content_unit', 'normalized_content_amount'])]
class IngredientPackage extends Model
{
    /** @use HasFactory<IngredientPackageFactory> */
    use HasFactory;

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /** @return HasMany<PantryEntry, $this> */
    public function pantryEntries(): HasMany
    {
        return $this->hasMany(PantryEntry::class);
    }

    public function hasKnownContents(): bool
    {
        return $this->content_amount !== null && $this->content_unit !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'package_type' => PackageType::class,
            'content_amount' => 'decimal:6',
            'content_unit' => UnitCode::class,
            'normalized_content_amount' => 'decimal:6',
        ];
    }
}
