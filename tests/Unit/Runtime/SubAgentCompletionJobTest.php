<?php

namespace Tests\Unit\Runtime;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\SubAgentCompletionJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubAgentCompletionJobTest extends TestCase
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
            'title' => 'Cover Letter Writer',
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function test_sends_success_announcement_to_channel(): void
    {
        $sentContent = null;
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($session, OutboundPayload $payload) use (&$sentContent) {
                $sentContent = $payload->content;

                return new ProviderResponse(success: true, providerMessageId: 'msg-1');
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new SubAgentCompletionJob(
            childSessionId: $this->childSession->id,
            parentSessionId: $this->parentSession->id,
            status: 'completed',
            text: 'Cover letter has been drafted and saved.',
            label: 'Cover Letter Writer',
        );

        $job->handle($connectorManager);

        $this->assertNotNull($sentContent);
        $this->assertStringContainsString('Cover Letter Writer', $sentContent);
        $this->assertStringContainsString('completed', $sentContent);
        $this->assertStringContainsString('Cover letter has been drafted', $sentContent);
    }

    public function test_sends_failure_announcement_to_channel(): void
    {
        $sentContent = null;
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($session, OutboundPayload $payload) use (&$sentContent) {
                $sentContent = $payload->content;

                return new ProviderResponse(success: true, providerMessageId: 'msg-2');
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new SubAgentCompletionJob(
            childSessionId: $this->childSession->id,
            parentSessionId: $this->parentSession->id,
            status: 'failed',
            error: 'Process timed out.',
            label: 'Cover Letter Writer',
        );

        $job->handle($connectorManager);

        $this->assertNotNull($sentContent);
        $this->assertStringContainsString('failed', $sentContent);
        $this->assertStringContainsString('timed out', $sentContent);
    }

    public function test_updates_child_session_status_to_stopped(): void
    {
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('sendMessage')->andReturn(
            new ProviderResponse(success: true, providerMessageId: 'msg-3')
        );

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new SubAgentCompletionJob(
            childSessionId: $this->childSession->id,
            parentSessionId: $this->parentSession->id,
            status: 'completed',
            text: 'Done.',
            label: 'Cover Letter Writer',
        );

        $job->handle($connectorManager);

        $this->childSession->refresh();
        $this->assertEquals(RuntimeSessionStatus::Stopped, $this->childSession->status);
        $this->assertNotNull($this->childSession->ended_at);
    }

    public function test_queued_on_messenger_default(): void
    {
        $job = new SubAgentCompletionJob(
            childSessionId: $this->childSession->id,
            parentSessionId: $this->parentSession->id,
            status: 'completed',
            text: 'Done.',
        );

        $this->assertEquals('messenger-default', $job->queue);
    }

    public function test_truncates_long_text_in_announcement(): void
    {
        $longText = str_repeat('x', 3000);
        $sentContent = null;
        $adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $adapter->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($session, OutboundPayload $payload) use (&$sentContent) {
                $sentContent = $payload->content;

                return new ProviderResponse(success: true, providerMessageId: 'msg-4');
            });

        $connectorManager = $this->createMock(ConnectorManager::class);
        $connectorManager->method('resolve')->willReturn($adapter);

        $job = new SubAgentCompletionJob(
            childSessionId: $this->childSession->id,
            parentSessionId: $this->parentSession->id,
            status: 'completed',
            text: $longText,
            label: 'Long Output',
        );

        $job->handle($connectorManager);

        $this->assertLessThanOrEqual(2200, strlen($sentContent));
    }
}
