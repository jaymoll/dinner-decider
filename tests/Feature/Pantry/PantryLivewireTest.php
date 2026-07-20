<?php

namespace Tests\Feature\Pantry;

use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PantryLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_can_add_stock_and_view_the_pantry(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create(['name' => 'Flour']);

        Livewire::actingAs($user)->test('pages::pantry.create')
            ->set('form.ingredient_id', $ingredient->id)->set('form.unit', 'kg')->set('form.amount', '1.5')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('pantry.index'));

        Livewire::actingAs($user)->test('pages::pantry.index')->assertSee('Flour')->assertSee('1.5 kg');
    }

    public function test_a_user_cannot_edit_another_users_entry(): void
    {
        $user = User::factory()->create();
        $entry = PantryEntry::factory()->create();

        $this->actingAs($user)->get(route('pantry.edit', $entry))->assertForbidden();
    }
}
