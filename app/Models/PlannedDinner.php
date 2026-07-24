<?php

namespace App\Models;

use App\Enums\PlannedDinnerStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedDinnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dinner_plan_id
 * @property int|null $recipe_id
 * @property string $recipe_name
 * @property string|null $recipe_description
 * @property numeric-string $source_servings
 * @property numeric-string $servings
 * @property list<array{position: int, instruction: string}> $recipe_steps
 * @property list<string> $recipe_categories
 * @property list<string> $recipe_tags
 * @property CarbonImmutable|null $planned_date
 * @property PlannedDinnerStatus $status
 * @property int $position
 * @property Carbon|null $cooked_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $restored_at
 * @property-read DinnerPlan $dinnerPlan
 */
#[Fillable(['dinner_plan_id', 'recipe_id', 'recipe_name', 'recipe_description', 'source_servings', 'servings', 'preparation_minutes', 'cooking_minutes', 'difficulty', 'cuisine', 'meal_type', 'notes', 'image_path', 'source_url', 'recipe_steps', 'recipe_categories', 'recipe_tags', 'planned_date', 'status', 'position', 'cooked_at', 'cancelled_at', 'restored_at'])]
class PlannedDinner extends Model
{
    /** @use HasFactory<PlannedDinnerFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => PlannedDinnerStatus::Planned, 'position' => 1];

    /** @return BelongsTo<DinnerPlan, $this> */
    public function dinnerPlan(): BelongsTo
    {
        return $this->belongsTo(DinnerPlan::class);
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return HasMany<PlannedDinnerRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(PlannedDinnerRequirement::class)->orderBy('position');
    }

    /**
     * @param  Builder<PlannedDinner>  $query
     * @return Builder<PlannedDinner>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PlannedDinnerStatus::Planned);
    }

    /**
     * @param  Builder<PlannedDinner>  $query
     * @return Builder<PlannedDinner>
     */
    public function scopeHistory(Builder $query): Builder
    {
        return $query->whereIn('status', [PlannedDinnerStatus::Cooked, PlannedDinnerStatus::Cancelled]);
    }

    /**
     * @param  Builder<PlannedDinner>  $query
     * @return Builder<PlannedDinner>
     */
    public function scopePriorityOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN planned_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('planned_date')
            ->orderBy('position')
            ->oldest('created_at')
            ->oldest('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_servings' => 'decimal:6',
            'servings' => 'decimal:6',
            'recipe_steps' => 'array',
            'recipe_categories' => 'array',
            'recipe_tags' => 'array',
            'planned_date' => 'immutable_date',
            'status' => PlannedDinnerStatus::class,
            'position' => 'integer',
            'cooked_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'restored_at' => 'immutable_datetime',
        ];
    }
}
