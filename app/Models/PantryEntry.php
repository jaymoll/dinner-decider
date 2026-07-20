<?php

namespace App\Models;

use App\Enums\UnitCode;
use App\ValueObjects\Quantity;
use Database\Factories\PantryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $ingredient_id
 * @property int|null $ingredient_package_id
 * @property UnitCode|null $display_unit
 * @property numeric-string $total_normalized_amount
 * @property string $compatibility_key
 * @property string $merge_key
 * @property numeric-string|null $reserved_normalized_amount
 * @property-read User $user
 * @property-read Ingredient $ingredient
 * @property-read IngredientPackage|null $ingredientPackage
 * @property-read Collection<int, IngredientReservation> $reservations
 */
#[Fillable(['user_id', 'ingredient_id', 'ingredient_package_id', 'display_unit', 'total_normalized_amount', 'compatibility_key', 'merge_key'])]

class PantryEntry extends Model
{
    /** @use HasFactory<PantryEntryFactory> */
    use HasFactory;

    public static function mergeKeyFor(Quantity $quantity): string
    {
        return $quantity->ingredientPackageId === null
            ? 'direct:'.(string) $quantity->compatibilityKey
            : 'package:'.$quantity->ingredientPackageId;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'display_unit' => UnitCode::class,
            'total_normalized_amount' => 'decimal:6',
        ];
    }
}
