<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertSee('Choose dinner with what you have')
            ->assertSee(route('ingredients.index'))
            ->assertSee(route('recipes.index'))
            ->assertSee(route('pantry.index'))
            ->assertSee(route('recommendations.index'))
            ->assertSee(route('dinner-plans.index'))
            ->assertSee(route('groceries.index'))
            ->assertDontSee('livewire-starter-kit');
    }

    public function test_unverified_users_are_redirected_to_the_email_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }
}
