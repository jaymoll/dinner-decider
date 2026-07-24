<?php

namespace App\Models;

use Database\Factories\GroceryListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property int $dinner_plan_id */
#[Fillable(['dinner_plan_id', 'regenerated_at'])]
class GroceryList extends Model
{
    /** @use HasFactory<GroceryListFactory> */
    use HasFactory;

    /** @return BelongsTo<DinnerPlan, $this> */
    public function dinnerPlan(): BelongsTo
    {
        return $this->belongsTo(DinnerPlan::class);
    }

    /** @return HasMany<GroceryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GroceryItem::class);
    }

    protected function casts(): array
    {
        return ['regenerated_at' => 'immutable_datetime'];
    }
}
