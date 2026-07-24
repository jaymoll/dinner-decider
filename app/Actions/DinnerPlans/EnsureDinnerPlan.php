<?php

namespace App\Actions\DinnerPlans;

use App\Models\DinnerPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EnsureDinnerPlan
{
    public function handle(User $user): DinnerPlan
    {
        Gate::forUser($user)->authorize('create', DinnerPlan::class);

        DB::table('dinner_plans')->insertOrIgnore([
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DinnerPlan::query()->whereBelongsTo($user)->firstOrFail();
    }
}
