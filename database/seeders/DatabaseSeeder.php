<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrNew([
            'email' => 'test@example.com',
        ]);

        $user->name = 'Test User';
        $user->email_verified_at = now();
        $user->password = 'password';
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $this->call(StageOneCatalogueSeeder::class);
    }
}
