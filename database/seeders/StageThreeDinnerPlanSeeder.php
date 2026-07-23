<?php

namespace Database\Seeders;

use App\Actions\DinnerPlans\CancelDinner;
use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Actions\Groceries\AddManualGroceryItem;
use App\Actions\Groceries\EditGeneratedGroceryQuantity;
use App\Actions\Groceries\EnsureGroceryList;
use App\Actions\Groceries\ToggleGroceryItemChecked;
use App\Enums\GroceryCategory;
use App\Enums\GroceryItemSource;
use App\Enums\PlannedDinnerStatus;
use App\Models\GroceryItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Builds idempotent action-derived planning and grocery states for the local demo account.
 */
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
            // Recipe name plus existing dinner state makes repeated local seeding non-destructive.
            if ($plan->dinners()->where('recipe_name', $recipeName)->exists()) {
                continue;
            }

            $recipe = Recipe::query()->whereBelongsTo($user)->active()->where('name', $recipeName)->first();
            if ($recipe !== null) {
                app(PlanDinner::class)->handle($user, $recipe, (string) $recipe->default_servings, $date);
            }
        }

        app(ReconcilePlanReservations::class)->handle($plan);

        if (! $plan->dinners()->where('recipe_name', 'Butter Toast')->where('status', PlannedDinnerStatus::Cancelled)->exists()) {
            $recipe = Recipe::query()->whereBelongsTo($user)->where('name', 'Butter Toast')->sole();
            $dinner = app(PlanDinner::class)->handle($user, $recipe, '1', now()->subDay()->format('Y-m-d'));
            app(CancelDinner::class)->handle($user, $dinner);
        }

        if (! $plan->dinners()->where('recipe_name', 'Spinach Omelette')->where('status', PlannedDinnerStatus::Cooked)->exists()) {
            $recipe = Recipe::query()->whereBelongsTo($user)->where('name', 'Spinach Omelette')->sole();
            $dinner = app(PlanDinner::class)->handle($user, $recipe, '2', now()->subDays(2)->format('Y-m-d'));
            $result = app(MarkDinnerCooked::class)->handle($user, $dinner);
            if ($result->requiresConfirmation) {
                // Exercise the same two-step confirmation path as the UI to create realistic history.
                app(MarkDinnerCooked::class)->handle($user, $dinner, $result->fingerprint);
            }
        }

        $list = app(EnsureGroceryList::class)->handle($plan);
        if (! $list->items()->where('source', GroceryItemSource::Manual)->where('name', 'Paper towels')->exists()) {
            app(AddManualGroceryItem::class)->handle($user, $list, [
                'name' => 'Paper towels',
                'quantity_description' => '1 roll',
                'category' => GroceryCategory::Household->value,
            ]);
        }

        $generatedItems = GroceryItem::query()->whereBelongsTo($list)->where('source', GroceryItemSource::Generated)->oldest('id')->get();
        $checkedItem = $generatedItems->first();
        if ($checkedItem !== null && $checkedItem->checked_at === null) {
            app(ToggleGroceryItemChecked::class)->handle($user, $checkedItem);
        }

        $adjustedItem = $generatedItems->first(fn (GroceryItem $item): bool => $item->calculated_amount !== null && ! $item->is_manually_adjusted);
        if ($adjustedItem !== null) {
            app(EditGeneratedGroceryQuantity::class)->handle(
                $user,
                $adjustedItem,
                bcadd($adjustedItem->calculated_amount, '1', (int) config('measurements.calculation_scale')),
                $adjustedItem->calculated_unit,
            );
        }
    }
}
