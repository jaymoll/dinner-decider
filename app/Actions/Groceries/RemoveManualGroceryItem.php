<?php

namespace App\Actions\Groceries;

use App\Enums\GroceryItemSource;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RemoveManualGroceryItem
{
    public function handle(User $user, GroceryItem $item): void
    {
        Gate::forUser($user)->authorize('delete', $item);
        if ($item->source !== GroceryItemSource::Manual) {
            throw new InvalidArgumentException('Generated grocery items are removed only by regeneration.');
        }
        $item->delete();
    }
}
