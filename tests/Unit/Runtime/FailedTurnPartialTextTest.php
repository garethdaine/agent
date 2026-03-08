<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FailedTurnPartialTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['messenger.compaction.enabled' => false]);
    }

    public function test_failed_turn_includes_partial_text_in_channel_message(): void
    {
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
            'status' => 'failed',
            'error' => 'Request timed out after 300s.',
            'text' => 'I was working on reading the job description and had drafted a cover letter...',
        ]);

        $sentContent = null;
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(false);
        $adapter->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($session, OutboundPayload $payload) use (&$sentContent) {
                $sentContent = $payload->content;

                return new ProviderResponse(success: true, providerMessageId: 'msg-1');
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ProcessRuntimeTurnJob(
            runtimeSessionId: $session->id,
            userMessage: 'Apply for this position',
            chatSessionId: $chatSession->id,
            connectorAccountId: $account->id,
        );

        $job->handle($orchestrator, $connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertNotNull($sentContent);
        $this->assertStringContainsString('timed out', $sentContent);
        $this->assertStringContainsString('cover letter', $sentContent);
    }

    public function test_failed_turn_without_partial_text_sends_error_only(): void
    {
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
            'status' => 'failed',
            'error' => 'Failed to start wrapper process.',
        ]);

        $sentContent = null;
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(false);
        $adapter->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($session, OutboundPayload $payload) use (&$sentContent) {
                $sentContent = $payload->content;

                return new ProviderResponse(success: true, providerMessageId: 'msg-2');
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new ProcessRuntimeTurnJob(
            runtimeSessionId: $session->id,
            userMessage: 'Hello',
            chatSessionId: $chatSession->id,
            connectorAccountId: $account->id,
        );

        $job->handle($orchestrator, $connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertNotNull($sentContent);
        $this->assertStringContainsString('Failed to start wrapper', $sentContent);
        $this->assertStringNotContainsString('Partial progress', $sentContent);
    }
}
