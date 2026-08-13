<?php

namespace Tests\Feature\DinnerPlans;

use App\Actions\DinnerPlans\CancelDinner;
use App\Actions\DinnerPlans\ChangePlannedDinnerServings;
use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\DinnerPlans\RestoreCancelledDinner;
use App\Actions\Pantry\UpdatePantryEntry;
use App\Enums\PlannedDinnerStatus;
use App\Enums\RequirementCoverage;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DinnerLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_serving_changes_rescale_from_source_and_cancel_restore_reconciles_current_stock(): void
    {
        [$user, $recipe, $entry] = $this->fixture('100', '200');
        $dinner = app(PlanDinner::class)->handle($user, $recipe, '4');

        app(ChangePlannedDinnerServings::class)->handle($user, $dinner, '2');
        app(ChangePlannedDinnerServings::class)->handle($user, $dinner, '8');
        $this->assertSame('200.000000', $dinner->requirements()->sole()->scaled_amount);

        app(CancelDinner::class)->handle($user, $dinner);
        $this->assertSame(PlannedDinnerStatus::Cancelled, $dinner->refresh()->status);
        $this->assertSame(0, $entry->reservations()->count());

        app(UpdatePantryEntry::class)->handle($user, $entry, '50');
        app(RestoreCancelledDinner::class)->handle($user, $dinner);
        $dinner->refresh();
        $requirement = $dinner->requirements()->sole();
        $this->assertSame(PlannedDinnerStatus::Planned, $dinner->status);
        $this->assertNull($dinner->cancelled_at);
        $this->assertNotNull($dinner->restored_at);
        $this->assertSame(RequirementCoverage::Partial, $requirement->coverage);
        $this->assertSame('150.000000', $requirement->missing_amount);
        $this->assertSame('50.000000', $requirement->reservations()->sum('normalized_amount'));
        $this->assertSame('50.000000', $entry->refresh()->total_normalized_amount);
    }

    public function test_unresolved_cooking_requires_current_confirmation_and_consumes_only_once(): void
    {
        [$user, $recipe, $entry] = $this->fixture('100', '60');
        $dinner = app(PlanDinner::class)->handle($user, $recipe, '4');

        $first = app(MarkDinnerCooked::class)->handle($user, $dinner);
        $this->assertTrue($first->requiresConfirmation);
        $this->assertSame('40.000000', $first->unresolved[0]['missing_amount']);

        $cooked = app(MarkDinnerCooked::class)->handle($user, $dinner, $first->fingerprint);
        $this->assertTrue($cooked->cooked);
        $this->assertSame('0.000000', $entry->refresh()->total_normalized_amount);

        $again = app(MarkDinnerCooked::class)->handle($user, $dinner);
        $this->assertTrue($again->alreadyCooked);
        $this->assertSame('0.000000', $entry->refresh()->total_normalized_amount);
    }

    public function test_cooked_dinners_are_terminal_for_cancel_and_restore(): void
    {
        [$user, $recipe] = $this->fixture('100', '100');
        $dinner = app(PlanDinner::class)->handle($user, $recipe, '4');
        $this->assertTrue(app(MarkDinnerCooked::class)->handle($user, $dinner)->cooked);

        foreach ([CancelDinner::class, RestoreCancelledDinner::class] as $actionClass) {
            try {
                app($actionClass)->handle($user, $dinner);
                $this->fail("{$actionClass} must reject a cooked dinner.");
            } catch (InvalidArgumentException) {
                $this->assertSame(PlannedDinnerStatus::Cooked, $dinner->refresh()->status);
            }
        }
    }

    /** @return array{User, Recipe, PantryEntry} */
    private function fixture(string $required, string $stock): array
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = Recipe::factory()->for($user)->create(['default_servings' => 4]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create(['entered_amount' => $required, 'normalized_amount' => $required]);
        $entry = PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => $stock]);

        return [$user, $recipe, $entry];
    }
}
