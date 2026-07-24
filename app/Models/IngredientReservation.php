<?php

namespace App\Models;

use Database\Factories\IngredientReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $planned_dinner_requirement_id
 * @property int $pantry_entry_id
 * @property numeric-string $normalized_amount
 * @property-read PlannedDinnerRequirement $requirement
 * @property-read PantryEntry $pantryEntry
 */
#[Fillable(['planned_dinner_requirement_id', 'pantry_entry_id', 'normalized_amount'])]
class IngredientReservation extends Model
{
    /** @use HasFactory<IngredientReservationFactory> */
    use HasFactory;

    /** @return BelongsTo<PlannedDinnerRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PlannedDinnerRequirement::class, 'planned_dinner_requirement_id');
    }

    /** @return BelongsTo<PantryEntry, $this> */
    public function pantryEntry(): BelongsTo
    {
        return $this->belongsTo(PantryEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['normalized_amount' => 'decimal:6'];
    }
}
