<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_verified_account_with_known_credentials_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $user = User::query()->where('email', 'test@example.com')->sole();

        $this->assertSame('Test User', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNull($user->two_factor_secret);
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
    }
}
