<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Enums\Runtime\RuntimeSessionStatus;
use App\Messenger\SlashCommands\SessionsCommandHandler;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the /sessions stop slash command (runtime sessions).
 */
final class StopCommandTest extends TestCase
{
    use RefreshDatabase;

    private SessionsCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(SessionsCommandHandler::class);
    }

    #[Test]
    public function sessions_stop_terminates_most_recent_session_when_no_id_provided(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'title' => 'My Session',
        ]);

        $result = $this->handler->handle($user, ['stop']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Stopped session', $result->message);
        $this->assertStringContainsString('My Session', $result->message);
        $this->assertEquals($session->id, $result->data['session_id']);

        $session->refresh();
        $this->assertEquals(RuntimeSessionStatus::Stopped, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    #[Test]
    public function sessions_stop_terminates_session_by_full_uuid(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'title' => 'Target Session',
        ]);

        $result = $this->handler->handle($user, ['stop', $session->id]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Stopped session', $result->message);

        $session->refresh();
        $this->assertEquals(RuntimeSessionStatus::Stopped, $session->status);
    }

    #[Test]
    public function sessions_stop_terminates_session_by_short_id(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'title' => 'Target Session',
        ]);

        $shortId = substr($session->id, 0, 8);

        $result = $this->handler->handle($user, ['stop', $shortId]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Stopped session', $result->message);

        $session->refresh();
        $this->assertEquals(RuntimeSessionStatus::Stopped, $session->status);
    }

    #[Test]
    public function sessions_stop_fails_when_session_not_found(): void
    {
        $user = User::factory()->create();

        $result = $this->handler->handle($user, ['stop', 'nonexistent-id']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Session not found', $result->message);
    }

    #[Test]
    public function sessions_stop_fails_when_no_active_sessions(): void
    {
        $user = User::factory()->create();

        $result = $this->handler->handle($user, ['stop']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No active session to stop', $result->message);
    }

    #[Test]
    public function sessions_stop_cannot_terminate_other_users_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $otherUser->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $result = $this->handler->handle($user, ['stop', $session->id]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Session not found', $result->message);

        $session->refresh();
        $this->assertEquals(RuntimeSessionStatus::Active, $session->status);
    }

    #[Test]
    public function sessions_stop_cannot_terminate_already_stopped_session(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Stopped,
        ]);

        $result = $this->handler->handle($user, ['stop', $session->id]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Session not found or not active', $result->message);
    }
}
