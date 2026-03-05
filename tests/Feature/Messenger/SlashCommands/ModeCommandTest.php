<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Messenger\SlashCommands\ModeCommandHandler;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the /mode slash command handler.
 */
final class ModeCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mode_shows_current_mode_when_no_args(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
        ]);

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Current mode: safe', $result->message);
    }

    #[Test]
    public function mode_shows_default_when_no_active_session(): void
    {
        $user = User::factory()->create();

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No active session', $result->message);
        $this->assertStringContainsString('Default mode:', $result->message);
    }

    #[Test]
    public function mode_changes_to_standard(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
        ]);

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, ['standard']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Mode changed from safe to standard', $result->message);

        $session->refresh();
        $this->assertEquals(RuntimeMode::Standard, $session->mode);
    }

    #[Test]
    public function mode_changes_to_full(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
        ]);

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, ['full']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Mode changed from safe to full', $result->message);

        $session->refresh();
        $this->assertEquals(RuntimeMode::Full, $session->mode);
    }

    #[Test]
    public function mode_indicates_already_set(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Standard,
        ]);

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, ['standard']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Mode is already set to standard', $result->message);
    }

    #[Test]
    public function mode_fails_for_invalid_mode(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, ['invalid']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid mode:', $result->message);
        $this->assertStringContainsString('Valid modes:', $result->message);
    }

    #[Test]
    public function mode_fails_when_no_active_session(): void
    {
        $user = User::factory()->create();

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, ['standard']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No active session to change mode for', $result->message);
    }

    #[Test]
    public function mode_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
        ]);

        $handler = new ModeCommandHandler;
        $result = $handler->handle($user, ['STANDARD']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Mode changed from safe to standard', $result->message);

        $session->refresh();
        $this->assertEquals(RuntimeMode::Standard, $session->mode);
    }
}
