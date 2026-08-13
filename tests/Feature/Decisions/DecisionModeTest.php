<?php

namespace Tests\Feature\Decisions;

use App\Actions\Decisions\PlanDecisionChoice;
use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\Favourites\AddRecipeFavourite;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Queries\GetDecisionCandidates;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DecisionModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_same_inputs_are_identical_and_reroll_changes_equivalent_order_deterministically(): void
    {
        $user = User::factory()->create();
        Recipe::factory()->count(8)->for($user)->create();
        $query = app(GetDecisionCandidates::class);

        $first = $query->get($user, 'stable-seed', 0, limit: 8)->pluck('recommendation.recipe.id')->all();
        $same = $query->get($user, 'stable-seed', 0, limit: 8)->pluck('recommendation.recipe.id')->all();
        $rerolled = $query->get($user, 'stable-seed', 1, limit: 8)->pluck('recommendation.recipe.id')->all();

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $rerolled);
        $this->assertSame($rerolled, $query->get($user, 'stable-seed', 1, limit: 8)->pluck('recommendation.recipe.id')->all());
    }

    public function test_pantry_remains_primary_then_favourite_and_longest_since_cooked_refine_ties(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '100']);
        $suitableNeverCooked = $this->recipe($user, $ingredient, 'Suitable never', '100');
        $suitableCooked = $this->recipe($user, $ingredient, 'Suitable cooked', '100');
        $suitableFavourite = $this->recipe($user, $ingredient, 'Suitable favourite', '100');
        $missingFavourite = $this->recipe($user, $ingredient, 'Missing favourite', '200');
        app(AddRecipeFavourite::class)->handle($user, $suitableFavourite);
        app(AddRecipeFavourite::class)->handle($user, $missingFavourite);
        PlannedDinner::factory()->cooked()->for(app(EnsureDinnerPlan::class)->handle($user))->create([
            'recipe_id' => $suitableCooked->id,
            'recipe_name' => $suitableCooked->name,
        ]);

        $ids = app(GetDecisionCandidates::class)->get($user, 'factor-seed', limit: 10)
            ->pluck('recommendation.recipe.id')->all();

        $this->assertSame($suitableFavourite->id, $ids[0]);
        $this->assertSame($suitableNeverCooked->id, $ids[1]);
        $this->assertSame($suitableCooked->id, $ids[2]);
        $this->assertSame($missingFavourite->id, $ids[3]);
    }

    public function test_archived_and_foreign_recipes_are_neither_candidates_nor_selectable(): void
    {
        $user = User::factory()->create();
        $active = Recipe::factory()->for($user)->create();
        $archived = Recipe::factory()->for($user)->create(['archived_at' => now()]);
        $foreign = Recipe::factory()->create();

        $ids = app(GetDecisionCandidates::class)->get($user, 'eligibility-seed', limit: 10)
            ->pluck('recommendation.recipe.id')->all();
        $this->assertSame([$active->id], $ids);

        foreach ([$archived, $foreign] as $ineligible) {
            try {
                app(PlanDecisionChoice::class)->handle($user, $ineligible->id, 'eligibility-seed', 0, null, []);
                $this->fail('Ineligible recipe was selectable.');
            } catch (ModelNotFoundException) {
                $this->assertFalse($user->dinnerPlan?->dinners()->where('recipe_id', $ineligible->id)->exists() ?? false);
            }
        }
    }

    public function test_explanations_only_describe_available_stage_seven_factors(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create();
        app(AddRecipeFavourite::class)->handle($user, $recipe);

        $candidate = app(GetDecisionCandidates::class)->get($user, 'explanation-seed')->sole();
        $explanation = implode(' ', $candidate->explanations);

        $this->assertStringContainsString('Pantry score', $explanation);
        $this->assertStringContainsString('favourite', $explanation);
        $this->assertStringContainsString('Never cooked', $explanation);
        foreach (['diet', 'expiry', 'cost', 'AI'] as $unsupported) {
            $this->assertStringNotContainsStringIgnoringCase($unsupported, $explanation);
        }
    }

    public function test_planning_a_choice_creates_the_normal_snapshot_reservation_and_grocery_projection(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '60']);
        $recipe = $this->recipe($user, $ingredient, 'Decision dinner', '100');

        $dinner = app(PlanDecisionChoice::class)->handle($user, $recipe->id, 'planning-seed', 0, '4', []);

        $this->assertSame('Decision dinner', $dinner->recipe_name);
        $this->assertSame(1, $dinner->requirements()->count());
        $this->assertSame('60.000000', $dinner->requirements()->sole()->reservations()->sum('normalized_amount'));
        $this->assertTrue($dinner->dinnerPlan->groceryList->items()->where('source', 'generated')->exists());

        $duplicate = app(PlanDecisionChoice::class)->handle($user, $recipe->id, 'planning-seed', 0, '4', []);
        $this->assertNotSame($dinner->id, $duplicate->id);
    }

    public function test_candidate_query_count_is_constant_as_volume_grows(): void
    {
        $small = User::factory()->create();
        Recipe::factory()->count(2)->for($small)->create();
        $smallCount = $this->queryCount($small);

        $large = User::factory()->create();
        Recipe::factory()->count(15)->for($large)->create();
        $largeCount = $this->queryCount($large);

        $this->assertSame($smallCount, $largeCount);
    }

    private function queryCount(User $user): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(GetDecisionCandidates::class)->get($user, 'query-seed');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function recipe(User $user, Ingredient $ingredient, string $name, string $amount): Recipe
    {
        $recipe = Recipe::factory()->for($user)->create(['name' => $name]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create([
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => $amount,
            'entered_unit' => UnitCode::Gram,
            'normalized_amount' => $amount,
            'compatibility_key' => 'mass',
        ]);

        return $recipe;
    }
}
