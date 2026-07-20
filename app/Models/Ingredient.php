<?php

namespace App\Models;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $category
 * @property MeasurementGroup $preferred_measurement_group
 * @property UnitCode $preferred_unit
 * @property bool $is_staple
 * @property bool $is_currently_available
 * @property Carbon|null $archived_at
 * @property-read User $user
 * @property-read Collection<int, IngredientAlias> $aliases
 * @property-read Collection<int, IngredientPackage> $packages
 */
#[Fillable(['user_id', 'name', 'normalized_name', 'category', 'preferred_measurement_group', 'preferred_unit', 'is_staple', 'is_currently_available', 'archived_at'])]
class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['is_staple' => false, 'is_currently_available' => true];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<IngredientAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(IngredientAlias::class)->oldest('id');
    }

    /** @return HasMany<IngredientPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(IngredientPackage::class)->oldest('id');
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * @param  Builder<Ingredient>  $query
     * @return Builder<Ingredient>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Ingredient>  $query
     * @return Builder<Ingredient>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'preferred_measurement_group' => MeasurementGroup::class,
            'preferred_unit' => UnitCode::class,
            'is_staple' => 'boolean',
            'is_currently_available' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
