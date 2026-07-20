<?php

namespace App\Models;

use Database\Factories\IngredientAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property string $name
 * @property string $normalized_name
 * @property-read Ingredient $ingredient
 */
#[Fillable(['ingredient_id', 'name', 'normalized_name'])]
class IngredientAlias extends Model
{
    /** @use HasFactory<IngredientAliasFactory> */
    use HasFactory;

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
