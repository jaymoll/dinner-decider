<?php

namespace Tests\Feature\DinnerPlans;

use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\Pantry\UpdatePantryEntry;
use App\Models\Ingredient;
use App\Models\IngredientReservation;
use App\Models\PantryEntry;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DinnerPlanConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_competing_plan_cook_and_stock_processes_preserve_reservation_and_consumption_invariants(): void
    {
        $this->markTestSkippedWhenDatabaseIsNotMysql();

        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $recipe = Recipe::factory()->for($user)->create(['default_servings' => 4]);
        RecipeIngredient::factory()->for($recipe)->for($ingredient)->create(['entered_amount' => '80', 'normalized_amount' => '80']);
        $entry = PantryEntry::factory()->for($user)->for($ingredient)->create(['total_normalized_amount' => '100']);

        Concurrency::run([
            fn (): int => app(PlanDinner::class)->handle(User::findOrFail($user->id), Recipe::findOrFail($recipe->id), '4')->id,
            fn (): int => app(PlanDinner::class)->handle(User::findOrFail($user->id), Recipe::findOrFail($recipe->id), '4')->id,
        ]);

        $this->assertSame(2, PlannedDinner::query()->count());
        $this->assertLessThanOrEqual(0, bccomp((string) IngredientReservation::query()->sum('normalized_amount'), $entry->total_normalized_amount, 6));

        $firstDinnerId = (int) PlannedDinner::query()->oldest('position')->value('id');
        $cookResults = Concurrency::run([
            fn (): bool => app(MarkDinnerCooked::class)->handle(User::findOrFail($user->id), PlannedDinner::findOrFail($firstDinnerId))->cooked,
            fn (): bool => app(MarkDinnerCooked::class)->handle(User::findOrFail($user->id), PlannedDinner::findOrFail($firstDinnerId))->cooked,
        ]);

        $this->assertSame([true, true], $cookResults);
        $this->assertSame('20.000000', $entry->refresh()->total_normalized_amount);

        Concurrency::run([
            fn (): int => app(PlanDinner::class)->handle(User::findOrFail($user->id), Recipe::findOrFail($recipe->id), '4')->id,
            function () use ($user, $entry): string {
                return app(UpdatePantryEntry::class)->handle(
                    User::findOrFail($user->id),
                    PantryEntry::findOrFail($entry->id),
                    '10',
                )->total_normalized_amount;
            },
        ]);

        $entry->refresh();
        $this->assertSame('10.000000', $entry->total_normalized_amount);
        $this->assertLessThanOrEqual(0, bccomp((string) $entry->reservations()->sum('normalized_amount'), $entry->total_normalized_amount, 6));
    }

    private function markTestSkippedWhenDatabaseIsNotMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Process-level lock coverage requires MySQL.');
        }
    }
}
