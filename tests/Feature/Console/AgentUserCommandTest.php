<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_user_with_personal_team(): void
    {
        $this->artisan('agent:user', [
            '--name' => 'Admin User',
            '--email' => 'admin@example.com',
            '--password' => 'SecurePass123!',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasCompletedOnboarding());
        $this->assertCount(1, $user->ownedTeams);
        $this->assertTrue($user->ownedTeams->first()->personal_team);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('agent:user', [
            '--name' => 'Duplicate',
            '--email' => 'existing@example.com',
            '--password' => 'SecurePass123!',
        ])->assertExitCode(1);
    }

    public function test_validates_email_format(): void
    {
        $this->artisan('agent:user', [
            '--name' => 'Bad Email',
            '--email' => 'not-an-email',
            '--password' => 'SecurePass123!',
        ])->assertExitCode(1);
    }

    public function test_validates_password_length(): void
    {
        $this->artisan('agent:user', [
            '--name' => 'Short Pass',
            '--email' => 'short@example.com',
            '--password' => '123',
        ])->assertExitCode(1);
    }
}
