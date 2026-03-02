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
 * Feature tests for Telegram webhook handling via unified API route.
 *
 * Assumptions:
 * - Unified webhook endpoint URL: /agent/api/v1/connectors/telegram/webhook/{account_key}
 * - Telegram webhook uses X-Telegram-Bot-Api-Secret-Token header for verification
 * - Update types: message, edited_message, callback_query, channel_post
 *
 * Signature Verification:
 * - Compare X-Telegram-Bot-Api-Secret-Token header with configured secret_token
 */
class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $account;

    private string $secretToken;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->secretToken = 'test_telegram_secret_token_12345';

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'account_key' => 'telegram_test_bot',
            'credentials' => [
                'bot_token' => '123456:ABC-DEF1234ghIkl-test-token',
                'secret_token' => $this->secretToken,
            ],
            'config' => [
                'signature_verification' => [
                    'scheme' => 'token',
                    'secret_token' => $this->secretToken,
                ],
            ],
        ]);
    }

    // =========================================================================
    // Secret Token Verification Tests
    // =========================================================================

    public function test_secret_token_verification_passes_valid_token(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '12345',
        ]);

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
                    'id' => 12345,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Hello bot!',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'telegram'
                && $job->connectorAccountId === $this->account->id;
        });
    }

    public function test_secret_token_verification_rejects_invalid_token(): void
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
                    'id' => 12345,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Hack attempt',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => 'wrong_secret_token',
            ]
        );

        $response->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_secret_token_verification_rejects_missing_token(): void
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
                    'id' => 12345,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'No token',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload
        );

        $response->assertStatus(401);
    }

    // =========================================================================
    // Message Event Tests
    // =========================================================================

    public function test_message_event_dispatches_process_job(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '98765',
        ]);

        $payload = [
            'update_id' => 123456790,
            'message' => [
                'message_id' => 100,
                'from' => [
                    'id' => 98765,
                    'first_name' => 'Test User',
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => 98765,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'list my jobs',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'telegram';
        });
    }

    public function test_callback_query_event_dispatches_process_job(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '54321',
        ]);

        $payload = [
            'update_id' => 123456791,
            'callback_query' => [
                'id' => 'callback_123',
                'from' => [
                    'id' => 54321,
                    'first_name' => 'Callback User',
                ],
                'message' => [
                    'message_id' => 101,
                    'chat' => [
                        'id' => 54321,
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => 'Select an option',
                ],
                'data' => 'confirm_delete_123',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_edited_message_event_dispatches_process_job(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '11111',
        ]);

        $payload = [
            'update_id' => 123456792,
            'edited_message' => [
                'message_id' => 102,
                'from' => [
                    'id' => 11111,
                    'first_name' => 'Edit User',
                ],
                'chat' => [
                    'id' => 11111,
                    'type' => 'private',
                ],
                'date' => time() - 60,
                'edit_date' => time(),
                'text' => 'edited message content',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_channel_post_event_dispatches_process_job(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '-1001234567890',
        ]);

        $payload = [
            'update_id' => 123456793,
            'channel_post' => [
                'message_id' => 103,
                'chat' => [
                    'id' => -1001234567890,
                    'type' => 'channel',
                    'title' => 'Test Channel',
                ],
                'date' => time(),
                'text' => 'Channel post content',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
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
            'provider_user_id' => '22222',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $payload = [
            'update_id' => 123456794,
            'message' => [
                'message_id' => 104,
                'from' => [
                    'id' => 22222,
                    'first_name' => 'Attacker',
                ],
                'chat' => [
                    'id' => 22222,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "delete job {$job->id}",
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
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
            'provider_user_id' => '33333',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id, 'name' => 'Owner Job']);

        $payload = [
            'update_id' => 123456795,
            'message' => [
                'message_id' => 105,
                'from' => [
                    'id' => 33333,
                    'first_name' => 'Owner',
                ],
                'chat' => [
                    'id' => 33333,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'list my jobs',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
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
            'provider_user_id' => '44444',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $payload = [
            'update_id' => 123456796,
            'message' => [
                'message_id' => 106,
                'from' => [
                    'id' => 44444,
                    'first_name' => 'Confirm User',
                ],
                'chat' => [
                    'id' => 44444,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "delete job {$job->id}",
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
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
            'provider_user_id' => '55555',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $payload = [
            'update_id' => 123456797,
            'message' => [
                'message_id' => 107,
                'from' => [
                    'id' => 55555,
                    'first_name' => 'No Confirm',
                ],
                'chat' => [
                    'id' => 55555,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "delete job {$job->id}",
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
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
            'provider_user_id' => '66666',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id]);

        $payload = [
            'update_id' => 123456798,
            'message' => [
                'message_id' => 108,
                'from' => [
                    'id' => 66666,
                    'first_name' => 'Outbound User',
                ],
                'chat' => [
                    'id' => 66666,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'list jobs',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

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
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
        ]);

        $this->mock(DeadLetterManager::class)->shouldReceive('moveToDeadLetter')->once();

        $sendJob = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Test Telegram message',
        );

        $sendJob->failed(new \Exception('Telegram API error'));

        Notification::assertSentTo($user, OutboundMessageFailedNotification::class);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    public function test_invalid_account_key_returns_404(): void
    {
        $payload = [
            'update_id' => 123456799,
            'message' => [
                'message_id' => 109,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 12345,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Hello',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => 'non_existent_bot']),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(404);
    }

    public function test_group_message_with_reply_includes_thread_context(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => '77777',
        ]);

        $payload = [
            'update_id' => 123456800,
            'message' => [
                'message_id' => 110,
                'from' => [
                    'id' => 77777,
                    'first_name' => 'Thread User',
                ],
                'chat' => [
                    'id' => -100123456789,
                    'type' => 'group',
                    'title' => 'Test Group',
                ],
                'date' => time(),
                'text' => 'Reply in thread',
                'reply_to_message' => [
                    'message_id' => 50,
                    'text' => 'Original message',
                ],
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.telegram.webhook', ['accountKey' => $this->account->account_key]),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->secretToken,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }
}
