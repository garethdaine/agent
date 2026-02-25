<?php

namespace Tests\Feature\Messenger;

use App\DTOs\Messenger\OutboundPayload;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Support\Messenger\Adapters\SlackAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackAdapterTest extends TestCase
{
    use RefreshDatabase;

    private SlackAdapter $adapter;

    private ConnectorAccount $account;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new SlackAdapter;

        $this->account = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Slack Workspace',
            'credentials' => [
                'signing_secret' => 'test_signing_secret_12345',
                'bot_token' => 'xoxb-test-bot-token-12345',
            ],
            'webhook_secret' => 'test_webhook_secret',
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => 'test_signing_secret_12345',
                ],
            ],
            'account_key' => 'T12345678',
        ]);

        $this->user = User::factory()->create();
    }

    public function test_parses_basic_message_event(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => 'Ev12345',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Hello, bot!',
                'ts' => '1234567890.123456',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('U12345678', $message->providerUserId);
        $this->assertEquals('C12345678', $message->channelId);
        $this->assertEquals('Hello, bot!', $message->content);
        $this->assertEquals('1234567890.123456', $message->providerMessageId);
        $this->assertEquals('Ev12345', $message->providerEventId);
        $this->assertNull($message->threadId);
    }

    public function test_parses_threaded_message(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => 'Ev12345',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Reply in thread',
                'ts' => '1234567890.123456',
                'thread_ts' => '1234567890.000001',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('1234567890.000001', $message->threadId);
        $this->assertEquals('Reply in thread', $message->content);
    }

    public function test_parses_app_mention_event(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => 'Ev12345',
            'event' => [
                'type' => 'app_mention',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => '<@U_BOT_ID> help me',
                'ts' => '1234567890.123456',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('<@U_BOT_ID> help me', $message->content);
    }

    public function test_parses_message_with_blocks(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => 'Ev12345',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Plain text fallback',
                'ts' => '1234567890.123456',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => 'This is *bold* text from blocks',
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('This is *bold* text from blocks', $message->content);
    }

    public function test_parses_message_with_rich_text_blocks(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => 'Ev12345',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Fallback',
                'ts' => '1234567890.123456',
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'elements' => [
                            [
                                'type' => 'rich_text_section',
                                'elements' => [
                                    ['type' => 'text', 'text' => 'Hello '],
                                    ['type' => 'text', 'text' => 'World'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('Hello World', $message->content);
    }

    public function test_parses_message_with_file_attachments(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => 'Ev12345',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Here is a file',
                'ts' => '1234567890.123456',
                'files' => [
                    [
                        'id' => 'F12345678',
                        'name' => 'screenshot.png',
                        'mimetype' => 'image/png',
                        'size' => 102400,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345678-F12345678/screenshot.png',
                    ],
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $message->attachments);
        $this->assertEquals('F12345678', $message->attachments[0]->providerFileId);
        $this->assertEquals('screenshot.png', $message->attachments[0]->filename);
        $this->assertEquals('image/png', $message->attachments[0]->mimeType);
        $this->assertEquals(102400, $message->attachments[0]->sizeBytes);
    }

    public function test_sends_basic_message(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1234567890.123456',
                'channel' => 'C12345678',
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C12345678',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Hello from bot!',
            channelId: 'C12345678',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);
        $this->assertEquals('1234567890.123456', $response->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === 'C12345678'
                && $request['text'] === 'Hello from bot!'
                && str_contains($request->header('Authorization')[0], 'Bearer xoxb-test-bot-token-12345');
        });
    }

    public function test_sends_message_with_threading(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1234567890.654321',
                'channel' => 'C12345678',
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C12345678',
            'thread_id' => '1234567890.000001',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Reply in thread',
            channelId: 'C12345678',
            threadId: '1234567890.000001',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);

        Http::assertSent(function ($request) {
            return $request['thread_ts'] === '1234567890.000001';
        });
    }

    public function test_sends_message_with_reply_to(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1234567890.654321',
                'channel' => 'C12345678',
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C12345678',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Reply to message',
            channelId: 'C12345678',
            replyToMessageId: '1234567890.111111',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);

        Http::assertSent(function ($request) {
            return $request['thread_ts'] === '1234567890.111111';
        });
    }

    public function test_handles_slack_api_error_response(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
                'error' => 'channel_not_found',
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C_INVALID',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: 'C_INVALID',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($response->success);
        $this->assertEquals('channel_not_found', $response->error);
    }

    public function test_handles_rate_limit_with_retry(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response(['ok' => false, 'error' => 'rate_limited'], 429, [
                    'Retry-After' => '1',
                ]);
            }

            return Http::response([
                'ok' => true,
                'ts' => '1234567890.123456',
                'channel' => 'C12345678',
            ]);
        });

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C12345678',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: 'C12345678',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);
        $this->assertEquals(2, $callCount);
    }

    public function test_signature_verification_with_valid_signature(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345678']);
        $signingSecret = 'test_signing_secret_12345';

        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $signingSecret);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
        ], $body);
        $request->attributes->set('connector_account', $this->account);

        $this->assertTrue($this->adapter->verifyWebhookSignature($request));
    }

    public function test_signature_verification_fails_with_invalid_signature(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345678']);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => 'v0=invalid_signature',
        ], $body);
        $request->attributes->set('connector_account', $this->account);

        $this->assertFalse($this->adapter->verifyWebhookSignature($request));
    }

    public function test_signature_verification_fails_without_headers(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'type' => 'event_callback',
        ]);
        $request->attributes->set('connector_account', $this->account);

        $this->assertFalse($this->adapter->verifyWebhookSignature($request));
    }

    public function test_send_message_fails_without_bot_token(): void
    {
        $accountWithoutToken = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Slack Without Token',
            'credentials' => [
                'signing_secret' => 'test_signing_secret_12345',
                // No bot_token
            ],
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'account_key' => 'T_NO_TOKEN',
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $accountWithoutToken->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C12345678',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: 'C12345678',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($response->success);
        $this->assertEquals('Missing bot token', $response->error);
    }

    public function test_threading_strategy_is_native(): void
    {
        $this->assertTrue($this->adapter->supportsThreading());
        $this->assertEquals(
            \App\DTOs\Messenger\ThreadingStrategy::Native,
            $this->adapter->getThreadingStrategy()
        );
    }

    public function test_replay_protection_strategy_is_timestamp(): void
    {
        $this->assertEquals(
            \App\DTOs\Messenger\ReplayProtectionStrategy::Timestamp,
            $this->adapter->getReplayProtectionStrategy()
        );
    }
}
