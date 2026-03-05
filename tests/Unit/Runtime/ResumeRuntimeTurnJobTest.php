<?php

namespace Tests\Unit\Runtime;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\ResumeRuntimeTurnJob;
use App\Jobs\Runtime\RuntimeTurnCompletedJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\SessionProcessManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ResumeRuntimeTurnJobTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $account;

    private ChatSession $chatSession;

    private RuntimeSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $this->chatSession = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
        ]);
        $this->session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);
    }

    public function test_job_queued_on_agent_queue(): void
    {
        $job = new ResumeRuntimeTurnJob(
            runtimeSessionId: $this->session->id,
            remainingTimeout: 1500,
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $this->assertEquals(config('runtime.queue', 'agent'), $job->queue);
    }

    public function test_dispatches_completed_job_when_turn_completes(): void
    {
        Bus::fake([RuntimeTurnCompletedJob::class, ResumeRuntimeTurnJob::class]);

        $processManager = Mockery::mock(SessionProcessManager::class);
        $processManager->shouldReceive('resumeReadTurnResponse')
            ->once()
            ->andReturn([
                'status' => 'completed',
                'text' => 'Final result here.',
                'runner_session_id' => 'runner-abc',
            ]);

        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(false);

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ResumeRuntimeTurnJob(
            runtimeSessionId: $this->session->id,
            remainingTimeout: 1500,
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($processManager, $connectorManager);

        Bus::assertDispatched(RuntimeTurnCompletedJob::class, function ($dispatched) {
            return $dispatched->result['status'] === 'completed'
                && $dispatched->result['text'] === 'Final result here.';
        });
    }

    public function test_re_dispatches_self_when_yielded_again(): void
    {
        Bus::fake([RuntimeTurnCompletedJob::class, ResumeRuntimeTurnJob::class]);

        $processManager = Mockery::mock(SessionProcessManager::class);
        $processManager->shouldReceive('resumeReadTurnResponse')
            ->once()
            ->andReturn([
                'status' => 'yielded',
                'session_id' => $this->session->id,
                'elapsed_seconds' => 240,
            ]);

        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(true);
        $adapter->shouldReceive('editMessage')->once()->andReturn(
            new ProviderResponse(success: true, providerMessageId: 'ph-1')
        );

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ResumeRuntimeTurnJob(
            runtimeSessionId: $this->session->id,
            remainingTimeout: 1500,
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
            placeholderMessageId: 'ph-1',
        );

        $job->handle($processManager, $connectorManager);

        Bus::assertDispatched(ResumeRuntimeTurnJob::class);
        Bus::assertNotDispatched(RuntimeTurnCompletedJob::class);
    }

    public function test_dispatches_completed_job_on_failure(): void
    {
        Bus::fake([RuntimeTurnCompletedJob::class, ResumeRuntimeTurnJob::class]);

        $processManager = Mockery::mock(SessionProcessManager::class);
        $processManager->shouldReceive('resumeReadTurnResponse')
            ->once()
            ->andReturn([
                'status' => 'failed',
                'error' => 'Process exited unexpectedly.',
            ]);

        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(false);

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ResumeRuntimeTurnJob(
            runtimeSessionId: $this->session->id,
            remainingTimeout: 1500,
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($processManager, $connectorManager);

        Bus::assertDispatched(RuntimeTurnCompletedJob::class, function ($dispatched) {
            return $dispatched->result['status'] === 'failed';
        });
        Bus::assertNotDispatched(ResumeRuntimeTurnJob::class);
    }
}
