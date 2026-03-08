<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Jobs\Runtime\ResumeRuntimeTurnJob;
use App\Jobs\Runtime\RuntimeTurnCompletedJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ProcessRuntimeTurnJobYieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['messenger.compaction.enabled' => false]);
    }

    public function test_yielded_result_dispatches_resume_job(): void
    {
        Bus::fake([ResumeRuntimeTurnJob::class, RuntimeTurnCompletedJob::class]);

        $user = User::factory()->create();
        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);

        $account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $chatSession = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
        ]);

        $orchestrator = $this->createMock(MessengerRuntimeOrchestrator::class);
        $orchestrator->method('executeTurn')->willReturn([
            'status' => 'yielded',
            'session_id' => $session->id,
            'elapsed_seconds' => 120,
        ]);

        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(true);
        $adapter->shouldReceive('editMessage')->andReturn(
            new ProviderResponse(success: true, providerMessageId: 'ph-1')
        );
        $adapter->shouldReceive('sendMessage')->andReturn(
            new ProviderResponse(success: true, providerMessageId: 'msg-1')
        );

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ProcessRuntimeTurnJob(
            runtimeSessionId: $session->id,
            userMessage: 'Do a long task',
            chatSessionId: $chatSession->id,
            connectorAccountId: $account->id,
            placeholderMessageId: 'ph-1',
        );

        $job->handle($orchestrator, $connectorManager, app(\App\Services\Messenger\CompactionService::class));

        Bus::assertDispatched(ResumeRuntimeTurnJob::class, function ($dispatched) use ($session) {
            return $dispatched->runtimeSessionId === $session->id;
        });
        Bus::assertNotDispatched(RuntimeTurnCompletedJob::class);
    }

    public function test_yielded_result_sends_progress_message_to_channel(): void
    {
        Bus::fake([ResumeRuntimeTurnJob::class]);

        $user = User::factory()->create();
        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);

        $account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $chatSession = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
        ]);

        $orchestrator = $this->createMock(MessengerRuntimeOrchestrator::class);
        $orchestrator->method('executeTurn')->willReturn([
            'status' => 'yielded',
            'session_id' => $session->id,
            'elapsed_seconds' => 120,
        ]);

        $sentMessages = [];
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(false);
        $adapter->shouldReceive('sendMessage')
            ->andReturnUsing(function ($session, $payload) use (&$sentMessages) {
                $sentMessages[] = $payload->content;

                return new ProviderResponse(success: true, providerMessageId: 'msg-1');
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ProcessRuntimeTurnJob(
            runtimeSessionId: $session->id,
            userMessage: 'Do a long task',
            chatSessionId: $chatSession->id,
            connectorAccountId: $account->id,
        );

        $job->handle($orchestrator, $connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertNotEmpty($sentMessages);
        $hasProgressMessage = collect($sentMessages)->contains(fn ($msg) => str_contains($msg, 'working') || str_contains($msg, 'Working') || str_contains($msg, 'report back'));
        $this->assertTrue($hasProgressMessage, 'Expected a progress message to be sent when turn yields');
    }

    public function test_non_yielded_result_does_not_dispatch_resume_job(): void
    {
        Bus::fake([ResumeRuntimeTurnJob::class]);

        $user = User::factory()->create();
        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);

        $account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $chatSession = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
        ]);

        $orchestrator = $this->createMock(MessengerRuntimeOrchestrator::class);
        $orchestrator->method('executeTurn')->willReturn([
            'status' => 'completed',
            'text' => 'All done!',
        ]);

        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(false);
        $adapter->shouldReceive('sendMessage')->andReturn(
            new ProviderResponse(success: true, providerMessageId: 'msg-1')
        );

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ProcessRuntimeTurnJob(
            runtimeSessionId: $session->id,
            userMessage: 'Quick task',
            chatSessionId: $chatSession->id,
            connectorAccountId: $account->id,
        );

        $job->handle($orchestrator, $connectorManager, app(\App\Services\Messenger\CompactionService::class));

        Bus::assertNotDispatched(ResumeRuntimeTurnJob::class);
    }
}
