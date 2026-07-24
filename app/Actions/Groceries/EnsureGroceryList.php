<?php

namespace App\Actions\Groceries;

use App\Models\DinnerPlan;
use App\Models\GroceryList;
use Illuminate\Support\Facades\DB;

final class EnsureGroceryList
{
    public function handle(DinnerPlan $dinnerPlan): GroceryList
    {
        DB::table('grocery_lists')->insertOrIgnore([
            'dinner_plan_id' => $dinnerPlan->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return GroceryList::query()->whereBelongsTo($dinnerPlan)->firstOrFail();
    }
}
