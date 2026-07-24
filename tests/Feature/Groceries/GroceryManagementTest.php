<?php

namespace Tests\Feature\Groceries;

use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\ReconcilePlanReservations;
use App\Actions\Groceries\AddManualGroceryItem;
use App\Actions\Groceries\ClearCompletedGroceries;
use App\Actions\Groceries\EditGeneratedGroceryQuantity;
use App\Actions\Groceries\ToggleGroceryItemChecked;
use App\Enums\GroceryItemSource;
use App\Enums\UnitCode;
use App\Models\DinnerPlan;
use App\Models\GroceryItem;
use App\Models\GroceryList;
use App\Models\Ingredient;
use App\Models\PlannedDinner;
use App\Models\PlannedDinnerRequirement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

        $requirement->update(['scaled_amount' => '750', 'missing_amount' => '750']);
        app(ReconcilePlanReservations::class)->handle($plan);
        $generated->refresh();
        $this->assertNull($generated->checked_at);
        $this->assertSame('500.000000', $generated->previous_calculated_amount);
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

    /** @return array{User, DinnerPlan, PlannedDinnerRequirement} */
    private function plannedRequirement(string $amount): array
    {
        $user = User::factory()->create();
        $plan = DinnerPlan::factory()->for($user)->create();
        $ingredient = Ingredient::factory()->for($user)->create(['category' => 'Dry goods']);
        $dinner = PlannedDinner::factory()->for($plan)->create();
        $requirement = PlannedDinnerRequirement::factory()->for($dinner)->for($ingredient)->create([
            'ingredient_name' => 'Rice', 'scaled_amount' => $amount, 'missing_amount' => $amount, 'compatibility_key' => 'mass',
        ]);

        return [$user, $plan, $requirement];
    }
}
