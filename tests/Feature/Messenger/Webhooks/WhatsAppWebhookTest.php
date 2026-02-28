<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\Webhooks;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for WhatsAppWebhookController.
 *
 * Assumptions:
 * - WhatsApp Cloud API v18+ webhook format
 * - Webhook endpoint URL pattern: /messenger/webhooks/whatsapp/{account}
 * - GET requests used for webhook verification (hub.mode=subscribe)
 * - POST requests used for incoming messages with HMAC-SHA256 signature
 * - Signature header: X-Hub-Signature-256
 *
 * WhatsApp Webhook Verification:
 * - GET with hub.mode=subscribe, hub.verify_token, hub.challenge
 * - If verify_token matches, return hub.challenge with 200
 * - If verify_token doesn't match, return 403
 *
 * Message Webhook:
 * - POST with X-Hub-Signature-256 header
 * - Signature is sha256=HMAC_SHA256(body, app_secret)
 * - Return 401 on invalid signature
 *
 * Payload Structure:
 * - entry[].changes[].value.messages[] contains incoming messages
 * - entry[].changes[].value.statuses[] contains delivery status updates
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $account;

    private string $appSecret;

    private string $verifyToken;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->appSecret = 'test_app_secret_1234567890abcdef';
        $this->verifyToken = 'my-verify-token-12345';

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_WHATSAPP,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
                'phone_number_id' => '123456789012345',
                'app_secret' => $this->appSecret,
                'verify_token' => $this->verifyToken,
                'webhook_url' => 'https://example.com/messenger/webhooks/whatsapp/test',
            ],
        ]);
    }

    public function test_get_verification_with_subscribe_mode_returns_challenge(): void
    {
        $challenge = 'challenge_token_abc123';

        $response = $this->get(
            route('messenger.webhooks.whatsapp.verify', ['account' => $this->account->id]) .
            '?' . http_build_query([
                'hub_mode' => 'subscribe',
                'hub_verify_token' => $this->verifyToken,
                'hub_challenge' => $challenge,
            ])
        );

        $response->assertStatus(200);
        $response->assertSee($challenge);
    }

    public function test_get_verification_rejects_wrong_verify_token(): void
    {
        $response = $this->get(
            route('messenger.webhooks.whatsapp.verify', ['account' => $this->account->id]) .
            '?' . http_build_query([
                'hub_mode' => 'subscribe',
                'hub_verify_token' => 'wrong-token',
                'hub_challenge' => 'challenge123',
            ])
        );

        $response->assertStatus(403);
    }

    public function test_get_verification_rejects_non_subscribe_mode(): void
    {
        $response = $this->get(
            route('messenger.webhooks.whatsapp.verify', ['account' => $this->account->id]) .
            '?' . http_build_query([
                'hub_mode' => 'unsubscribe',
                'hub_verify_token' => $this->verifyToken,
                'hub_challenge' => 'challenge123',
            ])
        );

        $response->assertStatus(403);
    }

    public function test_post_with_valid_hmac_sha256_signature_processes_message(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'John Doe'],
                                        'wa_id' => '14155551234',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155551234',
                                        'id' => 'wamid.HBgLMTQxNTU1NTEyMzQVAgA',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello, bot!'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'whatsapp'
                && $job->connectorAccountId === $this->account->id;
        });
    }

    public function test_post_with_invalid_signature_returns_401(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ];

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => 'sha256=invalid_signature_here',
            ]
        );

        $response->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_post_with_missing_signature_returns_401(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ];

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload
        );

        $response->assertStatus(401);
    }

    public function test_message_payload_routes_to_process_chat_intent(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Jane Smith'],
                                        'wa_id' => '14155559876',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155559876',
                                        'id' => 'wamid.command123',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'Run task abc'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->connectorAccountId === $this->account->id
                && $job->provider === 'whatsapp';
        });
    }

    public function test_status_updates_handled_without_error(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.status123',
                                        'status' => 'sent',
                                        'timestamp' => '1699999999',
                                        'recipient_id' => '14155551234',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        // Status updates should be handled gracefully (200 OK)
        $response->assertStatus(200);

        // Status updates shouldn't dispatch message processing jobs
        Queue::assertNothingPushed();
    }

    public function test_delivered_status_handled_without_error(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.status456',
                                        'status' => 'delivered',
                                        'timestamp' => '1699999999',
                                        'recipient_id' => '14155551234',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);
        Queue::assertNothingPushed();
    }

    public function test_read_status_handled_without_error(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.status789',
                                        'status' => 'read',
                                        'timestamp' => '1699999999',
                                        'recipient_id' => '14155551234',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);
        Queue::assertNothingPushed();
    }

    public function test_invalid_account_returns_404(): void
    {
        $response = $this->get(
            '/messenger/webhooks/whatsapp/00000000-0000-0000-0000-000000000000' .
            '?' . http_build_query([
                'hub_mode' => 'subscribe',
                'hub_verify_token' => $this->verifyToken,
                'hub_challenge' => 'challenge123',
            ])
        );

        $response->assertStatus(404);
    }

    public function test_multiple_messages_in_single_payload_all_processed(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'John Doe'],
                                        'wa_id' => '14155551234',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155551234',
                                        'id' => 'wamid.msg1',
                                        'timestamp' => '1699999998',
                                        'type' => 'text',
                                        'text' => ['body' => 'First message'],
                                    ],
                                    [
                                        'from' => '14155551234',
                                        'id' => 'wamid.msg2',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'Second message'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        // Both messages should be processed
        Queue::assertPushed(ProcessInboundMessage::class, 2);
    }

    public function test_image_message_handled_correctly(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789012345',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+14155550001',
                                    'phone_number_id' => '123456789012345',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Jane Doe'],
                                        'wa_id' => '14155559876',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155559876',
                                        'id' => 'wamid.image123',
                                        'timestamp' => '1699999999',
                                        'type' => 'image',
                                        'image' => [
                                            'id' => 'media_id_123',
                                            'mime_type' => 'image/jpeg',
                                            'sha256' => 'abc123hash',
                                            'caption' => 'Look at this!',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('messenger.webhooks.whatsapp', ['account' => $this->account->id]),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }
}
