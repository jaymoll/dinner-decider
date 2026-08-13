<?php

namespace Tests\Feature\DinnerPlans;

use App\Actions\DinnerPlans\CancelDinner;
use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\DinnerPlans\PlanDinnerFromHistory;
use App\Actions\DinnerPlans\RestoreCancelledDinner;
use App\Actions\Recipes\ArchiveRecipe;
use App\Enums\PlannedDinnerStatus;
use App\Enums\PlannedDinnerStatusEventType;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\User;
use App\Queries\GetDinnerHistory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DinnerHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_lifecycle_events_record_exact_transitions_without_idempotent_duplicates(): void
    {
        $user = User::factory()->create();
        $dinner = app(PlanDinner::class)->handle($user, Recipe::factory()->for($user)->create(), '4');

        app(CancelDinner::class)->handle($user, $dinner);
        app(CancelDinner::class)->handle($user, $dinner);
        app(RestoreCancelledDinner::class)->handle($user, $dinner);
        app(RestoreCancelledDinner::class)->handle($user, $dinner);
        app(CancelDinner::class)->handle($user, $dinner);
        app(RestoreCancelledDinner::class)->handle($user, $dinner);
        app(MarkDinnerCooked::class)->handle($user, $dinner);
        app(MarkDinnerCooked::class)->handle($user, $dinner);

        $events = $dinner->statusEvents()->get();
        $this->assertSame([
            PlannedDinnerStatusEventType::Planned,
            PlannedDinnerStatusEventType::Cancelled,
            PlannedDinnerStatusEventType::Restored,
            PlannedDinnerStatusEventType::Cancelled,
            PlannedDinnerStatusEventType::Restored,
            PlannedDinnerStatusEventType::Cooked,
        ], $events->pluck('event_type')->all());
        $this->assertSame([
            [null, PlannedDinnerStatus::Planned],
            [PlannedDinnerStatus::Planned, PlannedDinnerStatus::Cancelled],
            [PlannedDinnerStatus::Cancelled, PlannedDinnerStatus::Planned],
            [PlannedDinnerStatus::Planned, PlannedDinnerStatus::Cancelled],
            [PlannedDinnerStatus::Cancelled, PlannedDinnerStatus::Planned],
            [PlannedDinnerStatus::Planned, PlannedDinnerStatus::Cooked],
        ], $events->map(fn ($event): array => [$event->from_status, $event->to_status])->all());
        $this->assertTrue($events->every(fn ($event): bool => $event->actor_user_id === $user->id && ! $event->is_reconstructed));
    }

    public function test_event_writes_roll_back_with_the_occurrence_transaction(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create();

        try {
            DB::transaction(function () use ($user, $recipe): void {
                app(PlanDinner::class)->handle($user, $recipe, '4');
                throw new RuntimeException('Force rollback after all action writes.');
            });
        } catch (RuntimeException) {
            // Assert the occurrence and its lifecycle evidence share the same rollback boundary.
        }

        $this->assertSame(0, PlannedDinner::query()->count());
        $this->assertSame(0, DB::table('planned_dinner_status_events')->count());
    }

    public function test_history_orders_stably_and_filters_by_status_recipe_and_amsterdam_dates(): void
    {
        $user = User::factory()->create();
        $firstRecipe = Recipe::factory()->for($user)->create(['name' => 'First snapshot']);
        $secondRecipe = Recipe::factory()->for($user)->create(['name' => 'Second snapshot']);

        CarbonImmutable::setTestNow('2026-03-28 23:30:00 UTC');
        $early = $this->cook($user, $firstRecipe);
        CarbonImmutable::setTestNow('2026-03-29 21:30:00 UTC');
        $late = $this->cook($user, $secondRecipe);
        CarbonImmutable::setTestNow('2026-03-29 22:30:00 UTC');
        $outside = $this->cook($user, $firstRecipe);
        CarbonImmutable::setTestNow('2026-03-29 20:00:00 UTC');
        $cancelled = app(PlanDinner::class)->handle($user, $secondRecipe, '4');
        app(CancelDinner::class)->handle($user, $cancelled);

        $history = app(GetDinnerHistory::class)->get($user, status: PlannedDinnerStatus::Cooked, fromDate: '2026-03-29', toDate: '2026-03-29');
        $this->assertSame([$late->id, $early->id], $history->pluck('id')->all());
        $this->assertNotContains($outside->id, $history->pluck('id'));

        $combined = app(GetDinnerHistory::class)->get($user, recipeId: $secondRecipe->id, status: PlannedDinnerStatus::Cancelled);
        $this->assertSame([$cancelled->id], $combined->pluck('id')->all());
    }

    public function test_history_uses_immutable_snapshots_and_replanning_creates_an_independent_occurrence(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Original name', 'default_servings' => 3]);
        $historical = app(PlanDinner::class)->handle($user, $recipe, '6');
        app(MarkDinnerCooked::class)->handle($user, $historical);

        $recipe->update(['name' => 'Edited name']);
        app(ArchiveRecipe::class)->handle($user, $recipe);
        $recipe->delete();

        $history = app(GetDinnerHistory::class)->get($user)->sole();
        $this->assertSame('Original name', $history->recipe_name);
        $this->assertSame('6.000000', $history->servings);
        $this->assertNull($history->recipe_id);

        $replanned = app(PlanDinnerFromHistory::class)->handle($user, $history, '2');
        $this->assertNotSame($history->id, $replanned->id);
        $this->assertSame(PlannedDinnerStatus::Planned, $replanned->status);
        $this->assertSame('2.000000', $replanned->servings);
        $this->assertSame(1, $replanned->statusEvents()->count());
    }

    public function test_history_is_cross_user_isolated_and_query_count_is_bounded(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create();
        $otherRecipe = Recipe::factory()->for($otherUser)->create();
        foreach (range(1, 12) as $index) {
            $this->cook($user, $recipe);
        }
        $foreign = $this->cook($otherUser, $otherRecipe);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $firstPage = app(GetDinnerHistory::class)->get($user, perPage: 5, page: 1);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $secondPage = app(GetDinnerHistory::class)->get($user, perPage: 5, page: 2);

        $this->assertCount(5, $firstPage);
        $this->assertCount(5, $secondPage);
        $this->assertSame(12, $firstPage->total());
        $this->assertLessThanOrEqual(4, $queryCount);
        $this->assertNotContains($foreign->id, $firstPage->pluck('id'));
        $this->assertSame($firstPage->pluck('id')->sortDesc()->values()->all(), $firstPage->pluck('id')->all());
    }

    private function cook(User $user, Recipe $recipe): PlannedDinner
    {
        $dinner = app(PlanDinner::class)->handle($user, $recipe, (string) $recipe->default_servings);
        app(MarkDinnerCooked::class)->handle($user, $dinner);

        return $dinner->refresh();
    }
}
