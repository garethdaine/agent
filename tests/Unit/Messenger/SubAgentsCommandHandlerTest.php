<?php

namespace Tests\Unit\Messenger;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Messenger\SlashCommands\SubAgentsCommandHandler;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SubAgentsCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    private ChatSession $chatSession;

    private RuntimeSession $parentSession;

    protected function setUp(): void
    {
        parent::setUp();

        config(['runtime.subagents.enabled' => true]);

        $this->user = User::factory()->create();
        $this->account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $this->chatSession = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
        ]);
        $this->parentSession = RuntimeSession::create([
            'user_id' => $this->user->id,
            'chat_session_id' => $this->chatSession->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);
    }

    public function test_list_shows_no_subagents_when_empty(): void
    {
        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, ['list'], $this->chatSession->id, $this->account->id);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No sub-agents', $result->message);
    }

    public function test_list_shows_active_and_recent_subagents(): void
    {
        RuntimeSession::create([
            'user_id' => $this->user->id,
            'chat_session_id' => $this->chatSession->id,
            'parent_session_id' => $this->parentSession->id,
            'spawn_depth' => 1,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'title' => 'Code Reviewer',
            'started_at' => now()->subMinutes(3),
        ]);
        RuntimeSession::create([
            'user_id' => $this->user->id,
            'chat_session_id' => $this->chatSession->id,
            'parent_session_id' => $this->parentSession->id,
            'spawn_depth' => 1,
            'status' => RuntimeSessionStatus::Stopped,
            'mode' => RuntimeMode::Safe,
            'title' => 'Doc Writer',
            'started_at' => now()->subMinutes(10),
            'ended_at' => now()->subMinutes(5),
        ]);

        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, ['list'], $this->chatSession->id, $this->account->id);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Code Reviewer', $result->message);
        $this->assertStringContainsString('Doc Writer', $result->message);
    }

    public function test_spawn_creates_subagent(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);

        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle(
            $this->user,
            ['spawn', 'Write', 'a', 'cover', 'letter'],
            $this->chatSession->id,
            $this->account->id
        );

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Spawned', $result->message);
        Bus::assertDispatched(ProcessRuntimeTurnJob::class);
    }

    public function test_spawn_requires_task(): void
    {
        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, ['spawn'], $this->chatSession->id, $this->account->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('task', $result->message);
    }

    public function test_kill_stops_subagent(): void
    {
        $child = RuntimeSession::create([
            'user_id' => $this->user->id,
            'chat_session_id' => $this->chatSession->id,
            'parent_session_id' => $this->parentSession->id,
            'spawn_depth' => 1,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'title' => 'Worker',
            'started_at' => now(),
        ]);

        $shortId = substr($child->id, 0, 8);
        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, ['kill', $shortId], $this->chatSession->id, $this->account->id);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Stopped', $result->message);

        $child->refresh();
        $this->assertEquals(RuntimeSessionStatus::Stopped, $child->status);
    }

    public function test_kill_requires_id(): void
    {
        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, ['kill'], $this->chatSession->id, $this->account->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('ID', $result->message);
    }

    public function test_unknown_subcommand_returns_usage(): void
    {
        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, ['foo'], $this->chatSession->id, $this->account->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('list', $result->message);
    }

    public function test_default_with_no_args_shows_list(): void
    {
        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle($this->user, [], $this->chatSession->id, $this->account->id);

        $this->assertTrue($result->success);
    }

    public function test_spawn_rejected_when_disabled(): void
    {
        config(['runtime.subagents.enabled' => false]);

        $handler = app(SubAgentsCommandHandler::class);
        $result = $handler->handle(
            $this->user,
            ['spawn', 'Do', 'something'],
            $this->chatSession->id,
            $this->account->id
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('disabled', $result->message);
    }
}
