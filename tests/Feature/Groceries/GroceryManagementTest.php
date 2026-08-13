<?php

namespace Tests\Feature\Groceries;

use App\Actions\DinnerPlans\ChangePlannedDinnerServings;
use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Actions\Groceries\AddManualGroceryItem;
use App\Actions\Groceries\ClearCompletedGroceries;
use App\Actions\Groceries\EditGeneratedGroceryQuantity;
use App\Actions\Groceries\RemoveManualGroceryItem;
use App\Actions\Groceries\ToggleGroceryItemChecked;
use App\Actions\Groceries\UpdateManualGroceryItem;
use App\Actions\Pantry\AddPantryStock;
use App\Actions\Pantry\RemovePantryEntry;
use App\Actions\Pantry\UpdatePantryEntry;
use App\Enums\GroceryItemSource;
use App\Enums\UnitCode;
use App\Models\DinnerPlan;
use App\Models\GroceryItem;
use App\Models\GroceryList;
use App\Models\Ingredient;
use App\Models\PlannedDinnerRequirement;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class GroceryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regeneration_preserves_manual_items_and_checked_state_until_quantity_increases(): void
    {
        [$user, $plan, $requirement] = $this->plannedRequirement('500');
        app(ReconcilePlanReservations::class)->handle($plan);
        $list = GroceryList::query()->whereBelongsTo($plan)->firstOrFail();
        $generated = GroceryItem::query()->whereBelongsTo($list)->where('source', GroceryItemSource::Generated)->firstOrFail();
        app(ToggleGroceryItemChecked::class)->handle($user, $generated);
        app(AddManualGroceryItem::class)->handle($user, $list, ['name' => 'Soap', 'category' => 'household']);

        app(ReconcilePlanReservations::class)->handle($plan);
        $this->assertNotNull($generated->refresh()->checked_at);
        $this->assertTrue(GroceryItem::query()->whereBelongsTo($list)->where('name', 'Soap')->exists());

        app(ChangePlannedDinnerServings::class)->handle($user, $requirement->plannedDinner, '2');
        $this->assertNotNull($generated->refresh()->checked_at);
        $this->assertSame('250.000000', $generated->calculated_amount);

        app(ChangePlannedDinnerServings::class)->handle($user, $requirement->plannedDinner, '6');
        $generated->refresh();
        $this->assertNull($generated->checked_at);
        $this->assertSame('250.000000', $generated->previous_calculated_amount);
        $this->assertSame('750.000000', $generated->calculated_amount);
    }

    public function test_override_clears_on_regeneration_and_completed_rows_have_no_history(): void
    {
        [$user, $plan] = $this->plannedRequirement('100');
        app(ReconcilePlanReservations::class)->handle($plan);
        $list = GroceryList::query()->whereBelongsTo($plan)->firstOrFail();
        $item = GroceryItem::query()->whereBelongsTo($list)->firstOrFail();
        app(EditGeneratedGroceryQuantity::class)->handle($user, $item, '1', UnitCode::Kilogram);
        app(ReconcilePlanReservations::class)->handle($plan);
        $this->assertFalse($item->refresh()->is_manually_adjusted);
        app(ToggleGroceryItemChecked::class)->handle($user, $item);

        $this->assertSame(1, app(ClearCompletedGroceries::class)->handle($user, $list));
        $this->assertDatabaseMissing('grocery_items', ['id' => $item->id]);
        $this->assertFalse(Schema::hasTable('grocery_histories'));
    }

    public function test_users_cannot_mutate_another_users_items(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = GroceryItem::factory()->for(GroceryList::factory()->for(DinnerPlan::factory()->for($owner)))->create();
        $this->expectException(AuthorizationException::class);
        app(ToggleGroceryItemChecked::class)->handle($other, $item);
    }

    public function test_checked_generated_contribution_resolves_cooking_confirmation(): void
    {
        [$user, $plan, $requirement] = $this->plannedRequirement('100');
        app(ReconcilePlanReservations::class)->handle($plan);
        $item = GroceryItem::query()->whereHas('groceryList', fn ($query) => $query->whereBelongsTo($plan))->firstOrFail();
        $cook = app(MarkDinnerCooked::class);

        $unresolved = $cook->handle($user, $requirement->plannedDinner);
        $this->assertTrue($unresolved->requiresConfirmation);

        app(ToggleGroceryItemChecked::class)->handle($user, $item);
        $result = $cook->handle($user, $requirement->plannedDinner);
        $this->assertTrue($result->cooked);
        $this->assertFalse($result->requiresConfirmation);
    }

    public function test_pantry_mutations_recalculate_generated_quantities(): void
    {
        [$user, $plan, $requirement] = $this->plannedRequirement('500');
        $ingredient = $requirement->ingredient;
        $generated = $plan->groceryList->items()->where('source', GroceryItemSource::Generated)->sole();
        $this->assertSame('500.000000', $generated->calculated_amount);

        $entry = app(AddPantryStock::class)->handle($user, [
            'ingredient_id' => $ingredient->id,
            'amount' => '100',
            'unit' => 'g',
            'ingredient_package_id' => null,
        ]);
        $this->assertSame('400.000000', $generated->refresh()->calculated_amount);

        app(UpdatePantryEntry::class)->handle($user, $entry, '200');
        $this->assertSame('300.000000', $generated->refresh()->calculated_amount);

        app(RemovePantryEntry::class)->handle($user, $entry, true);
        $this->assertSame('500.000000', $generated->refresh()->calculated_amount);
    }

    public function test_manual_items_support_their_full_lifecycle_and_generated_items_reject_it(): void
    {
        [$user, $plan] = $this->plannedRequirement('100');
        $list = $plan->groceryList;
        $generated = $list->items()->where('source', GroceryItemSource::Generated)->sole();
        $manual = app(AddManualGroceryItem::class)->handle($user, $list, [
            'name' => '  Dish   soap ',
            'quantity_description' => '  one bottle ',
            'category' => 'household',
        ]);

        app(ReconcilePlanReservations::class)->handle($plan);
        $this->assertModelExists($manual);

        app(UpdateManualGroceryItem::class)->handle($user, $manual, [
            'name' => 'Dishwasher tablets',
            'quantity_description' => '20 pack',
            'category' => 'household',
        ]);
        $this->assertSame('Dishwasher tablets', $manual->refresh()->name);

        foreach ([UpdateManualGroceryItem::class, RemoveManualGroceryItem::class] as $actionClass) {
            try {
                if ($actionClass === UpdateManualGroceryItem::class) {
                    app($actionClass)->handle($user, $generated, [
                        'name' => 'Changed',
                        'category' => 'other',
                    ]);
                } else {
                    app($actionClass)->handle($user, $generated);
                }
                $this->fail("{$actionClass} must reject a generated item.");
            } catch (InvalidArgumentException) {
                $this->assertModelExists($generated);
            }
        }

        app(RemoveManualGroceryItem::class)->handle($user, $manual);
        $this->assertModelMissing($manual);
    }

    /** @return array{User, DinnerPlan, PlannedDinnerRequirement} */
    private function plannedRequirement(string $amount): array
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create(['category' => 'Dry goods']);
        $recipe = Recipe::factory()->for($user)->create(['default_servings' => 4]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create([
            'entered_amount' => $amount,
            'normalized_amount' => $amount,
        ]);
        $dinner = app(PlanDinner::class)->handle($user, $recipe, '4');
        $plan = $dinner->dinnerPlan;
        $requirement = $dinner->requirements()->sole();

        return [$user, $plan, $requirement];
    }
}
