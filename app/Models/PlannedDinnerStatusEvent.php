<?php

namespace App\Models;

use App\Enums\PlannedDinnerStatus;
use App\Enums\PlannedDinnerStatusEventType;
use Database\Factories\PlannedDinnerStatusEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $planned_dinner_id
 * @property PlannedDinnerStatusEventType $event_type
 * @property PlannedDinnerStatus|null $from_status
 * @property PlannedDinnerStatus $to_status
 * @property Carbon $occurred_at
 * @property int|null $actor_user_id
 * @property bool $is_reconstructed
 */
#[Fillable(['planned_dinner_id', 'event_type', 'from_status', 'to_status', 'occurred_at', 'actor_user_id', 'is_reconstructed'])]

class PlannedDinnerStatusEvent extends Model
{
    /** @use HasFactory<PlannedDinnerStatusEventFactory> */
    use HasFactory;

    /** @return BelongsTo<PlannedDinner, $this> */
    public function plannedDinner(): BelongsTo
    {
        return $this->belongsTo(PlannedDinner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_type' => PlannedDinnerStatusEventType::class,
            'from_status' => PlannedDinnerStatus::class,
            'to_status' => PlannedDinnerStatus::class,
            'occurred_at' => 'immutable_datetime',
            'is_reconstructed' => 'boolean',
        ];
    }
}
