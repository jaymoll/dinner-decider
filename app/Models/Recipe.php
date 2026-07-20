<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property int $default_servings
 * @property int|null $preparation_minutes
 * @property int|null $cooking_minutes
 * @property string|null $difficulty
 * @property string|null $cuisine
 * @property string|null $meal_type
 * @property string|null $notes
 * @property string|null $image_path
 * @property string|null $source_url
 * @property Carbon|null $archived_at
 * @property-read User $user
 * @property-read Collection<int, RecipeIngredient> $ingredients
 * @property-read Collection<int, RecipeStep> $steps
 * @property-read Collection<int, RecipeCategory> $categories
 * @property-read Collection<int, Tag> $tags
 */
#[Fillable(['user_id', 'name', 'description', 'default_servings', 'preparation_minutes', 'cooking_minutes', 'difficulty', 'cuisine', 'meal_type', 'notes', 'image_path', 'source_url', 'archived_at'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    /** @return HasMany<RecipeStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    /** @return BelongsToMany<RecipeCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(RecipeCategory::class, 'category_recipe');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @param  Builder<Recipe>  $query
     * @return Builder<Recipe>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Recipe>  $query
     * @return Builder<Recipe>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * @param  Builder<Recipe>  $query
     * @return Builder<Recipe>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_servings' => 'integer',
            'preparation_minutes' => 'integer',
            'cooking_minutes' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
