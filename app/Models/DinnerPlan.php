<?php

namespace App\Models;

use Database\Factories\DinnerPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property-read User $user
 */
#[Fillable(['user_id'])]
class DinnerPlan extends Model
{
    /** @use HasFactory<DinnerPlanFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PlannedDinner, $this> */
    public function dinners(): HasMany
    {
        return $this->hasMany(PlannedDinner::class);
    }

    /** @return HasMany<PlannedDinner, $this> */
    public function activeDinners(): HasMany
    {
        return $this->dinners()->active()->priorityOrder();
    }

    /** @return HasMany<PlannedDinner, $this> */
    public function history(): HasMany
    {
        return $this->dinners()->history()->latest('updated_at');
    }

    /** @return HasOne<GroceryList, $this> */
    public function groceryList(): HasOne
    {
        return $this->hasOne(GroceryList::class);
    }
}
