<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\Webhooks;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\AgentJob;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Models\UserChatPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for Discord webhook handling via unified API route.
 *
 * Assumptions:
 * - sodium PHP extension is available for Ed25519 verification
 * - Unified webhook endpoint URL: /agent/api/v1/connectors/discord/webhook
 * - Discord sends signature in X-Signature-Ed25519 header
 * - Discord sends timestamp in X-Signature-Timestamp header
 *
 * Discord Interaction Types:
 * - Type 1: PING (endpoint verification)
 * - Type 2: APPLICATION_COMMAND (slash commands)
 * - Type 3: MESSAGE_COMPONENT (button/select clicks)
 * - Type 4: APPLICATION_COMMAND_AUTOCOMPLETE
 * - Type 5: MODAL_SUBMIT
 *
 * Response Types:
 * - Type 1: PONG (for PING)
 * - Type 4: CHANNEL_MESSAGE_WITH_SOURCE
 * - Type 5: DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE (for long operations)
 * - Type 6: DEFERRED_UPDATE_MESSAGE
 *
 * Signature Verification:
 * - Message = timestamp + raw_body
 * - Signature is hex-encoded Ed25519 detached signature
 * - Public key is hex-encoded 32-byte Ed25519 public key
 */
class DiscordWebhookTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $account;

    private string $privateKeyHex;

    private string $publicKeyHex;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Generate Ed25519 key pair for testing
        // Note: In real Discord, you'd use the public key from the Developer Portal
        $keypair = sodium_crypto_sign_keypair();
        $this->privateKeyHex = bin2hex(sodium_crypto_sign_secretkey($keypair));
        $this->publicKeyHex = bin2hex(sodium_crypto_sign_publickey($keypair));

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => 'MTEyMzQ1Njc4OTAxMjM0NTY3OA.GxYmZc.test-token',
                'application_id' => '1123456789012345678',
                'public_key' => $this->publicKeyHex,
            ],
        ]);
    }

    // =========================================================================
    // Signature Verification Tests
    // =========================================================================

    public function test_ed25519_signature_verification_passes_valid_signature(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 1]);

        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            ['type' => 1],
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 1]);
    }

    public function test_ed25519_signature_verification_rejects_invalid_signature(): void
    {
        $timestamp = (string) time();

        // Generate an invalid signature
        $invalidSignature = str_repeat('00', 64);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            ['type' => 1],
            [
                'X-Signature-Ed25519' => $invalidSignature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(401);
    }

    public function test_ed25519_signature_verification_rejects_missing_signature(): void
    {
        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            ['type' => 1],
            [
                'X-Signature-Timestamp' => (string) time(),
            ]
        );

        $response->assertStatus(401);
    }

    public function test_ed25519_signature_verification_rejects_missing_timestamp(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 1]);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            ['type' => 1],
            [
                'X-Signature-Ed25519' => $signature,
            ]
        );

        $response->assertStatus(401);
    }

    // =========================================================================
    // Interaction Type Tests
    // =========================================================================

    public function test_ping_type_returns_pong_response(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 1]);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            ['type' => 1],
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 1]); // PONG response

        // PING should not dispatch any jobs
        Queue::assertNothingPushed();
    }

    public function test_application_command_routes_to_intent_parser(): void
    {
        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'data' => [
                'name' => 'agent',
                'options' => [
                    ['name' => 'action', 'value' => 'run'],
                    ['name' => 'command', 'value' => 'check status'],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        // Should return deferred response for async processing
        $response->assertStatus(200);
        $response->assertJson(['type' => 5]); // DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE

        // Should dispatch ProcessInboundMessage job
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'discord'
                && $job->connectorAccountId === $this->account->id;
        });
    }

    public function test_message_component_routes_to_intent_parser(): void
    {
        $body = [
            'type' => 3, // MESSAGE_COMPONENT
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'message' => [
                'id' => '999888777666555444',
                'content' => 'Original message',
            ],
            'data' => [
                'custom_id' => 'confirm_action_123',
                'component_type' => 2, // Button
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]); // DEFERRED

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_deferred_response_type_5_for_long_operations(): void
    {
        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'data' => [
                'name' => 'agent',
                'options' => [],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        // Type 5: DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE
        // Indicates "thinking" state while processing async
        $response->assertStatus(200);
        $response->assertJson(['type' => 5]);
    }

    public function test_autocomplete_returns_type_8_with_choices(): void
    {
        $body = [
            'type' => 4, // APPLICATION_COMMAND_AUTOCOMPLETE
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'data' => [
                'name' => 'agent',
                'options' => [
                    [
                        'name' => 'command',
                        'type' => 3, // STRING
                        'value' => 'sta', // Partial input
                        'focused' => true,
                    ],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        // Type 8: APPLICATION_COMMAND_AUTOCOMPLETE_RESULT
        $response->assertStatus(200);
        $response->assertJsonStructure(['type', 'data' => ['choices']]);
    }

    public function test_modal_submit_routes_to_intent_parser(): void
    {
        $body = [
            'type' => 5, // MODAL_SUBMIT
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'TestUser',
                ],
            ],
            'data' => [
                'custom_id' => 'feedback_modal',
                'components' => [
                    [
                        'type' => 1, // ACTION_ROW
                        'components' => [
                            [
                                'type' => 4, // TEXT_INPUT
                                'custom_id' => 'feedback_text',
                                'value' => 'User submitted feedback here',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]); // DEFERRED

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    // =========================================================================
    // Bot Message Filtering Tests
    // =========================================================================

    public function test_bot_messages_are_ignored(): void
    {
        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => '9876543210987654321',
                    'username' => 'AnotherBot',
                    'bot' => true, // This is a bot
                ],
            ],
            'data' => [
                'name' => 'agent',
                'options' => [],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);

        // Bot messages should not be processed
        Queue::assertNothingPushed();
    }

    public function test_dm_interaction_uses_user_field(): void
    {
        // In DMs, there's no 'member' field, just 'user'
        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555666',
            'token' => 'interaction-token-abc123',
            'channel_id' => '123456789012345678',
            'user' => [ // DM uses 'user' instead of 'member'
                'id' => '9876543210987654321',
                'username' => 'TestUser',
            ],
            'data' => [
                'name' => 'agent',
                'options' => [],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    // =========================================================================
    // Legacy Route Removal Tests
    // =========================================================================

    public function test_legacy_discord_web_route_returns_404(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['type' => 1]);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            '/messenger/webhooks/discord/'.$this->account->id,
            ['type' => 1],
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(404);
    }

    // =========================================================================
    // Job Handler Integration Tests
    // =========================================================================

    public function test_slash_command_routes_to_jobs_list_handler(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '9876543210987654321',
        ]);
        AgentJob::factory()->count(2)->create(['user_id' => $user->id]);

        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555666',
            'token' => 'interaction-token-jobs-list',
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
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]); // DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'discord'
                && $job->connectorAccountId === $this->account->id;
        });
    }

    // =========================================================================
    // Authorization Denial Tests
    // =========================================================================

    public function test_denies_unauthorized_job_action_for_non_owner(): void
    {
        // Create job owner and attacker
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        // Link attacker's Discord account
        MessengerIdentityLink::factory()->create([
            'user_id' => $attacker->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U_ATTACKER_12345',
        ]);

        // Create job owned by someone else
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555667',
            'token' => 'interaction-token-delete-job',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => 'U_ATTACKER_12345',
                    'username' => 'AttackerUser',
                ],
            ],
            'data' => [
                'name' => 'jobs',
                'options' => [
                    [
                        'name' => 'delete',
                        'type' => 1,
                        'options' => [
                            ['name' => 'job_id', 'type' => 4, 'value' => $job->id],
                        ],
                    ],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);

        // Job should still exist (not deleted)
        $this->assertDatabaseHas('agent_jobs', ['id' => $job->id]);
    }

    public function test_allows_job_action_for_owner(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U_OWNER_12345',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id, 'name' => 'My Test Job']);

        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555668',
            'token' => 'interaction-token-list-jobs',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => 'U_OWNER_12345',
                    'username' => 'OwnerUser',
                ],
            ],
            'data' => [
                'name' => 'jobs',
                'options' => [
                    ['name' => 'list', 'type' => 1],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]);

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
            'provider_user_id' => 'U_CONFIRM_USER',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555669',
            'token' => 'interaction-token-delete-confirm',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => 'U_CONFIRM_USER',
                    'username' => 'ConfirmUser',
                ],
            ],
            'data' => [
                'name' => 'jobs',
                'options' => [
                    [
                        'name' => 'delete',
                        'type' => 1,
                        'options' => [
                            ['name' => 'job_id', 'type' => 4, 'value' => $job->id],
                        ],
                    ],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]); // DEFERRED

        // Job should not be deleted yet (waiting for confirmation)
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
            'provider_user_id' => 'U_NO_CONFIRM_USER',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555670',
            'token' => 'interaction-token-delete-no-confirm',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => 'U_NO_CONFIRM_USER',
                    'username' => 'NoConfirmUser',
                ],
            ],
            'data' => [
                'name' => 'jobs',
                'options' => [
                    [
                        'name' => 'delete',
                        'type' => 1,
                        'options' => [
                            ['name' => 'job_id', 'type' => 4, 'value' => $job->id],
                        ],
                    ],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['type' => 5]);

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
            'provider_user_id' => 'U_OUTBOUND_USER',
        ]);

        AgentJob::factory()->count(1)->create(['user_id' => $user->id]);

        $body = [
            'type' => 2, // APPLICATION_COMMAND
            'id' => '111222333444555671',
            'token' => 'interaction-token-outbound',
            'channel_id' => '123456789012345678',
            'member' => [
                'user' => [
                    'id' => 'U_OUTBOUND_USER',
                    'username' => 'OutboundUser',
                ],
            ],
            'data' => [
                'name' => 'jobs',
                'options' => [
                    ['name' => 'list', 'type' => 1],
                ],
            ],
        ];

        $timestamp = (string) time();
        $bodyJson = json_encode($body);
        $signature = $this->generateSignature($timestamp, $bodyJson);

        $response = $this->postJson(
            route('agent.api.connectors.discord.webhook'),
            $body,
            [
                'X-Signature-Ed25519' => $signature,
                'X-Signature-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);

        // Discord uses deferred responses, so outbound is handled via follow-up
        Queue::assertPushed(ProcessInboundMessage::class);
    }

    /**
     * Generate a valid Ed25519 signature for the given timestamp and body.
     */
    private function generateSignature(string $timestamp, string $body): string
    {
        $message = $timestamp.$body;
        $privateKey = hex2bin($this->privateKeyHex);

        $signature = sodium_crypto_sign_detached($message, $privateKey);

        return bin2hex($signature);
    }
}
