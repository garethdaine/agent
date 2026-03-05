<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Adapters;

use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Support\Messenger\Adapters\SlackAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackAdapterTest extends TestCase
{
    use RefreshDatabase;

    private SlackAdapter $adapter;

    private ConnectorAccount $account;

    private string $signingSecret = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new SlackAdapter;
        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => 'xoxb-test-slack-bot-token-123456',
                'signing_secret' => $this->signingSecret,
            ],
            'config' => [
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => $this->signingSecret,
                ],
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'max_age_seconds' => 300,
                ],
                'threading' => 'native',
                'threading_fallback' => 'edit',
            ],
        ]);
    }

    public function test_send_message_posts_to_chat_post_message(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => 'C0123456789',
                'ts' => '1678901234.567890',
                'message' => [
                    'text' => 'Hello from the bot!',
                    'ts' => '1678901234.567890',
                ],
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Hello from the bot!',
            channelId: 'C0123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);
        $this->assertEquals('1678901234.567890', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === 'C0123456789'
                && $request['text'] === 'Hello from the bot!';
        });
    }

    public function test_send_message_stores_last_bot_message_id(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1678901234.567890',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: 'C0123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $this->adapter->sendMessage($session, $payload);

        $cacheKey = sprintf('slack:last_bot_message:%s:%s', $session->connector_account_id, $session->channel_id);
        $this->assertEquals('1678901234.567890', Cache::get($cacheKey));
    }

    public function test_send_message_includes_thread_ts_when_provided(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1678901234.999999',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Reply in thread',
            channelId: 'C0123456789',
            threadId: '1678901234.000001',
            replyToMessageId: null,
            attachmentIds: [],
        );

        $this->adapter->sendMessage($session, $payload);

        Http::assertSent(function ($request) {
            return $request['thread_ts'] === '1678901234.000001';
        });
    }

    public function test_supports_message_editing_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsMessageEditing());
    }

    public function test_edit_message_calls_chat_update(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.update' => Http::response([
                'ok' => true,
                'channel' => 'C0123456789',
                'ts' => '1678901234.567890',
                'text' => 'Updated content',
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '1678901234.567890', 'Updated content');

        $this->assertTrue($result->success);
        $this->assertEquals('1678901234.567890', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/chat.update'
                && $request['channel'] === 'C0123456789'
                && $request['ts'] === '1678901234.567890'
                && $request['text'] === 'Updated content';
        });
    }

    public function test_edit_message_returns_failure_on_api_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.update' => Http::response([
                'ok' => false,
                'error' => 'message_not_found',
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '9999999999.999999', 'Updated');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('message_not_found', $result->error);
    }

    public function test_edit_message_normalizes_escaped_newlines(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.update' => Http::response([
                'ok' => true,
                'ts' => '1678901234.567890',
            ], 200),
        ]);

        $this->adapter->editMessage($session, '1678901234.567890', 'Line 1\nLine 2');

        Http::assertSent(function ($request) {
            return $request['text'] === "Line 1\nLine 2";
        });
    }

    public function test_edit_message_treats_not_modified_as_success(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.update' => Http::response([
                'ok' => false,
                'error' => 'not_modified',
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '1678901234.567890', 'Same content');

        $this->assertTrue($result->success);
        $this->assertEquals('1678901234.567890', $result->providerMessageId);
    }

    public function test_supports_reactions_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsReactions());
    }

    public function test_add_reaction_calls_reactions_add(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/reactions.add' => Http::response([
                'ok' => true,
            ], 200),
        ]);

        $result = $this->adapter->addReaction($session, '1678901234.567890', 'eyes');

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/reactions.add'
                && $request['channel'] === 'C0123456789'
                && $request['timestamp'] === '1678901234.567890'
                && $request['name'] === 'eyes';
        });
    }

    public function test_threading_strategy_returns_native(): void
    {
        $this->assertEquals(ThreadingStrategy::Native, $this->adapter->getThreadingStrategy());
    }

    public function test_supports_threading_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsThreading());
    }

    public function test_replay_protection_strategy_returns_timestamp(): void
    {
        $this->assertEquals(
            ReplayProtectionStrategy::Timestamp,
            $this->adapter->getReplayProtectionStrategy()
        );
    }

    public function test_verify_webhook_signature_with_valid_hmac(): void
    {
        $timestamp = (string) time();
        $body = '{"event":{"type":"message","text":"hello"}}';
        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $expectedSignature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->signingSecret);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SLACK_SIGNATURE' => $expectedSignature,
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        ], $body);
        $request->attributes->set('connector_account', $this->account);

        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_rejects_invalid_signature(): void
    {
        $request = new Request([], [], [], [], [], [], '{"test": true}');
        $request->headers->set('X-Slack-Signature', 'v0=invalidsignature');
        $request->headers->set('X-Slack-Request-Timestamp', (string) time());
        $request->attributes->set('connector_account', $this->account);

        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_rejects_missing_headers(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);

        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertFalse($result);
    }

    public function test_parse_inbound_message_extracts_event_data(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'event_id' => 'Ev0123456789',
            'event' => [
                'type' => 'message',
                'user' => 'U0123456789',
                'channel' => 'C0123456789',
                'text' => 'Hello bot!',
                'ts' => '1678901234.567890',
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('U0123456789', $normalized->providerUserId);
        $this->assertEquals('C0123456789', $normalized->channelId);
        $this->assertEquals('Hello bot!', $normalized->content);
        $this->assertEquals('1678901234.567890', $normalized->providerMessageId);
        $this->assertEquals('Ev0123456789', $normalized->providerEventId);
    }

    public function test_parse_inbound_message_extracts_thread_ts(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'event' => [
                'type' => 'message',
                'user' => 'U0123456789',
                'channel' => 'C0123456789',
                'text' => 'Thread reply',
                'ts' => '1678901234.999999',
                'thread_ts' => '1678901234.000001',
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('1678901234.000001', $normalized->threadId);
    }

    public function test_parse_inbound_message_extracts_file_attachments(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'event' => [
                'type' => 'message',
                'user' => 'U0123456789',
                'channel' => 'C0123456789',
                'text' => 'Check this file',
                'ts' => '1678901234.567890',
                'files' => [
                    [
                        'id' => 'F0123456789',
                        'name' => 'report.pdf',
                        'mimetype' => 'application/pdf',
                        'size' => 25000,
                        'url_private_download' => 'https://files.slack.com/files-pri/T0/F0/report.pdf',
                    ],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $normalized->attachments);
        $this->assertEquals('F0123456789', $normalized->attachments[0]->providerFileId);
        $this->assertEquals('report.pdf', $normalized->attachments[0]->filename);
        $this->assertEquals('application/pdf', $normalized->attachments[0]->mimeType);
    }

    public function test_send_message_handles_api_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
                'error' => 'channel_not_found',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Test',
            channelId: 'C0123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('channel_not_found', $result->error);
    }

    public function test_send_message_normalizes_escaped_newlines(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => 'C0123456789',
        ]);

        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1678901234.567890',
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: '**Job Management:**\n- List jobs\n- Create jobs',
            channelId: 'C0123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $this->adapter->sendMessage($session, $payload);

        Http::assertSent(function ($request) {
            return $request['text'] === "**Job Management:**\n- List jobs\n- Create jobs";
        });
    }
}
