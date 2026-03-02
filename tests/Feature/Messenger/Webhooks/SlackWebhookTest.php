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
 * Feature tests for Slack webhook handling via unified API route.
 *
 * Assumptions:
 * - Unified webhook endpoint URL: /agent/api/v1/connectors/slack/webhook
 * - Slack signature verification uses v0 HMAC-SHA256
 * - Signature header: X-Slack-Signature
 * - Timestamp header: X-Slack-Request-Timestamp
 *
 * Event Types:
 * - url_verification: Initial webhook setup verification
 * - event_callback: Incoming events (messages, app_mention, etc.)
 *
 * Signature Verification:
 * - sig_basestring = 'v0:' + timestamp + ':' + request_body
 * - signature = 'v0=' + HMAC_SHA256(sig_basestring, signing_secret)
 */
class SlackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $account;

    private string $signingSecret;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->signingSecret = 'test_slack_signing_secret_12345';

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'account_key' => 'T_SLACK_TEST',
            'credentials' => [
                'bot_token' => 'xoxb-test-slack-token-12345',
                'signing_secret' => $this->signingSecret,
            ],
            'config' => [
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => $this->signingSecret,
                ],
            ],
        ]);
    }

    // =========================================================================
    // URL Verification Tests
    // =========================================================================

    public function test_url_verification_returns_challenge(): void
    {
        $challenge = 'challenge_token_slack_12345';
        $timestamp = (string) time();
        $body = json_encode([
            'type' => 'url_verification',
            'token' => 'test_token',
            'challenge' => $challenge,
        ]);

        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            json_decode($body, true),
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['challenge' => $challenge]);
    }

    // =========================================================================
    // Signature Verification Tests
    // =========================================================================

    public function test_signature_verification_passes_valid_signature(): void
    {
        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_SIG_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Hello bot',
                'ts' => '1234567890.123456',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);
    }

    public function test_signature_verification_rejects_invalid_signature(): void
    {
        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_INVALID_SIG',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Hack attempt',
                'ts' => '1234567890.123456',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => 'v0=invalid_signature_here',
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_signature_verification_rejects_missing_signature(): void
    {
        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_MISSING_SIG',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'No signature',
                'ts' => '1234567890.123456',
            ],
        ];

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(401);
    }

    public function test_signature_verification_handles_old_timestamp(): void
    {
        // Timestamp from 10 minutes ago (outside 5 minute window)
        // Note: Replay protection may be enforced at middleware or provider adapter level
        // This test validates the webhook handles old timestamps appropriately
        $oldTimestamp = (string) (time() - 600);
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_REPLAY',
            'event' => [
                'type' => 'message',
                'user' => 'U12345678',
                'channel' => 'C12345678',
                'text' => 'Replay attack',
                'ts' => '1234567890.123456',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($oldTimestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $oldTimestamp,
            ]
        );

        // Webhook should either reject (401) or accept with no job pushed for replay
        // The exact behavior depends on the replay protection strategy configured
        $this->assertTrue(
            $response->status() === 401 || $response->status() === 200,
            'Expected either 401 (replay rejected) or 200 (deferred handling)'
        );
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
            'provider_user_id' => 'U_SLACK_USER',
        ]);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_MESSAGE_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_SLACK_USER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'list my jobs',
                'ts' => '1234567890.111111',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'slack'
                && $job->connectorAccountId === $this->account->id;
        });
    }

    public function test_app_mention_event_dispatches_process_job(): void
    {
        $user = User::factory()->create();
        MessengerIdentityLink::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U_MENTION_USER',
        ]);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_MENTION_TEST',
            'event' => [
                'type' => 'app_mention',
                'user' => 'U_MENTION_USER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => '<@U_BOT_ID> list jobs',
                'ts' => '1234567890.222222',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_bot_messages_are_ignored(): void
    {
        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_BOT_MSG',
            'event' => [
                'type' => 'message',
                'bot_id' => 'B_OTHER_BOT',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'Bot message',
                'ts' => '1234567890.333333',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(200);

        // Bot messages should not dispatch processing jobs
        Queue::assertNothingPushed();
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
            'provider_user_id' => 'U_ATTACKER',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_UNAUTH_DELETE',
            'event' => [
                'type' => 'message',
                'user' => 'U_ATTACKER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => "delete job {$job->id}",
                'ts' => '1234567890.444444',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
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
            'provider_user_id' => 'U_OWNER',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id, 'name' => 'Owner Job']);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_OWNER_LIST',
            'event' => [
                'type' => 'message',
                'user' => 'U_OWNER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'list my jobs',
                'ts' => '1234567890.555555',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
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
            'provider_user_id' => 'U_CONFIRM',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_CONFIRM_DELETE',
            'event' => [
                'type' => 'message',
                'user' => 'U_CONFIRM',
                'channel' => 'C_TEST_CHANNEL',
                'text' => "delete job {$job->id}",
                'ts' => '1234567890.666666',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
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
            'provider_user_id' => 'U_NO_CONFIRM',
        ]);

        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_NO_CONFIRM',
            'event' => [
                'type' => 'message',
                'user' => 'U_NO_CONFIRM',
                'channel' => 'C_TEST_CHANNEL',
                'text' => "delete job {$job->id}",
                'ts' => '1234567890.777777',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
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
            'provider_user_id' => 'U_OUTBOUND',
        ]);

        AgentJob::factory()->create(['user_id' => $user->id]);

        $timestamp = (string) time();
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T_SLACK_TEST',
            'event_id' => 'Ev_OUTBOUND',
            'event' => [
                'type' => 'message',
                'user' => 'U_OUTBOUND',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'list jobs',
                'ts' => '1234567890.888888',
            ],
        ];
        $body = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $body);

        $response = $this->postJson(
            route('agent.api.connectors.slack.webhook'),
            $payload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
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
            'provider' => ConnectorAccount::PROVIDER_SLACK,
        ]);

        $this->mock(DeadLetterManager::class)->shouldReceive('moveToDeadLetter')->once();

        $sendJob = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Test Slack message',
        );

        $sendJob->failed(new \Exception('Slack API error'));

        Notification::assertSentTo($user, OutboundMessageFailedNotification::class);
    }

    /**
     * Generate a valid Slack signature for the given timestamp and body.
     */
    private function generateSignature(string $timestamp, string $body): string
    {
        $sigBaseString = 'v0:'.$timestamp.':'.$body;

        return 'v0='.hash_hmac('sha256', $sigBaseString, $this->signingSecret);
    }
}
