<?php

namespace App\Actions\Groceries;

use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ToggleGroceryItemChecked
{
    public function handle(User $user, GroceryItem $item): GroceryItem
    {
        Gate::forUser($user)->authorize('update', $item);
        $item->update(['checked_at' => $item->checked_at === null ? now() : null]);

        return $item->refresh();
    }
}
