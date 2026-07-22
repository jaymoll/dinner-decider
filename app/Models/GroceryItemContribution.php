<?php

namespace App\Models;

use Database\Factories\GroceryItemContributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $grocery_item_id
 * @property int $planned_dinner_requirement_id
 * @property numeric-string|null $normalized_amount
 * @property-read GroceryItem $groceryItem
 * @property-read PlannedDinnerRequirement $requirement
 */
#[Fillable(['grocery_item_id', 'planned_dinner_requirement_id', 'normalized_amount'])]
class GroceryItemContribution extends Model
{
    /** @use HasFactory<GroceryItemContributionFactory> */
    use HasFactory;

    /** @return BelongsTo<GroceryItem, $this> */
    public function groceryItem(): BelongsTo
    {
        return $this->belongsTo(GroceryItem::class);
    }

    /** @return BelongsTo<PlannedDinnerRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PlannedDinnerRequirement::class, 'planned_dinner_requirement_id');
    }

    protected function casts(): array
    {
        return ['normalized_amount' => 'decimal:6'];
    }
}
