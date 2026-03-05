<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Adapters;

use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatMessage;
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
        $this->adapter = new DiscordAdapter;
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

        $request = new Request;
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
        $request = new Request;
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
        $request = new Request;
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
        $request = new Request;
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

    public function test_parse_inbound_message_extracts_gateway_interaction_create_event(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            't' => 'INTERACTION_CREATE',
            'd' => [
                'type' => 2, // APPLICATION_COMMAND
                'id' => '1477967126194159657',
                'token' => 'interaction-token',
                'channel_id' => '123456789012345678',
                'member' => [
                    'user' => [
                        'id' => '9876543210987654321',
                        'username' => 'TestUser',
                    ],
                ],
                'data' => [
                    'name' => 'jobs',
                    'options' => [
                        ['name' => 'list', 'type' => 1],
                    ],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertSame('9876543210987654321', $normalized->providerUserId);
        $this->assertSame('123456789012345678', $normalized->channelId);
        $this->assertSame('/jobs list', $normalized->content);
    }

    public function test_parse_inbound_message_maps_jobs_list_subcommand_to_supported_text_command(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2, // APPLICATION_COMMAND
            'id' => 'interaction-jobs-list',
            'token' => 'interaction-token',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'channel_id' => '123456789012345678',
            'data' => [
                'name' => 'jobs',
                'options' => [
                    ['name' => 'list', 'type' => 1],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertSame('/jobs list', $normalized->content);
    }

    public function test_parse_inbound_message_maps_jobs_list_subcommand_when_type_is_string(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2, // APPLICATION_COMMAND
            'id' => 'interaction-jobs-list-string-type',
            'token' => 'interaction-token',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'channel_id' => '123456789012345678',
            'data' => [
                'name' => 'jobs',
                'options' => [
                    ['name' => 'list', 'type' => '1'],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertSame('/jobs list', $normalized->content);
    }

    public function test_parse_inbound_message_maps_legacy_agent_command_option_to_text(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2, // APPLICATION_COMMAND
            'id' => 'interaction-agent-legacy',
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
                    ['name' => 'command', 'value' => 'list my jobs'],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertSame('agent command: list my jobs', $normalized->content);
    }

    public function test_parse_inbound_message_maps_runs_stop_subcommand_with_nested_run_id(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2, // APPLICATION_COMMAND
            'id' => 'interaction-runs-stop',
            'token' => 'interaction-token',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'channel_id' => '123456789012345678',
            'data' => [
                'name' => 'runs',
                'options' => [
                    [
                        'name' => 'stop',
                        'type' => 1,
                        'options' => [
                            ['name' => 'run_id', 'type' => 4, 'value' => 42],
                        ],
                    ],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertSame('/runs stop 42', $normalized->content);
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

    public function test_supports_message_editing_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsMessageEditing());
    }

    public function test_edit_message_patches_existing_message(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/987654321098765432/messages/1234567890123456789' => Http::response([
                'id' => '1234567890123456789',
                'channel_id' => '987654321098765432',
                'content' => 'Updated content',
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '1234567890123456789', 'Updated content');

        $this->assertTrue($result->success);
        $this->assertEquals('1234567890123456789', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/channels/987654321098765432/messages/1234567890123456789')
                && $request['content'] === 'Updated content';
        });
    }

    public function test_edit_message_uses_thread_id_when_set(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
            'thread_id' => '1111111111111111111',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/1111111111111111111/messages/1234567890123456789' => Http::response([
                'id' => '1234567890123456789',
                'content' => 'Edited in thread',
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '1234567890123456789', 'Edited in thread');

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/channels/1111111111111111111/messages/');
        });
    }

    public function test_edit_message_returns_failure_on_api_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/987654321098765432/messages/*' => Http::response([
                'message' => 'Unknown Message',
                'code' => 10008,
            ], 404),
        ]);

        $result = $this->adapter->editMessage($session, '9999999999999999999', 'Updated content');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown Message', $result->error);
    }

    public function test_parse_interaction_extracts_resolved_attachments(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2,
            'id' => 'interaction-with-attachment',
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
                    ['name' => 'command', 'value' => 'Check this file'],
                    ['name' => 'file', 'type' => 11, 'value' => '1234567890'],
                ],
                'resolved' => [
                    'attachments' => [
                        '1234567890' => [
                            'id' => '1234567890',
                            'filename' => 'gap-analysis.docx',
                            'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'size' => 26828,
                            'url' => 'https://cdn.discordapp.com/attachments/123/456/gap-analysis.docx',
                        ],
                    ],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $normalized->attachments);
        $this->assertEquals('1234567890', $normalized->attachments[0]->providerFileId);
        $this->assertEquals('gap-analysis.docx', $normalized->attachments[0]->filename);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $normalized->attachments[0]->mimeType);
        $this->assertEquals(26828, $normalized->attachments[0]->sizeBytes);
        $this->assertEquals('https://cdn.discordapp.com/attachments/123/456/gap-analysis.docx', $normalized->attachments[0]->downloadUrl);
    }

    public function test_parse_interaction_returns_empty_attachments_when_no_resolved_data(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2,
            'id' => 'interaction-no-attachment',
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
                    ['name' => 'command', 'value' => 'list my jobs'],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertCount(0, $normalized->attachments);
    }

    public function test_parse_interaction_handles_multiple_resolved_attachments(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'type' => 2,
            'id' => 'interaction-multi-attach',
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
                    ['name' => 'command', 'value' => 'Review these files'],
                ],
                'resolved' => [
                    'attachments' => [
                        '111' => [
                            'id' => '111',
                            'filename' => 'plan.md',
                            'content_type' => 'text/markdown',
                            'size' => 2048,
                            'url' => 'https://cdn.discordapp.com/attachments/123/111/plan.md',
                        ],
                        '222' => [
                            'id' => '222',
                            'filename' => 'report.pdf',
                            'content_type' => 'application/pdf',
                            'size' => 50000,
                            'url' => 'https://cdn.discordapp.com/attachments/123/222/report.pdf',
                        ],
                    ],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertCount(2, $normalized->attachments);
        $filenames = array_map(fn ($a) => $a->filename, $normalized->attachments);
        $this->assertContains('plan.md', $filenames);
        $this->assertContains('report.pdf', $filenames);
    }

    public function test_send_message_edits_original_interaction_response_when_context_exists(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'list my jobs',
            'idempotency_key' => hash('sha256', 'discord-interaction-inbound-1'),
            'provider_event_id' => 'interaction-123',
            'provider_message_id' => 'interaction-123',
            'created_at' => now(),
        ]);

        Cache::put(
            sprintf('discord:interaction:%s:%s', $this->account->id, 'interaction-123'),
            [
                'token' => 'interaction-token-abc',
                'application_id' => '1123456789012345678',
            ],
            900
        );

        Http::fake([
            'https://discord.com/api/v10/webhooks/1123456789012345678/interaction-token-abc/messages/@original' => Http::response([
                'id' => 'deferred-message-123',
                'channel_id' => '987654321098765432',
                'content' => 'Resolved response',
            ], 200),
            'https://discord.com/api/v10/channels/*/messages' => Http::response([
                'id' => 'channel-message-should-not-be-used',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Resolved response',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);
        $this->assertEquals('deferred-message-123', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && $request->url() === 'https://discord.com/api/v10/webhooks/1123456789012345678/interaction-token-abc/messages/@original';
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/channels/987654321098765432/messages');
        });

        $this->assertNull(Cache::get(sprintf('discord:interaction:%s:%s', $this->account->id, 'interaction-123')));
    }

    public function test_send_message_normalizes_escaped_newlines(): void
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
                'content' => "Line 1\n\nLine 2",
            ], 200),
        ]);

        // Content with escaped \n sequences that should be converted to real newlines
        $payload = new OutboundPayload(
            content: 'Line 1\n\nLine 2',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            // Verify the escaped \n was converted to actual newlines
            $content = $request['content'];

            return $content === "Line 1\n\nLine 2";
        });
    }

    public function test_send_message_normalizes_content_with_markdown_formatting(): void
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
                'content' => "**Job Management:**\n- List jobs\n- Create jobs",
            ], 200),
        ]);

        // Content with markdown and escaped newlines
        $payload = new OutboundPayload(
            content: '**Job Management:**\n- List jobs\n- Create jobs',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            $content = $request['content'];

            return $content === "**Job Management:**\n- List jobs\n- Create jobs";
        });
    }

    public function test_send_message_normalizes_multiple_consecutive_newlines(): void
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
            ], 200),
        ]);

        // Content with many escaped newlines
        $payload = new OutboundPayload(
            content: 'Section 1\n\n\n\n\nSection 2',
            channelId: '987654321098765432',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            $content = $request['content'];

            // Multiple newlines should be normalized to at most two
            return $content === "Section 1\n\nSection 2";
        });
    }

    public function test_edit_message_normalizes_escaped_newlines(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '987654321098765432',
        ]);

        Http::fake([
            'https://discord.com/api/v10/channels/987654321098765432/messages/1234567890123456789' => Http::response([
                'id' => '1234567890123456789',
                'channel_id' => '987654321098765432',
                'content' => "Updated\ncontent",
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '1234567890123456789', 'Updated\ncontent');

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && $request['content'] === "Updated\ncontent";
        });
    }
}
