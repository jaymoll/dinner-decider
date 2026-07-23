<?php

namespace Tests\Feature;

use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\Groceries\ToggleGroceryItemChecked;
use App\Actions\Pantry\AddPantryStock;
use App\Enums\GroceryItemSource;
use App\Enums\PlannedDinnerStatus;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\GroceryItem;
use App\Models\Ingredient;
use App\Models\IngredientReservation;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Queries\GetPantryAwareRecommendations;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MvpJourneyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_connected_dinner_workflow_preserves_authoritative_state(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create(['name' => 'Potatoes']);
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Potato dinner', 'default_servings' => 4]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create([
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => '150',
            'entered_unit' => UnitCode::Gram,
            'normalized_amount' => '150',
            'compatibility_key' => 'mass',
        ]);

        $pantryEntry = app(AddPantryStock::class)->handle($user, [
            'ingredient_id' => $ingredient->id,
            'amount' => '100',
            'unit' => UnitCode::Gram->value,
        ]);

        $recommendation = app(GetPantryAwareRecommendations::class)->get($user)->sole();
        $this->assertSame('partial', $recommendation->matches[0]->status);
        $this->assertSame('50.000000', $recommendation->matches[0]->missingAmount);

        $dinner = app(PlanDinner::class)->handle($user, $recipe, '4', '2026-07-27');
        $requirement = $dinner->requirements()->sole();
        $reservation = $requirement->reservations()->sole();
        $this->assertSame('100.000000', $reservation->normalized_amount);

        $groceryItem = GroceryItem::query()
            ->where('source', GroceryItemSource::Generated)
            ->where('ingredient_id', $ingredient->id)
            ->sole();
        $this->assertSame('50.000000', $groceryItem->calculated_amount);
        $this->assertCount(1, $groceryItem->contributions);

        app(ToggleGroceryItemChecked::class)->handle($user, $groceryItem);
        $result = app(MarkDinnerCooked::class)->handle($user, $dinner);

        $this->assertTrue($result->cooked);
        $this->assertFalse($result->requiresConfirmation);
        $this->assertSame(PlannedDinnerStatus::Cooked, $dinner->refresh()->status);
        $this->assertSame('0.000000', $pantryEntry->refresh()->total_normalized_amount);
        $this->assertFalse(IngredientReservation::query()->whereKey($reservation->id)->exists());
        $this->assertNull($requirement->refresh()->unresolved_at_cooking);
        $this->assertFalse(GroceryItem::query()->whereKey($groceryItem->id)->exists());

        $snapshot = $dinner->refresh();
        $this->assertSame('Potato dinner', $snapshot->recipe_name);
        $this->assertSame('2026-07-27', $snapshot->planned_date?->format('Y-m-d'));
        $this->assertSame('150.000000', $snapshot->requirements()->sole()->scaled_amount);
    }
}
