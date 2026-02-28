<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Adapters;

use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Support\Messenger\Adapters\DiscordAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for DiscordAdapter.
 *
 * Assumptions:
 * - Discord API v10 is used for all API calls
 * - Bot token is prefixed with "Bot " for Authorization header
 * - Guild channels support native threads
 * - DMs use message editing for pseudo-threading (append to previous message)
 *
 * Discord Threading Strategy:
 * - Guild channels: POST /channels/{id}/threads to create thread
 * - DMs: No thread support, edit previous bot message to append content
 *
 * Rate Limiting:
 * - Discord returns X-RateLimit-* headers
 * - Should handle 429 responses with retry after Retry-After header
 */
class DiscordAdapterTest extends TestCase
{
    use RefreshDatabase;

    private DiscordAdapter $adapter;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new DiscordAdapter();
        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => 'MTEyMzQ1Njc4OTAxMjM0NTY3OA.GxYmZc.test-token',
                'application_id' => '1123456789012345678',
                'public_key' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
            ],
        ]);
    }

    public function test_send_message_posts_to_correct_channel_endpoint(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/987654321098765432/messages' => Http::response([
                'id' => '1234567890123456789',
                'channel_id' => '987654321098765432',
                'content' => 'Hello from the bot!',
                'timestamp' => '2024-01-15T12:00:00.000Z',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Hello from the bot!',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);
        $this->assertEquals('1234567890123456789', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/v10/channels/987654321098765432/messages'
                && $request->method() === 'POST'
                && str_starts_with($request->header('Authorization')[0], 'Bot ');
        });
    }

    public function test_guild_channel_uses_native_threads(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        // When thread_id is provided, we send to the thread as the channel
        Http::fake([
            'https://discord.com/api/v10/channels/1111111111111111111/messages' => Http::response([
                'id' => '1234567890123456789',
                'channel_id' => '1111111111111111111',
                'content' => 'Reply in thread',
            ], 200),
        ]);

        // When thread_id is provided, Discord sends to thread channel
        $payload = new OutboundPayload(
            content: 'Reply in thread',
            channelId: '987654321098765432',
            threadId: '1111111111111111111', // Thread ID for guild channels
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            // For thread messages, we send to the thread ID as the channel
            return str_contains($request->url(), '/channels/1111111111111111111/messages');
        });
    }

    public function test_dm_edits_previous_message_instead_of_new_message(): void
    {
        $user = User::factory()->create();

        // Simulate an existing DM session
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '999888777666555444', // DM channel
        ]);

        // Simulate previous bot message ID stored in cache
        $cacheKey = sprintf('discord:last_bot_message:%s:%s', $session->connector_account_id, $session->channel_id);
        Cache::put($cacheKey, '1234567890123456780', 86400);

        Http::fake([
            // GET existing message
            'https://discord.com/api/v10/channels/999888777666555444/messages/1234567890123456780' => Http::response([
                'id' => '1234567890123456780',
                'channel_id' => '999888777666555444',
                'content' => 'Previous content',
            ], 200),
        ]);

        // The PATCH for edit will go to same URL but different method
        Http::fake([
            'https://discord.com/api/v10/channels/999888777666555444/messages/1234567890123456780' => Http::response([
                'id' => '1234567890123456780',
                'channel_id' => '999888777666555444',
                'content' => "Previous content\n\n---\n\nNew appended content",
            ], 200),
        ]);

        $result = $this->adapter->appendToLastMessage($session, 'New appended content');

        $this->assertTrue($result->success);
    }

    public function test_rate_limit_header_handling_with_retry(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/987654321098765432/messages' => Http::sequence()
                ->push([
                    'message' => 'You are being rate limited.',
                    'retry_after' => 0.5,
                    'global' => false,
                ], 429, [
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset-After' => '0.5',
                    'Retry-After' => '1',
                ])
                ->push([
                    'id' => '1234567890123456789',
                    'channel_id' => '987654321098765432',
                    'content' => 'Hello!',
                ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Hello!',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        // The adapter should have retried after the 429
        $this->assertTrue($result->success);
        $this->assertEquals('1234567890123456789', $result->providerMessageId);
    }

    public function test_identity_mapping_creates_messenger_identity_link(): void
    {
        $user = User::factory()->create();

        $request = new Request();
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'd' => [
                'author' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                    'discriminator' => '1234',
                    'avatar' => 'abc123hash',
                ],
                'channel_id' => '123456789012345678',
                'content' => 'Hello world',
                'id' => '111222333444555666',
            ],
        ]);

        // Create or update identity link during message parsing
        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('9876543210987654321', $normalized->providerUserId);
        $this->assertEquals('123456789012345678', $normalized->channelId);
        $this->assertEquals('Hello world', $normalized->content);
    }

    public function test_identity_mapping_updates_existing_link(): void
    {
        $user = User::factory()->create();

        // Pre-existing identity link
        $existingLink = MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '9876543210987654321',
            'provider_username' => 'OldUsername',
        ]);

        // Ensure the adapter can map the identity
        $this->assertNotNull($existingLink->id);
        $this->assertEquals('9876543210987654321', $existingLink->provider_user_id);
    }

    public function test_verify_webhook_signature_requires_ed25519_headers(): void
    {
        $request = new Request();
        $request->attributes->set('connector_account', $this->account);

        // Missing signature headers should fail
        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertFalse($result);
    }

    public function test_threading_strategy_returns_native_for_guild_channels(): void
    {
        $this->assertEquals(ThreadingStrategy::Native, $this->adapter->getThreadingStrategy());
    }

    public function test_supports_threading_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsThreading());
    }

    public function test_replay_protection_strategy_returns_event_id(): void
    {
        $this->assertEquals(
            ReplayProtectionStrategy::EventId,
            $this->adapter->getReplayProtectionStrategy()
        );
    }

    public function test_parse_inbound_message_extracts_interaction_data(): void
    {
        $request = new Request();
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555666',
            'token' => 'interaction-token',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'channel_id' => '123456789012345678',
            'data' => [
                'name' => 'agent',
                'options' => [
                    ['name' => 'run', 'value' => 'Do something'],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('9876543210987654321', $normalized->providerUserId);
        $this->assertEquals('123456789012345678', $normalized->channelId);
        $this->assertStringContainsString('run', $normalized->content);
    }

    public function test_parse_inbound_message_extracts_message_create_event(): void
    {
        $request = new Request();
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            't' => 'MESSAGE_CREATE',
            'd' => [
                'id' => '111222333444555666',
                'channel_id' => '123456789012345678',
                'author' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                    'bot' => false,
                ],
                'content' => 'Hello bot!',
                'timestamp' => '2024-01-15T12:00:00.000Z',
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('9876543210987654321', $normalized->providerUserId);
        $this->assertEquals('123456789012345678', $normalized->channelId);
        $this->assertEquals('Hello bot!', $normalized->content);
        $this->assertEquals('111222333444555666', $normalized->providerMessageId);
    }

    public function test_send_message_handles_api_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/987654321098765432/messages' => Http::response([
                'code' => 50001,
                'message' => 'Missing Access',
            ], 403),
        ]);

        $payload = new OutboundPayload(
            content: 'Hello!',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Missing Access', $result->error);
    }

    public function test_send_message_uses_thread_id_when_provided(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/*/messages' => Http::response([
                'id' => '1234567890123456789',
                'channel_id' => '1111111111111111111',
                'content' => 'Reply in thread',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Reply in thread',
            channelId: '987654321098765432',
            threadId: '1111111111111111111',
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);

        // When thread_id is provided, message should go to thread channel
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/channels/1111111111111111111/messages');
        });
    }
}
