<?php

namespace Tests\Feature\Ingredients;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IngredientLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_can_create_an_ingredient_from_the_livewire_form(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::ingredients.create')
            ->set('form.name', 'Olive Oil')
            ->set('form.category', 'Dry goods')
            ->set('form.preferred_measurement_group', 'volume')
            ->set('form.preferred_unit', 'ml')
            ->set('form.is_staple', true)
            ->call('addAlias')
            ->set('form.aliases.0', 'Cooking oil')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('ingredients.index'));

        $this->assertTrue($user->ingredients()->where('normalized_name', 'olive oil')->where('is_staple', true)->exists());
    }

    public function test_incompatible_preferred_units_are_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::ingredients.create')
            ->set('form.name', 'Onion')
            ->set('form.preferred_measurement_group', 'count')
            ->set('form.preferred_unit', 'g')
            ->call('save')
            ->assertHasErrors(['preferred_unit']);
    }
}
