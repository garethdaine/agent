<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = config('seeding.admin_password');
        if ($adminPassword) {
            User::query()->updateOrCreate(
                ['email' => 'gareth@garethdaine.com'],
                [
                    'name' => 'Gareth Daine',
                    'password' => $adminPassword,
                ]
            );
        }

        if (config('seeding.e2e_seed_user', false)) {
            $email = config('seeding.test_user_email', 'test@example.com');
            $password = config('seeding.test_user_password', 'password');
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
