<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\DTOs\Messenger\OutboundPayload;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Support\Messenger\Adapters\TelegramAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAdapterTest extends TestCase
{
    use RefreshDatabase;

    private TelegramAdapter $adapter;

    private ConnectorAccount $account;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new TelegramAdapter;

        $this->account = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'name' => 'Test Telegram Bot',
            'credentials' => [
                'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            ],
            'webhook_secret' => 'my_secret_token',
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'signature_verification' => [
                    'scheme' => 'token',
                    'secret_token' => 'my_secret_token',
                ],
            ],
            'account_key' => 'telegram_bot_123',
        ]);

        $this->user = User::factory()->create();
    }

    public function test_parses_basic_message(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                    'title' => 'Test Group',
                ],
                'date' => 1640000000,
                'text' => 'Hello bot!',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('12345', $message->providerUserId);
        $this->assertEquals('-100123456', $message->channelId);
        $this->assertEquals('Hello bot!', $message->content);
        $this->assertEquals('42', $message->providerMessageId);
        $this->assertEquals('123456789', $message->providerEventId);
        $this->assertNull($message->threadId);
    }

    public function test_parses_reply_message_with_threading(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 43,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'text' => 'This is a reply',
                'reply_to_message' => [
                    'message_id' => 42,
                    'text' => 'Original message',
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('42', $message->threadId);
        $this->assertEquals('This is a reply', $message->content);
    }

    public function test_parses_edited_message(): void
    {
        $payload = [
            'update_id' => 123456789,
            'edited_message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'edit_date' => 1640000060,
                'text' => 'Edited message content',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('12345', $message->providerUserId);
        $this->assertEquals('Edited message content', $message->content);
    }

    public function test_parses_callback_query(): void
    {
        $payload = [
            'update_id' => 123456789,
            'callback_query' => [
                'id' => 'callback_123',
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'message' => [
                    'message_id' => 42,
                    'from' => [
                        'id' => 999999,
                        'is_bot' => true,
                        'first_name' => 'Bot',
                    ],
                    'chat' => [
                        'id' => -100123456,
                        'type' => 'group',
                    ],
                    'date' => 1640000000,
                    'text' => 'Select an option',
                ],
                'data' => 'option_1',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('-100123456', $message->channelId);
        $this->assertEquals('42', $message->providerMessageId);
    }

    public function test_parses_message_with_caption(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'caption' => 'Photo caption here',
                'photo' => [
                    ['file_id' => 'photo_small', 'file_size' => 1000, 'width' => 90, 'height' => 90],
                    ['file_id' => 'photo_large', 'file_size' => 50000, 'width' => 800, 'height' => 600],
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('Photo caption here', $message->content);
        $this->assertCount(1, $message->attachments);
        $this->assertEquals('photo_large', $message->attachments[0]->providerFileId);
    }

    public function test_parses_message_with_document(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'document' => [
                    'file_id' => 'doc_12345',
                    'file_name' => 'report.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 102400,
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $message->attachments);
        $this->assertEquals('doc_12345', $message->attachments[0]->providerFileId);
        $this->assertEquals('report.pdf', $message->attachments[0]->filename);
        $this->assertEquals('application/pdf', $message->attachments[0]->mimeType);
        $this->assertEquals(102400, $message->attachments[0]->sizeBytes);
    }

    public function test_parses_message_with_audio(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'audio' => [
                    'file_id' => 'audio_12345',
                    'file_name' => 'song.mp3',
                    'mime_type' => 'audio/mpeg',
                    'file_size' => 5000000,
                    'duration' => 180,
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $message->attachments);
        $this->assertEquals('audio_12345', $message->attachments[0]->providerFileId);
        $this->assertEquals('song.mp3', $message->attachments[0]->filename);
        $this->assertEquals('audio/mpeg', $message->attachments[0]->mimeType);
    }

    public function test_parses_message_with_voice(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'voice' => [
                    'file_id' => 'voice_12345',
                    'mime_type' => 'audio/ogg',
                    'file_size' => 50000,
                    'duration' => 10,
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $message->attachments);
        $this->assertEquals('voice_12345', $message->attachments[0]->providerFileId);
        $this->assertEquals('voice.ogg', $message->attachments[0]->filename);
    }

    public function test_parses_message_with_sticker(): void
    {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100123456,
                    'type' => 'group',
                ],
                'date' => 1640000000,
                'sticker' => [
                    'file_id' => 'sticker_12345',
                    'emoji' => '😀',
                    'file_size' => 10000,
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $message->attachments);
        $this->assertEquals('sticker_12345', $message->attachments[0]->providerFileId);
        $this->assertEquals('sticker.webp', $message->attachments[0]->filename);
        $this->assertEquals('image/webp', $message->attachments[0]->mimeType);
    }

    public function test_sends_basic_message(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 123,
                    'chat' => [
                        'id' => -100123456,
                        'type' => 'group',
                    ],
                    'text' => 'Hello from bot!',
                ],
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => '-100123456',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Hello from bot!',
            channelId: '-100123456',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);
        $this->assertEquals('123', $response->providerMessageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org')
                && str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '-100123456'
                && $request['text'] === 'Hello from bot!'
                && $request['parse_mode'] === 'Markdown';
        });
    }

    public function test_sends_message_with_reply(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 124,
                    'chat' => ['id' => -100123456],
                    'text' => 'Reply message',
                ],
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => '-100123456',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Reply message',
            channelId: '-100123456',
            replyToMessageId: '42',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);

        Http::assertSent(function ($request) {
            return $request['reply_to_message_id'] === 42;
        });
    }

    public function test_sends_message_with_thread_id(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 125,
                    'chat' => ['id' => -100123456],
                    'text' => 'Thread message',
                ],
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => '-100123456',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Thread message',
            channelId: '-100123456',
            threadId: '50',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);

        Http::assertSent(function ($request) {
            return $request['reply_to_message_id'] === 50;
        });
    }

    public function test_handles_telegram_api_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: chat not found',
            ]),
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => 'invalid_chat',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: 'invalid_chat',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($response->success);
        $this->assertEquals('Bad Request: chat not found', $response->error);
    }

    public function test_handles_rate_limit_with_retry(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'ok' => false,
                    'error_code' => 429,
                    'description' => 'Too Many Requests',
                    'parameters' => ['retry_after' => 1],
                ], 429, ['Retry-After' => '1']);
            }

            return Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 126,
                    'chat' => ['id' => -100123456],
                    'text' => 'Success after retry',
                ],
            ]);
        });

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => '-100123456',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: '-100123456',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($response->success);
        $this->assertEquals(2, $callCount);
    }

    public function test_secret_token_verification_passes(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345],
                'chat' => ['id' => -100123456],
                'text' => 'Test',
            ],
        ]);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'my_secret_token');
        $request->attributes->set('connector_account', $this->account);

        $this->assertTrue($this->adapter->verifyWebhookSignature($request));
    }

    public function test_secret_token_verification_fails_with_wrong_token(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345],
                'chat' => ['id' => -100123456],
                'text' => 'Test',
            ],
        ]);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong_token');
        $request->attributes->set('connector_account', $this->account);

        $this->assertFalse($this->adapter->verifyWebhookSignature($request));
    }

    public function test_secret_token_verification_fails_without_header(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345],
                'chat' => ['id' => -100123456],
                'text' => 'Test',
            ],
        ]);
        $request->attributes->set('connector_account', $this->account);

        $this->assertFalse($this->adapter->verifyWebhookSignature($request));
    }

    public function test_send_message_fails_without_bot_token(): void
    {
        $accountWithoutToken = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'name' => 'Test Telegram Without Token',
            'credentials' => [],
            'webhook_secret' => 'secret',
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'account_key' => 'telegram_no_token',
        ]);

        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'connector_account_id' => $accountWithoutToken->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => '-100123456',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $payload = new OutboundPayload(
            content: 'Test message',
            channelId: '-100123456',
        );

        $response = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($response->success);
        $this->assertEquals('Missing bot token', $response->error);
    }

    public function test_threading_strategy_is_reply_to(): void
    {
        $this->assertTrue($this->adapter->supportsThreading());
        $this->assertEquals(
            \App\DTOs\Messenger\ThreadingStrategy::ReplyTo,
            $this->adapter->getThreadingStrategy()
        );
    }

    public function test_replay_protection_strategy_is_event_id(): void
    {
        $this->assertEquals(
            \App\DTOs\Messenger\ReplayProtectionStrategy::EventId,
            $this->adapter->getReplayProtectionStrategy()
        );
    }

    public function test_parses_channel_post(): void
    {
        $payload = [
            'update_id' => 123456789,
            'channel_post' => [
                'message_id' => 100,
                'chat' => [
                    'id' => -1001234567890,
                    'type' => 'channel',
                    'title' => 'Test Channel',
                ],
                'date' => 1640000000,
                'text' => 'Channel post content',
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $request->attributes->set('connector_account', $this->account);

        $message = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('-1001234567890', $message->channelId);
        $this->assertEquals('Channel post content', $message->content);
        $this->assertEquals('100', $message->providerMessageId);
    }
}
