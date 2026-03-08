<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');
        if ($adminPassword) {
            User::query()->updateOrCreate(
                ['email' => 'gareth@garethdaine.com'],
                [
                    'name' => 'Gareth Daine',
                    'password' => $adminPassword,
                ]
            );
        }

        if (env('E2E_SEED_USER', false)) {
            $email = env('TEST_USER_EMAIL', 'test@example.com');
            $password = env('TEST_USER_PASSWORD', 'password');
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'E2E Test User',
                    'password' => $password,
                    'onboarding_completed_at' => now(),
                ]
            );
        }
    }
}
