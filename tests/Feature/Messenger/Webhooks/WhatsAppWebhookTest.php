<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\Webhooks;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Jobs\Messenger\SendOutboundMessage;
use App\Messenger\Reliability\DeadLetterManager;
use App\Models\AgentJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Models\UserChatPreference;
use App\Models\UserNotificationSetting;
use App\Notifications\OutboundMessageFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for WhatsApp webhook handling via unified API route.
 *
 * Assumptions:
 * - WhatsApp Cloud API v18+ webhook format
 * - Unified webhook endpoint URL: /agent/api/v1/connectors/whatsapp/webhook
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
                'webhook_url' => 'https://example.com/agent/api/v1/connectors/whatsapp/webhook',
            ],
        ]);
    }

    // =========================================================================
    // GET Verification Tests
    // =========================================================================

    public function test_get_verification_with_subscribe_mode_returns_challenge(): void
    {
        $challenge = 'challenge_token_abc123';

        $response = $this->get(
            route('agent.api.connectors.whatsapp.webhook').'?'.http_build_query([
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
            route('agent.api.connectors.whatsapp.webhook').'?'.http_build_query([
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
            route('agent.api.connectors.whatsapp.webhook').'?'.http_build_query([
                'hub_mode' => 'unsubscribe',
                'hub_verify_token' => $this->verifyToken,
                'hub_challenge' => 'challenge123',
            ])
        );

        $response->assertStatus(400);
    }

    // =========================================================================
    // POST Message Webhook Tests
    // =========================================================================

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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
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
            route('agent.api.connectors.whatsapp.webhook'),
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
            route('agent.api.connectors.whatsapp.webhook'),
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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
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

    // =========================================================================
    // Status Update Tests
    // =========================================================================

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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);
        Queue::assertNothingPushed();
    }

    // =========================================================================
    // Multiple Message Tests
    // =========================================================================

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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
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
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    // =========================================================================
    // Legacy Route Removal Tests
    // =========================================================================

    public function test_legacy_whatsapp_web_route_returns_404(): void
    {
        $response = $this->get('/messenger/webhooks/whatsapp/'.$this->account->id.'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'challenge_12345',
        ]));

        $response->assertStatus(404);
    }

    public function test_legacy_whatsapp_post_route_returns_404(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            '/messenger/webhooks/whatsapp/'.$this->account->id,
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(404);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    public function test_invalid_verify_token_with_no_accounts_returns_403(): void
    {
        // Delete all accounts to test the case where no accounts match
        ConnectorAccount::query()->delete();

        $response = $this->get(
            route('agent.api.connectors.whatsapp.webhook').'?'.http_build_query([
                'hub_mode' => 'subscribe',
                'hub_verify_token' => 'some-random-token',
                'hub_challenge' => 'challenge123',
            ])
        );

        $response->assertStatus(403);
    }

    // =========================================================================
    // Chat Intent Parsing Tests
    // =========================================================================

    public function test_parses_message_as_jobs_list_command(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '14155551234',
        ]);
        AgentJob::factory()->count(3)->create(['user_id' => $user->id]);

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
                                        'profile' => ['name' => 'Test User'],
                                        'wa_id' => '14155551234',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155551234',
                                        'id' => 'wamid.listjobs123',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'list jobs'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
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

    // =========================================================================
    // Authorization Denial Tests
    // =========================================================================

    public function test_denies_unauthorized_job_action_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        MessengerIdentityLink::factory()->create([
            'user_id' => $attacker->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '14155559999',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

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
                                        'profile' => ['name' => 'Attacker'],
                                        'wa_id' => '14155559999',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155559999',
                                        'id' => 'wamid.deletejob456',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => "delete job {$job->id}"],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        // Job should still exist (not deleted due to authorization failure)
        $this->assertDatabaseHas('agent_jobs', ['id' => $job->id]);
    }

    public function test_allows_job_action_for_owner(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '14155558888',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id, 'name' => 'Owner Job']);

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
                                        'profile' => ['name' => 'Owner'],
                                        'wa_id' => '14155558888',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155558888',
                                        'id' => 'wamid.listowner789',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'list my jobs'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    // =========================================================================
    // Confirmation Workflow Tests
    // =========================================================================

    public function test_requests_confirmation_for_delete_when_preference_enabled(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create([
            'user_id' => $user->id,
            'require_confirmation_for_delete' => true,
            'require_confirmation_for_stop' => true,
            'require_confirmation_for_steer' => false,
        ]);

        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '14155557777',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

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
                                        'profile' => ['name' => 'Confirm User'],
                                        'wa_id' => '14155557777',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155557777',
                                        'id' => 'wamid.confirmdelete101',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => "delete job {$job->id}"],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        // Job should NOT be deleted yet (confirmation required)
        $this->assertDatabaseHas('agent_jobs', ['id' => $job->id]);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_skips_confirmation_when_preference_disabled(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create([
            'user_id' => $user->id,
            'require_confirmation_for_delete' => false,
            'require_confirmation_for_stop' => false,
            'require_confirmation_for_steer' => false,
        ]);

        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '14155556666',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

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
                                        'profile' => ['name' => 'No Confirm User'],
                                        'wa_id' => '14155556666',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155556666',
                                        'id' => 'wamid.noconfirmdelete202',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => "delete job {$job->id}"],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    // =========================================================================
    // Outbound Message Tests
    // =========================================================================

    public function test_queues_outbound_message_on_successful_action(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '14155555555',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id]);

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
                                        'profile' => ['name' => 'Outbound User'],
                                        'wa_id' => '14155555555',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '14155555555',
                                        'id' => 'wamid.outbound303',
                                        'timestamp' => '1699999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'list jobs'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            route('agent.api.connectors.whatsapp.webhook'),
            $payload,
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        // Verify outbound message is queued (via ProcessInboundMessage which triggers SendOutboundMessage)
        Queue::assertPushed(ProcessInboundMessage::class);
    }

    // =========================================================================
    // Outbound Message Failure Tests
    // =========================================================================

    public function test_handles_outbound_message_failure_flow(): void
    {
        Queue::fake();
        Notification::fake();

        $user = User::factory()->create();
        UserNotificationSetting::create(['user_id' => $user->id, 'channel' => 'email']);

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_WHATSAPP,
        ]);

        $this->mock(DeadLetterManager::class)->shouldReceive('moveToDeadLetter')->once();

        $sendJob = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Test WhatsApp message',
        );

        $sendJob->failed(new \Exception('WhatsApp API error'));

        Notification::assertSentTo($user, OutboundMessageFailedNotification::class);
    }

    public function test_outbound_message_has_correct_retry_configuration(): void
    {
        $sendJob = new SendOutboundMessage(
            sessionId: 'test-session-id',
            content: 'Test message',
        );

        $this->assertEquals(3, $sendJob->tries);
        $this->assertEquals([30, 120, 480], $sendJob->backoff);
    }
}
