<?php

namespace Tests\Feature\Groceries;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroceryLivewireTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verified_users_can_render_and_add_a_manual_item(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('groceries.index'))->assertOk()->assertSee('Groceries');
        Livewire::actingAs($user)->test('pages::groceries.index')
            ->set('form.name', 'Dish soap')->set('form.quantityDescription', '1 bottle')->set('form.category', 'household')
            ->call('save')->assertHasNoErrors()->assertSee('Dish soap');
    }

    public function test_route_requires_authentication_and_verified_email(): void
    {
        $this->get(route('groceries.index'))->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('groceries.index'))->assertRedirect(route('verification.notice'));
    }
}
