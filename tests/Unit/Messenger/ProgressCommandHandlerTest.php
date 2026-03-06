<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Enums\Runtime\RuntimeSessionStatus;
use App\Messenger\SlashCommands\ProgressCommandHandler;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProgressCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProgressCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email' => 'progress-test@example.com']);
        $this->handler = app(ProgressCommandHandler::class);
    }

    public function test_returns_no_active_session_when_none_exists(): void
    {
        $result = $this->handler->handle($this->user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No active session', $result->message);
    }

    public function test_returns_no_live_data_when_session_exists_but_no_cache(): void
    {
        RuntimeSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => RuntimeSessionStatus::Active,
            'parent_session_id' => null,
        ]);

        $result = $this->handler->handle($this->user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No live progress', $result->message);
    }

    public function test_returns_live_progress_from_cache(): void
    {
        $session = RuntimeSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => RuntimeSessionStatus::Active,
            'parent_session_id' => null,
        ]);

        Cache::put("runtime:live_fragments:{$session->id}", [
            'fragment_count' => 198,
            'elapsed_seconds' => 840,
            'runner_session_id' => 'runner-123',
            'text_length' => 4500,
            'text_preview' => 'I am currently reviewing the job description and preparing your application materials...',
            'recent_activity' => 'tool_call:Read | text:reviewing requirements',
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        $result = $this->handler->handle($this->user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('198', $result->message);
        $this->assertStringContainsString('14m', $result->message);
        $this->assertStringContainsString('reviewing', $result->message);
        $this->assertStringContainsString('4,500', $result->message);
    }

    public function test_scoped_to_chat_session(): void
    {
        $account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $chatSession = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
        ]);

        $session = RuntimeSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => RuntimeSessionStatus::Active,
            'parent_session_id' => null,
            'chat_session_id' => $chatSession->id,
        ]);

        Cache::put("runtime:live_fragments:{$session->id}", [
            'fragment_count' => 50,
            'elapsed_seconds' => 120,
            'runner_session_id' => null,
            'text_length' => 200,
            'text_preview' => 'Working on it...',
            'recent_activity' => 'text:Working on it',
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        $result = $this->handler->handle($this->user, [], $chatSession->id);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('50', $result->message);
    }

    public function test_includes_subagent_progress_when_available(): void
    {
        $parent = RuntimeSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => RuntimeSessionStatus::Active,
            'parent_session_id' => null,
        ]);

        RuntimeSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => RuntimeSessionStatus::Active,
            'parent_session_id' => $parent->id,
            'title' => 'Research task',
        ]);

        Cache::put("runtime:live_fragments:{$parent->id}", [
            'fragment_count' => 100,
            'elapsed_seconds' => 300,
            'runner_session_id' => null,
            'text_length' => 1000,
            'text_preview' => 'Delegating work...',
            'recent_activity' => 'text:delegating',
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        $result = $this->handler->handle($this->user, []);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Research task', $result->message);
        $this->assertStringContainsString('Sub-agent', $result->message);
    }
}
