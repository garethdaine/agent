<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\Models\ConnectorAccount;
use App\Support\Messenger\IdempotencyKeyGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $slackAccount;

    private ConnectorAccount $telegramAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slackAccount = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Slack Workspace',
            'credentials' => ['signing_secret' => 'test_signing_secret_12345'],
            'webhook_secret' => 'test_webhook_secret',
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => 'test_signing_secret_12345',
                ],
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'window_seconds' => 300,
                ],
            ],
            'account_key' => 'T12345678',
        ]);

        $this->telegramAccount = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'name' => 'Test Telegram Bot',
            'credentials' => ['bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'],
            'webhook_secret' => 'my_secret_token',
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'signature_verification' => [
                    'scheme' => 'token',
                    'secret_token' => 'my_secret_token',
                ],
                'replay_protection' => [
                    'strategy' => 'event_id',
                    'dedupe_ttl_seconds' => 3600,
                ],
            ],
            'account_key' => 'telegram_bot_123',
        ]);
    }

    public function test_valid_slack_signature_passes(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345678']);
        $signingSecret = 'test_signing_secret_12345';

        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $signingSecret);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            json_decode($body, true),
            [
                'X-Slack-Request-Timestamp' => $timestamp,
                'X-Slack-Signature' => $signature,
            ]
        );

        // Should not be 401 (signature validation passed)
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_invalid_slack_signature_returns_401(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345678']);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            json_decode($body, true),
            [
                'X-Slack-Request-Timestamp' => $timestamp,
                'X-Slack-Signature' => 'v0=invalid_signature_here',
            ]
        );

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'code' => 'SIGNATURE_INVALID',
                'message' => 'Invalid webhook signature.',
            ],
        ]);
    }

    public function test_expired_slack_timestamp_returns_401(): void
    {
        // Timestamp from 10 minutes ago (beyond 5-minute window)
        $expiredTimestamp = (string) (time() - 600);
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345678']);
        $signingSecret = 'test_signing_secret_12345';

        $sigBaseString = 'v0:'.$expiredTimestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $signingSecret);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            json_decode($body, true),
            [
                'X-Slack-Request-Timestamp' => $expiredTimestamp,
                'X-Slack-Signature' => $signature,
            ]
        );

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'code' => 'TIMESTAMP_EXPIRED',
                'message' => 'Webhook timestamp is too old.',
            ],
        ]);
    }

    public function test_valid_telegram_secret_token_passes(): void
    {
        $body = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345, 'first_name' => 'Test'],
                'chat' => ['id' => -100123456, 'type' => 'group'],
                'text' => 'Hello bot',
            ],
        ];

        $response = $this->postJson(
            '/agent/api/v1/connectors/telegram/webhook/telegram_bot_123',
            $body,
            [
                'X-Telegram-Bot-Api-Secret-Token' => 'my_secret_token',
            ]
        );

        // Should not be 401 (token validation passed)
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_invalid_telegram_secret_token_returns_401(): void
    {
        $body = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345, 'first_name' => 'Test'],
                'chat' => ['id' => -100123456, 'type' => 'group'],
                'text' => 'Hello bot',
            ],
        ];

        $response = $this->postJson(
            '/agent/api/v1/connectors/telegram/webhook/telegram_bot_123',
            $body,
            [
                'X-Telegram-Bot-Api-Secret-Token' => 'wrong_token',
            ]
        );

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'code' => 'SIGNATURE_INVALID',
                'message' => 'Invalid webhook signature.',
            ],
        ]);
    }

    public function test_duplicate_event_id_returns_200_silent_ack(): void
    {
        // First, simulate a successful first request by caching the event_id
        $eventId = 'evt_'.uniqid();
        Cache::put("messenger:event_dedupe:{$this->slackAccount->id}:{$eventId}", true, 3600);

        $timestamp = (string) time();
        $body = json_encode([
            'type' => 'event_callback',
            'team_id' => 'T12345678',
            'event_id' => $eventId,
        ]);
        $signingSecret = 'test_signing_secret_12345';

        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $signingSecret);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            json_decode($body, true),
            [
                'X-Slack-Request-Timestamp' => $timestamp,
                'X-Slack-Signature' => $signature,
            ]
        );

        // Should return 200 (silent ack for duplicate)
        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'duplicate' => true]);
    }

    public function test_idempotency_key_generation_with_provider_message_id(): void
    {
        $generator = app(IdempotencyKeyGenerator::class);

        $message = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerMessageId: '1234567890.123456',
        );

        $key1 = $generator->generate($message, $this->slackAccount);
        $key2 = $generator->generate($message, $this->slackAccount);

        // Keys should be consistent
        $this->assertEquals($key1, $key2);
        // Key should be a 64-character hex string (SHA256)
        $this->assertEquals(64, strlen($key1));
    }

    public function test_idempotency_key_generation_fallback_without_message_id(): void
    {
        $generator = app(IdempotencyKeyGenerator::class);

        $timestamp = Carbon::now();

        $message = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_1', 'attach_2'],
        );

        $key1 = $generator->generate($message, $this->slackAccount);
        $key2 = $generator->generate($message, $this->slackAccount);

        // Keys should be consistent
        $this->assertEquals($key1, $key2);
        // Key should be a 64-character hex string (SHA256)
        $this->assertEquals(64, strlen($key1));
    }

    public function test_idempotency_key_differs_for_different_content(): void
    {
        $generator = app(IdempotencyKeyGenerator::class);

        $timestamp = Carbon::now();

        $message1 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
        );

        $message2 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world!', // Different content
            providerTimestamp: $timestamp,
        );

        $key1 = $generator->generate($message1, $this->slackAccount);
        $key2 = $generator->generate($message2, $this->slackAccount);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_idempotency_key_differs_for_different_attachments(): void
    {
        $generator = app(IdempotencyKeyGenerator::class);

        $timestamp = Carbon::now();

        $message1 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_1'],
        );

        $message2 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_2'], // Different attachment
        );

        $key1 = $generator->generate($message1, $this->slackAccount);
        $key2 = $generator->generate($message2, $this->slackAccount);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_missing_slack_signature_header_returns_401(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345678']);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            json_decode($body, true),
            [
                'X-Slack-Request-Timestamp' => $timestamp,
                // Missing X-Slack-Signature
            ]
        );

        $response->assertStatus(401);
    }

    public function test_missing_telegram_token_header_returns_401(): void
    {
        $body = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345, 'first_name' => 'Test'],
                'chat' => ['id' => -100123456, 'type' => 'group'],
                'text' => 'Hello bot',
            ],
        ];

        $response = $this->postJson(
            '/agent/api/v1/connectors/telegram/webhook/telegram_bot_123',
            $body
            // Missing X-Telegram-Bot-Api-Secret-Token
        );

        $response->assertStatus(401);
    }
}
