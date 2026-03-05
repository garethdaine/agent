<?php

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\RuntimeTurnCompletedJob;
use App\Jobs\Runtime\SubAgentCompletionJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Messenger\CompactionService;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AnnounceBackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    private ChatSession $chatSession;

    private RuntimeSession $parentSession;

    private RuntimeSession $childSession;

    protected function setUp(): void
    {
        parent::setUp();

        config(['messenger.compaction.enabled' => false]);

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
        $this->childSession = RuntimeSession::create([
            'user_id' => $this->user->id,
            'chat_session_id' => $this->chatSession->id,
            'parent_session_id' => $this->parentSession->id,
            'spawn_depth' => 1,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'title' => 'Code Review Bot',
            'started_at' => now(),
        ]);
    }

    public function test_turn_completed_dispatches_subagent_completion_for_child_session(): void
    {
        Bus::fake([SubAgentCompletionJob::class]);

        $connectorManager = $this->createMock(ConnectorManager::class);
        $compactionService = app(CompactionService::class);

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->childSession->id,
            result: ['status' => 'completed', 'text' => 'Review done.'],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($connectorManager, $compactionService);

        Bus::assertDispatched(SubAgentCompletionJob::class, function ($dispatchedJob) {
            return $dispatchedJob->childSessionId === $this->childSession->id
                && $dispatchedJob->parentSessionId === $this->parentSession->id
                && $dispatchedJob->status === 'completed'
                && $dispatchedJob->text === 'Review done.'
                && $dispatchedJob->label === 'Code Review Bot';
        });
    }

    public function test_turn_completed_does_not_dispatch_for_root_session(): void
    {
        Bus::fake([SubAgentCompletionJob::class]);

        $connectorManager = $this->createMock(ConnectorManager::class);
        $compactionService = app(CompactionService::class);

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->parentSession->id,
            result: ['status' => 'completed', 'text' => 'Done.'],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($connectorManager, $compactionService);

        Bus::assertNotDispatched(SubAgentCompletionJob::class);
    }

    public function test_turn_completed_dispatches_subagent_completion_for_failed_child(): void
    {
        Bus::fake([SubAgentCompletionJob::class]);

        $connectorManager = $this->createMock(ConnectorManager::class);
        $compactionService = app(CompactionService::class);

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->childSession->id,
            result: ['status' => 'failed', 'error' => 'Process crashed.'],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($connectorManager, $compactionService);

        Bus::assertDispatched(SubAgentCompletionJob::class, function ($dispatchedJob) {
            return $dispatchedJob->status === 'failed'
                && $dispatchedJob->error === 'Process crashed.';
        });
    }
}
