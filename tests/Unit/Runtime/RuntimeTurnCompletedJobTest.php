<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\RuntimeTurnCompletedJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RuntimeTurnCompletedJobTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $account;

    private ChatSession $chatSession;

    private RuntimeSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        config(['messenger.compaction.enabled' => false]);

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

    public function test_completed_result_sends_text_to_channel(): void
    {
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

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->session->id,
            result: ['status' => 'completed', 'text' => 'The task is done. Here are the results.'],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertNotNull($sentContent);
        $this->assertStringContainsString('The task is done', $sentContent);
    }

    public function test_failed_result_sends_error_with_partial_text(): void
    {
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

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->session->id,
            result: [
                'status' => 'failed',
                'error' => 'Request timed out after 1800s.',
                'text' => 'Partial work done so far...',
            ],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $job->handle($connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertNotNull($sentContent);
        $this->assertStringContainsString('timed out', $sentContent);
        $this->assertStringContainsString('Partial work done', $sentContent);
    }

    public function test_completed_result_edits_placeholder_when_supported(): void
    {
        $editedContent = null;
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('supportsMessageEditing')->andReturn(true);
        $adapter->shouldReceive('sendMessage')->once()->andReturn(new ProviderResponse(success: true, providerMessageId: 'msg-3'));
        $adapter->shouldReceive('editMessage')
            ->once()
            ->andReturnUsing(function ($session, string $msgId, string $content) use (&$editedContent) {
                $editedContent = $content;

                return new ProviderResponse(success: true, providerMessageId: $msgId);
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->session->id,
            result: ['status' => 'completed', 'text' => 'Done!'],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
            placeholderMessageId: 'placeholder-123',
        );

        $job->handle($connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertEquals('✅ Done', $editedContent);
    }

    public function test_job_queued_on_messenger_default(): void
    {
        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->session->id,
            result: ['status' => 'completed', 'text' => 'Done!'],
            chatSessionId: $this->chatSession->id,
            connectorAccountId: $this->account->id,
        );

        $this->assertEquals('messenger-default', $job->queue);
    }

    public function test_job_handles_missing_chat_session_gracefully(): void
    {
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldNotReceive('sendMessage');

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new RuntimeTurnCompletedJob(
            runtimeSessionId: $this->session->id,
            result: ['status' => 'completed', 'text' => 'Done!'],
            chatSessionId: null,
            connectorAccountId: $this->account->id,
        );

        $job->handle($connectorManager, app(\App\Services\Messenger\CompactionService::class));

        $this->assertTrue(true);
    }
}
