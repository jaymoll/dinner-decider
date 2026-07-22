<?php

namespace App\Actions\Groceries;

use App\Enums\GroceryItemSource;
use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class EditGeneratedGroceryQuantity
{
    /** @param numeric-string $amount */
    public function handle(User $user, GroceryItem $item, string $amount, ?UnitCode $unit): GroceryItem
    {
        Gate::forUser($user)->authorize('update', $item);
        if ($item->source !== GroceryItemSource::Generated || $item->calculated_amount === null
            || ! preg_match('/^\d+(?:\.\d+)?$/', $amount) || bccomp($amount, '0', 6) <= 0) {
            throw new InvalidArgumentException('A positive override is required for a generated quantity.');
        }
        if (! $this->compatible($item->calculated_unit, $unit)) {
            throw new InvalidArgumentException('The override unit is incompatible with the generated quantity.');
        }
        $item->update(['override_amount' => $amount, 'override_unit' => $unit, 'is_manually_adjusted' => true]);

        return $item->refresh();
    }

    private function compatible(?UnitCode $calculated, ?UnitCode $override): bool
    {
        if ($calculated === null || $override === null) {
            return $calculated === $override;
        }

        return $calculated->measurementGroup() === $override->measurementGroup()
            && ($calculated->measurementGroup() !== MeasurementGroup::Count || $calculated === $override);
    }
}
