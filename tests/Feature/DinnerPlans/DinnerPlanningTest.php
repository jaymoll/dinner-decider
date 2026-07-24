<?php

namespace Tests\Feature\DinnerPlans;

use App\Actions\DinnerPlans\DuplicatePlannedDinner;
use App\Actions\DinnerPlans\PlanDinner;
use App\Enums\RequirementCoverage;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DinnerPlanningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_planning_creates_complete_snapshots_and_partial_multi_entry_reservations(): void
    {
        [$user, $recipe, $ingredient] = $this->recipeFixture('100');
        $package = IngredientPackage::factory()->for($ingredient)->create(['label' => '30 g pack', 'content_amount' => '30', 'normalized_content_amount' => '30']);
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '40']);
        PantryEntry::factory()->for($user)->for($ingredient)->for($package, 'ingredientPackage')->create([
            'display_unit' => null, 'total_normalized_amount' => '30', 'compatibility_key' => 'mass', 'merge_key' => 'package:'.$package->id,
        ]);

        $dinner = app(PlanDinner::class)->handle($user, $recipe, '2', '2026-07-21');
        $requirement = $dinner->requirements()->with('reservations')->sole();

        $this->assertSame('50.000000', $requirement->scaled_amount);
        $this->assertSame(RequirementCoverage::Full, $requirement->coverage);
        $reserved = $requirement->reservations->reduce(fn (string $sum, $reservation): string => bcadd($sum, $reservation->normalized_amount, 6), '0');
        $this->assertSame('50.000000', $reserved);
        $this->assertSame('21-07-2026', $dinner->planned_date->format('d-m-Y'));

        $recipe->update(['name' => 'Edited later']);
        $this->assertSame('Fixture dinner', $dinner->refresh()->recipe_name);
    }

    public function test_duplicate_occurrences_have_independent_requirement_and_reservation_identities(): void
    {
        [$user, $recipe, $ingredient] = $this->recipeFixture('100');
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '300']);
        $first = app(PlanDinner::class)->handle($user, $recipe, '4');
        $duplicate = app(DuplicatePlannedDinner::class)->handle($user, $first);

        $this->assertNotSame($first->id, $duplicate->id);
        $this->assertNotSame($first->requirements()->sole()->id, $duplicate->requirements()->sole()->id);
        $this->assertNotSame($first->requirements()->sole()->reservations()->sole()->id, $duplicate->requirements()->sole()->reservations()->sole()->id);
    }

    public function test_a_user_cannot_plan_another_users_recipe(): void
    {
        [$owner, $recipe] = $this->recipeFixture('100');

        $this->expectException(AuthorizationException::class);
        app(PlanDinner::class)->handle(User::factory()->create(), $recipe, '4');
    }

    /** @return array{User, Recipe, Ingredient} */
    private function recipeFixture(string $amount): array
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Fixture dinner', 'default_servings' => 4]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create(['entered_amount' => $amount, 'normalized_amount' => $amount]);

        return [$user, $recipe, $ingredient];
    }
}
