<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Enums\Runtime\RuntimeSessionStatus;
use App\Messenger\SlashCommands\StatusCommandHandler;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the /status slash command handler.
 *
 * These tests verify:
 * - Status returns session info when active sessions exist
 * - Status shows appropriate message when no sessions exist
 */
final class StatusCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function status_returns_session_info_when_active(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $handler = new StatusCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Active sessions: 1', $result->message);
    }

    #[Test]
    public function status_shows_no_active_sessions(): void
    {
        $user = User::factory()->create();

        $handler = new StatusCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No active runtime sessions', $result->message);
    }

    #[Test]
    public function status_counts_only_active_sessions(): void
    {
        $user = User::factory()->create();

        // Create multiple sessions with different statuses
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        RuntimeSession::factory()->completed()->create([
            'user_id' => $user->id,
        ]);
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Failed,
        ]);

        $handler = new StatusCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Active sessions: 2', $result->message);
    }

    #[Test]
    public function status_does_not_count_other_users_sessions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        RuntimeSession::factory()->create([
            'user_id' => $otherUser->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $handler = new StatusCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Active sessions: 1', $result->message);
    }
}
