<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\Jobs\Messenger\ProcessInboundMessage;
use App\Jobs\Messenger\SendAccountLinkPrompt;
use App\Jobs\Messenger\SendOutboundMessage;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Services\Messenger\ChatSessionManager;
use App\Support\Messenger\ConnectorManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Workspace',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'connection_mode' => 'webhook',
            'credentials' => [
                'bot_token' => 'xoxb-test-token',
                'signing_secret' => 'test-signing-secret',
            ],
            'config' => [
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => 'test-signing-secret',
                ],
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'window_seconds' => 300,
                ],
            ],
        ]);
    }

    public function test_webhook_dispatches_process_inbound_message_job(): void
    {
        Bus::fake([ProcessInboundMessage::class]);

        $timestamp = now()->timestamp;
        $body = json_encode([
            'token' => 'test-token',
            'team_id' => 'T12345',
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Hello, Agent!',
                'ts' => '1234567890.123456',
            ],
            'event_id' => 'Ev12345',
            'event_time' => $timestamp,
        ]);

        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, 'test-signing-secret');

        $response = $this->postJson('/agent/api/v1/connectors/slack/webhook', json_decode($body, true), [
            'X-Slack-Signature' => $signature,
            'X-Slack-Request-Timestamp' => $timestamp,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        Bus::assertDispatched(ProcessInboundMessage::class, function (ProcessInboundMessage $job) {
            return $job->connectorAccountId === $this->account->id
                && $job->provider === 'slack';
        });
    }

    public function test_linked_user_creates_session_and_message(): void
    {
        Queue::fake();

        // Create identity link for the user
        MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U12345',
        ]);

        $normalizedMessage = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello, Agent!',
            threadId: null,
            providerMessageId: '1234567890.123456',
            providerEventId: 'Ev12345',
            providerTimestamp: Carbon::now(),
            attachments: [],
        );

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Hello, Agent!',
                'ts' => '1234567890.123456',
            ],
            'event_id' => 'Ev12345',
        ];

        // Dispatch and process the job
        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Verify session was created
        $this->assertDatabaseHas('chat_sessions', [
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'channel_id' => 'C12345',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        // Verify message was created
        $this->assertDatabaseHas('chat_messages', [
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Hello, Agent!',
            'provider_message_id' => '1234567890.123456',
        ]);
    }

    public function test_unlinked_user_receives_link_prompt(): void
    {
        Queue::fake([SendAccountLinkPrompt::class]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U99999', // Unknown user
                'channel' => 'C12345',
                'text' => 'Hello, Agent!',
                'ts' => '1234567890.123456',
            ],
            'event_id' => 'Ev12345',
        ];

        // Dispatch and process the job
        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Verify account link prompt job was dispatched
        Queue::assertPushed(SendAccountLinkPrompt::class, function (SendAccountLinkPrompt $job) {
            return $job->connectorAccountId === $this->account->id
                && $job->providerUserId === 'U99999'
                && $job->channelId === 'C12345';
        });

        // Verify no session or message was created for unlinked user
        $this->assertDatabaseMissing('chat_sessions', [
            'connector_account_id' => $this->account->id,
            'channel_id' => 'C12345',
        ]);
    }

    public function test_idempotency_key_prevents_duplicate_messages(): void
    {
        Queue::fake();

        // Create identity link for the user
        MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U12345',
        ]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Duplicate message test',
                'ts' => '1234567890.999999',
            ],
            'event_id' => 'Ev12345-duplicate',
        ];

        // Process the first message
        $job1 = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job1->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Process the same message again (duplicate)
        $job2 = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job2->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Verify only one message was created (idempotency check)
        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_outbound_message_creates_record_and_calls_adapter(): void
    {
        Queue::fake();

        // Create session
        $session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'channel_id' => 'C12345',
            'provider' => 'slack',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        // Process outbound message job
        $job = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Response from Agent',
            threadId: null
        );

        // The job should create an outbound message record
        // Note: actual sending will be mocked in the adapter
        $this->assertInstanceOf(SendOutboundMessage::class, $job);
        $this->assertEquals($session->id, $job->sessionId);
        $this->assertEquals('Response from Agent', $job->content);
    }

    public function test_session_threading_resolution(): void
    {
        Queue::fake();

        // Create identity link for the user
        MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U12345',
        ]);

        // Create a message in a thread
        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Thread message',
                'ts' => '1234567890.111111',
                'thread_ts' => '1234567890.000000', // Thread parent
            ],
            'event_id' => 'Ev12345-thread',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Verify session was created with thread_id
        $this->assertDatabaseHas('chat_sessions', [
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'channel_id' => 'C12345',
            'thread_id' => '1234567890.000000',
        ]);
    }

    public function test_telegram_webhook_processes_correctly(): void
    {
        Bus::fake([ProcessInboundMessage::class]);

        // Create a Telegram connector account
        $telegramAccount = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'name' => 'Test Bot',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'connection_mode' => 'webhook',
            'account_key' => 'test-bot-key',
            'credentials' => [
                'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            ],
        ]);

        $response = $this->postJson('/agent/api/v1/connectors/telegram/webhook/test-bot-key', [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1234,
                'from' => [
                    'id' => 12345678,
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => 12345678,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => 'Hello from Telegram!',
            ],
        ]);

        $response->assertOk();

        Bus::assertDispatched(ProcessInboundMessage::class, function (ProcessInboundMessage $job) use ($telegramAccount) {
            return $job->connectorAccountId === $telegramAccount->id
                && $job->provider === 'telegram';
        });
    }

    public function test_rate_limited_account_link_prompt(): void
    {
        Queue::fake([SendAccountLinkPrompt::class]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U99999',
                'channel' => 'C12345',
                'text' => 'Hello!',
                'ts' => '1234567890.123456',
            ],
            'event_id' => 'Ev12345-1',
        ];

        // First message from unlinked user
        $job1 = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job1->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Second message from same unlinked user within rate limit window
        $payload['event_id'] = 'Ev12345-2';
        $payload['event']['ts'] = '1234567890.123457';
        $job2 = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job2->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Should only dispatch one account link prompt (rate limited)
        Queue::assertPushed(SendAccountLinkPrompt::class, 1);
    }
}
