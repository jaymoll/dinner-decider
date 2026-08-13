<?php

namespace Tests\Feature\DinnerPlans;

use App\Actions\DinnerPlans\ChangePlannedDinnerDate;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\DinnerPlans\ReorderPlannedDinner;
use App\Enums\RequirementCoverage;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DinnerPlanPriorityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dated_dinners_receive_stock_before_undated_dinners_and_earliest_date_wins(): void
    {
        [$user, $recipe] = $this->fixture();

        $undated = app(PlanDinner::class)->handle($user, $recipe, '4');
        $later = app(PlanDinner::class)->handle($user, $recipe, '4', '2026-08-02');

        $this->assertReservation($undated, '0.000000', RequirementCoverage::Missing);
        $this->assertReservation($later, '100.000000', RequirementCoverage::Full);

        $earlier = app(PlanDinner::class)->handle($user, $recipe, '4', '2026-08-01');

        $this->assertReservation($earlier, '100.000000', RequirementCoverage::Full);
        $this->assertReservation($later, '0.000000', RequirementCoverage::Missing);
        $this->assertReservation($undated, '0.000000', RequirementCoverage::Missing);
    }

    public function test_position_breaks_equal_date_ties_and_date_changes_reprioritize_existing_dinners(): void
    {
        [$user, $recipe] = $this->fixture();

        $first = app(PlanDinner::class)->handle($user, $recipe, '4', '2026-08-02');
        $second = app(PlanDinner::class)->handle($user, $recipe, '4', '2026-08-02');

        $this->assertReservation($first, '100.000000', RequirementCoverage::Full);
        $this->assertReservation($second, '0.000000', RequirementCoverage::Missing);

        app(ReorderPlannedDinner::class)->handle($user, $second, 1);

        $this->assertReservation($second, '100.000000', RequirementCoverage::Full);
        $this->assertReservation($first, '0.000000', RequirementCoverage::Missing);

        app(ChangePlannedDinnerDate::class)->handle($user, $first, '2026-08-01');

        $this->assertReservation($first, '100.000000', RequirementCoverage::Full);
        $this->assertReservation($second, '0.000000', RequirementCoverage::Missing);
    }

    /** @return array{User, Recipe} */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = Recipe::factory()->for($user)->create(['default_servings' => 4]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create([
            'entered_amount' => '100',
            'normalized_amount' => '100',
        ]);
        PantryEntry::factory()->for($user)->for($ingredient)->create([
            'total_normalized_amount' => '100',
        ]);

        return [$user, $recipe];
    }

    private function assertReservation(
        PlannedDinner $dinner,
        string $expectedAmount,
        RequirementCoverage $expectedCoverage,
    ): void {
        $requirement = $dinner->requirements()->sole();
        $reservedAmount = bcadd(
            '0',
            (string) $requirement->reservations()->sum('normalized_amount'),
            6,
        );

        $this->assertSame($expectedAmount, $reservedAmount);
        $this->assertSame($expectedCoverage, $requirement->refresh()->coverage);
    }
}
