<?php

namespace Tests\Feature\Decisions;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DecisionModeLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_empty_and_undersized_candidate_pools_render_clear_states(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test('pages::decisions.index')
            ->assertSee('No eligible recipes remain');

        Recipe::factory()->for($user)->create(['name' => 'Only choice']);
        Livewire::actingAs($user)->test('pages::decisions.index')
            ->assertSee('Only 1 eligible recipe')
            ->assertSee('Only choice')
            ->assertSee('Exclude')
            ->assertSee('Reroll choices');
    }

    public function test_reroll_and_exclusions_are_normalized_bounded_and_session_only(): void
    {
        config()->set('decisions.exclusion_limit', 2);
        $user = User::factory()->create();
        $recipes = Recipe::factory()->count(4)->for($user)->create();
        $firstId = $recipes->firstOrFail()->id;

        $component = Livewire::actingAs($user)->test('pages::decisions.index')
            ->set('excludedRecipeIds', ['invalid', -1, $firstId, (string) $firstId, 999999])
            ->call('reroll')
            ->assertSet('round', 1);

        $this->assertSame([$firstId, 999999], $component->get('excludedRecipeIds'));
        $this->assertFalse($user->recipes()->whereKey($firstId)->firstOrFail()->getAttribute('archived_at') !== null);
    }

    public function test_exclude_reauthorizes_visible_candidates_and_planning_uses_the_action_boundary(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create(['name' => 'Plan this choice']);
        $foreign = Recipe::factory()->create();

        try {
            Livewire::actingAs($user)->test('pages::decisions.index')->call('exclude', $foreign->id);
            $this->fail('A foreign recipe was accepted as an exclusion.');
        } catch (ModelNotFoundException) {
            $this->assertFalse($user->recipes()->whereKey($foreign->id)->exists());
        }

        Livewire::actingAs($user)->test('pages::decisions.index')
            ->call('plan', $recipe->id)
            ->assertHasNoErrors();

        $this->assertTrue($user->dinnerPlan()->firstOrFail()->dinners()->where('recipe_id', $recipe->id)->exists());
    }
}
