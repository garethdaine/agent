<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Adapters;

use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Support\Messenger\Adapters\TelegramAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAdapterTest extends TestCase
{
    use RefreshDatabase;

    private TelegramAdapter $adapter;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new TelegramAdapter;
        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => '7123456789:AAGtest-telegram-bot-token-abc123',
                'secret_token' => 'my-telegram-secret-token-xyz',
            ],
        ]);
    }

    public function test_send_message_posts_to_send_message_endpoint(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot7123456789:AAGtest-telegram-bot-token-abc123/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 42,
                    'chat' => ['id' => 123456789],
                    'text' => 'Hello from the bot!',
                ],
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Hello from the bot!',
            channelId: '123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertTrue($result->success);
        $this->assertEquals('42', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === '123456789'
                && $request['text'] === 'Hello from the bot!'
                && $request['parse_mode'] === 'Markdown';
        });
    }

    public function test_send_message_stores_last_bot_message_id(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 99, 'chat' => ['id' => 123456789]],
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Test',
            channelId: '123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $this->adapter->sendMessage($session, $payload);

        $cacheKey = sprintf('telegram:last_bot_message:%s:%s', $session->connector_account_id, $session->channel_id);
        $this->assertEquals('99', Cache::get($cacheKey));
    }

    public function test_send_message_includes_reply_to_message_id(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 43, 'chat' => ['id' => 123456789]],
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: 'Reply',
            channelId: '123456789',
            threadId: null,
            replyToMessageId: '42',
            attachmentIds: [],
        );

        $this->adapter->sendMessage($session, $payload);

        Http::assertSent(function ($request) {
            return $request['reply_to_message_id'] === 42;
        });
    }

    public function test_supports_message_editing_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsMessageEditing());
    }

    public function test_edit_message_calls_edit_message_text(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot7123456789:AAGtest-telegram-bot-token-abc123/editMessageText' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 42,
                    'chat' => ['id' => 123456789],
                    'text' => 'Updated content',
                ],
            ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '42', 'Updated content');

        $this->assertTrue($result->success);
        $this->assertEquals('42', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/editMessageText')
                && $request['chat_id'] === '123456789'
                && $request['message_id'] === 42
                && $request['text'] === 'Updated content'
                && $request['parse_mode'] === 'Markdown';
        });
    }

    public function test_edit_message_returns_failure_on_api_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/editMessageText' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: message to edit not found',
            ], 400),
        ]);

        $result = $this->adapter->editMessage($session, '99999', 'Updated');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('message to edit not found', $result->error);
    }

    public function test_edit_message_normalizes_escaped_newlines(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/editMessageText' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 42],
            ], 200),
        ]);

        $this->adapter->editMessage($session, '42', 'Line 1\nLine 2');

        Http::assertSent(function ($request) {
            return $request['text'] === "Line 1\nLine 2";
        });
    }

    public function test_edit_message_retries_without_markdown_on_parse_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot7123456789:AAGtest-telegram-bot-token-abc123/editMessageText' => Http::sequence()
                ->push([
                    'ok' => false,
                    'error_code' => 400,
                    'description' => "Bad Request: can't parse entities: Can't find end of the entity starting at byte offset 10",
                ], 400)
                ->push([
                    'ok' => true,
                    'result' => ['message_id' => 42],
                ], 200),
        ]);

        $result = $this->adapter->editMessage($session, '42', '**partial bold');

        $this->assertTrue($result->success);

        Http::assertSentCount(2);
        Http::assertSentInOrder([
            fn ($request) => $request['parse_mode'] === 'Markdown',
            fn ($request) => ! isset($request['parse_mode']),
        ]);
    }

    public function test_edit_message_treats_not_modified_as_success(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/editMessageText' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same as a current content and reply markup of the message',
            ], 400),
        ]);

        $result = $this->adapter->editMessage($session, '42', 'Same content');

        $this->assertTrue($result->success);
        $this->assertEquals('42', $result->providerMessageId);
    }

    public function test_supports_reactions_returns_true(): void
    {
        $this->assertTrue($this->adapter->supportsReactions());
    }

    public function test_add_reaction_calls_set_message_reaction(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot7123456789:AAGtest-telegram-bot-token-abc123/setMessageReaction' => Http::response([
                'ok' => true,
            ], 200),
        ]);

        $result = $this->adapter->addReaction($session, '42', '👀');

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/setMessageReaction')
                && $request['chat_id'] === '123456789'
                && $request['message_id'] === 42;
        });
    }

    public function test_threading_strategy_returns_reply_to(): void
    {
        $this->assertEquals(ThreadingStrategy::ReplyTo, $this->adapter->getThreadingStrategy());
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

    public function test_verify_webhook_signature_with_valid_secret_token(): void
    {
        $request = new Request;
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'my-telegram-secret-token-xyz');
        $request->attributes->set('connector_account', $this->account);

        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_rejects_invalid_token(): void
    {
        $request = new Request;
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong-token');
        $request->attributes->set('connector_account', $this->account);

        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_passes_when_no_secret_configured(): void
    {
        $accountNoSecret = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => '7123456789:AAGtest-no-secret',
            ],
        ]);

        $request = new Request;
        $request->attributes->set('connector_account', $accountNoSecret);

        $result = $this->adapter->verifyWebhookSignature($request);

        $this->assertTrue($result);
    }

    public function test_parse_inbound_message_extracts_message_data(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'update_id' => 123456789,
            'message' => [
                'message_id' => 42,
                'from' => ['id' => 987654321, 'first_name' => 'Test'],
                'chat' => ['id' => 123456789, 'type' => 'private'],
                'date' => 1699999999,
                'text' => 'Hello bot!',
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('987654321', $normalized->providerUserId);
        $this->assertEquals('123456789', $normalized->channelId);
        $this->assertEquals('Hello bot!', $normalized->content);
        $this->assertEquals('42', $normalized->providerMessageId);
        $this->assertEquals('123456789', $normalized->providerEventId);
    }

    public function test_parse_inbound_message_extracts_reply_thread(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'update_id' => 123456790,
            'message' => [
                'message_id' => 43,
                'from' => ['id' => 987654321],
                'chat' => ['id' => 123456789],
                'date' => 1699999999,
                'text' => 'This is a reply',
                'reply_to_message' => [
                    'message_id' => 42,
                    'text' => 'Original message',
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('42', $normalized->threadId);
    }

    public function test_parse_inbound_message_extracts_photo_attachment(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'update_id' => 123456791,
            'message' => [
                'message_id' => 44,
                'from' => ['id' => 987654321],
                'chat' => ['id' => 123456789],
                'date' => 1699999999,
                'caption' => 'Check this photo',
                'photo' => [
                    ['file_id' => 'small_id', 'file_size' => 1000, 'width' => 90, 'height' => 90],
                    ['file_id' => 'large_id', 'file_size' => 50000, 'width' => 800, 'height' => 600],
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertEquals('Check this photo', $normalized->content);
        $this->assertCount(1, $normalized->attachments);
        $this->assertEquals('large_id', $normalized->attachments[0]->providerFileId);
        $this->assertEquals('image/jpeg', $normalized->attachments[0]->mimeType);
    }

    public function test_parse_inbound_message_extracts_document_attachment(): void
    {
        $request = new Request;
        $request->attributes->set('connector_account', $this->account);
        $request->merge([
            'update_id' => 123456792,
            'message' => [
                'message_id' => 45,
                'from' => ['id' => 987654321],
                'chat' => ['id' => 123456789],
                'date' => 1699999999,
                'document' => [
                    'file_id' => 'doc_file_id_123',
                    'file_name' => 'report.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 25000,
                ],
            ],
        ]);

        $normalized = $this->adapter->parseInboundMessage($request);

        $this->assertCount(1, $normalized->attachments);
        $this->assertEquals('doc_file_id_123', $normalized->attachments[0]->providerFileId);
        $this->assertEquals('report.pdf', $normalized->attachments[0]->filename);
        $this->assertEquals('application/pdf', $normalized->attachments[0]->mimeType);
    }

    public function test_send_message_handles_api_error(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ], 400),
        ]);

        $payload = new OutboundPayload(
            content: 'Test',
            channelId: '123456789',
            threadId: null,
            replyToMessageId: null,
            attachmentIds: [],
        );

        $result = $this->adapter->sendMessage($session, $payload);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('chat not found', $result->error);
    }

    public function test_send_message_normalizes_escaped_newlines(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
            'user_id' => $user->id,
            'channel_id' => '123456789',
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 42, 'chat' => ['id' => 123456789]],
            ], 200),
        ]);

        $payload = new OutboundPayload(
            content: '**Job Management:**\n- List jobs\n- Create jobs',
            channelId: '123456789',
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
