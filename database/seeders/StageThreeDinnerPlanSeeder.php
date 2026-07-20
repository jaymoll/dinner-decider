<?php

namespace Database\Seeders;

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

class StageThreeDinnerPlanSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first();
        if ($user === null) {
            return;
        }

        $plan = app(EnsureDinnerPlan::class)->handle($user);
        $definitions = [
            ['Spaghetti Aglio e Olio', now()->addDay()->format('Y-m-d')],
            ['Creamy Chicken Pasta', now()->addDays(2)->format('Y-m-d')],
        ];

        foreach ($definitions as [$recipeName, $date]) {
            if ($plan->dinners()->where('recipe_name', $recipeName)->exists()) {
                continue;
            }

            $recipe = Recipe::query()->whereBelongsTo($user)->active()->where('name', $recipeName)->first();
            if ($recipe !== null) {
                app(PlanDinner::class)->handle($user, $recipe, (string) $recipe->default_servings, $date);
            }
        }

        app(ReconcilePlanReservations::class)->handle($plan);
    }
}
