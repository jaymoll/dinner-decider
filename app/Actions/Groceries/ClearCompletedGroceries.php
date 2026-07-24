<?php

namespace App\Actions\Groceries;

use App\Models\GroceryItem;
use App\Models\GroceryList;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ClearCompletedGroceries
{
    public function handle(User $user, GroceryList $list): int
    {
        Gate::forUser($user)->authorize('update', $list);

        return GroceryItem::query()->whereBelongsTo($list)->whereNotNull('checked_at')->delete();
    }
}
