<?php

namespace Tests\Feature\Messenger;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Jobs\Messenger\SendAccountLinkPrompt;
use App\Models\AgentJob;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\PendingConfirmation;
use App\Models\User;
use App\Services\Messenger\AccountLinkTokenService;
use App\Services\Messenger\ChatSessionManager;
use App\Support\Agent\AuditLogger;
use App\Support\Messenger\ConnectorManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End-to-end integration tests for the Messenger Control Plane.
 *
 * These tests validate complete flows from webhook ingress through
 * to action execution and outbound message delivery.
 */
class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $slackAccount;

    private ConnectorAccount $telegramAccount;

    private string $slackSigningSecret = 'test-slack-signing-secret';

    private string $telegramSecretToken = 'test-telegram-secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Integration Test User',
            'email' => 'integration@test.local',
        ]);

        $this->slackAccount = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Integration Test Workspace',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'account_key' => 'T_INT_TEST',
            'credentials' => [
                'bot_token' => 'xoxb-integration-test-token',
                'signing_secret' => $this->slackSigningSecret,
            ],
            'config' => [
                'confirmation_required' => true,
                'default_verbosity' => 'summary',
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => $this->slackSigningSecret,
                ],
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'window_seconds' => 300,
                ],
            ],
        ]);

        $this->telegramAccount = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'name' => 'Integration Test Bot',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'account_key' => 'INT_BOT_123',
            'credentials' => [
                'bot_token' => '123456789:AABBCCDDEEFFtest-bot-token',
                'secret_token' => $this->telegramSecretToken,
            ],
            'config' => [
                'confirmation_required' => true,
                'default_verbosity' => 'summary',
                'signature_verification' => [
                    'scheme' => 'token',
                ],
                'replay_protection' => [
                    'strategy' => 'event_id_dedupe',
                    'dedupe_ttl_seconds' => 3600,
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // =============================================
    // Complete Chat Flow Tests
    // =============================================

    public function test_complete_chat_flow_creates_job(): void
    {
        // 1. Setup: Create user with linked Slack account
        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_LINKED_USER',
        ]);

        // Mock outbound Slack API calls
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => 'C_TEST_CHANNEL',
                'ts' => '1234567890.123456',
                'message' => ['text' => 'Job created successfully'],
            ], 200),
        ]);

        // 2. Simulate: Slack webhook with "create job to run backup daily at 2am"
        $timestamp = now()->timestamp;
        $eventPayload = [
            'token' => 'test-verification-token',
            'team_id' => 'T_INT_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_LINKED_USER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'create a job called "daily backup" that runs "php artisan backup:run" every day at 2am',
                'ts' => '1234567890.111111',
            ],
            'event_id' => 'Ev_INTEGRATION_TEST_1',
            'event_time' => $timestamp,
        ];

        $body = json_encode($eventPayload);
        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->slackSigningSecret);

        // Send webhook and process synchronously for testing
        Queue::fake();

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // Process the job synchronously
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            $job->handle(
                app(ConnectorManager::class),
                app(ChatSessionManager::class)
            );

            return true;
        });

        // 3. Assert: ChatSession created
        $this->assertDatabaseHas('chat_sessions', [
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'channel_id' => 'C_TEST_CHANNEL',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        // 4. Assert: ChatMessage stored
        $this->assertDatabaseHas('chat_messages', [
            'connector_account_id' => $this->slackAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'provider_message_id' => '1234567890.111111',
        ]);

        $message = ChatMessage::where('provider_message_id', '1234567890.111111')->first();
        $this->assertNotNull($message);
        $this->assertStringContains('daily backup', $message->content);
    }

    public function test_complete_chat_flow_with_telegram(): void
    {
        // 1. Setup: Create user with linked Telegram account
        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->telegramAccount->id,
            'provider_user_id' => '987654321',
        ]);

        // Mock outbound Telegram API calls
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 12345,
                    'chat' => ['id' => 987654321],
                ],
            ], 200),
        ]);

        // 2. Simulate: Telegram update with list jobs command
        $updatePayload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1001,
                'from' => [
                    'id' => 987654321,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => 987654321,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => 'list my jobs',
            ],
        ];

        Queue::fake();

        $response = $this->postJson(
            '/agent/api/v1/connectors/telegram/webhook/'.$this->telegramAccount->account_key,
            $updatePayload,
            [
                'X-Telegram-Bot-Api-Secret-Token' => $this->telegramSecretToken,
            ]
        );

        $response->assertOk();

        // Process the job synchronously
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            $job->handle(
                app(ConnectorManager::class),
                app(ChatSessionManager::class)
            );

            return true;
        });

        // 3. Assert: ChatSession created for Telegram
        $this->assertDatabaseHas('chat_sessions', [
            'user_id' => $this->user->id,
            'connector_account_id' => $this->telegramAccount->id,
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        // 4. Assert: ChatMessage stored
        $this->assertDatabaseHas('chat_messages', [
            'connector_account_id' => $this->telegramAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'list my jobs',
        ]);
    }

    // =============================================
    // Account Link Flow Tests
    // =============================================

    public function test_unlinked_user_receives_link_prompt(): void
    {
        // 1. Simulate: Webhook from unknown Slack user
        Queue::fake();
        Bus::fake([SendAccountLinkPrompt::class]);

        $timestamp = now()->timestamp;
        $eventPayload = [
            'token' => 'test-verification-token',
            'team_id' => 'T_INT_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_UNKNOWN_USER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'hello',
                'ts' => '1234567890.222222',
            ],
            'event_id' => 'Ev_INTEGRATION_TEST_2',
            'event_time' => $timestamp,
        ];

        $body = json_encode($eventPayload);
        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->slackSigningSecret);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertOk();

        // Process the inbound message job
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            $job->handle(
                app(ConnectorManager::class),
                app(ChatSessionManager::class)
            );

            return true;
        });

        // 2. Assert: Link prompt dispatched
        Bus::assertDispatched(SendAccountLinkPrompt::class);
    }

    public function test_account_link_flow_end_to_end(): void
    {
        $tokenService = app(AccountLinkTokenService::class);

        // 1. Generate token (simulating bot sending link prompt)
        $token = $tokenService->generate(
            provider: 'slack',
            providerUserId: 'U_NEW_USER_TO_LINK',
            connectorAccountId: $this->slackAccount->id
        );

        // 2. Visit: Link URL as unauthenticated user (should show login form)
        $response = $this->get(route('messenger.link.show', ['token' => $token]));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Messenger/LinkAccount'));

        // 3. Submit: Link as authenticated user
        $response = $this->actingAs($this->user)
            ->post(route('messenger.link.store', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));

        // 4. Assert: Account linked
        $this->assertDatabaseHas('messenger_identity_links', [
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_NEW_USER_TO_LINK',
        ]);

        // 5. Simulate: Second message from same user should now be processed normally
        Queue::fake();

        $timestamp = now()->timestamp;
        $eventPayload = [
            'token' => 'test-verification-token',
            'team_id' => 'T_INT_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_NEW_USER_TO_LINK',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'list my jobs',
                'ts' => '1234567890.333333',
            ],
            'event_id' => 'Ev_INTEGRATION_TEST_3',
            'event_time' => $timestamp,
        ];

        $body = json_encode($eventPayload);
        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->slackSigningSecret);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertOk();

        // 6. Assert: Message processed normally (session created)
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            $job->handle(
                app(ConnectorManager::class),
                app(ChatSessionManager::class)
            );

            return true;
        });

        $this->assertDatabaseHas('chat_sessions', [
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'channel_id' => 'C_TEST_CHANNEL',
        ]);
    }

    // =============================================
    // Confirmation Flow Tests
    // =============================================

    public function test_destructive_action_requires_confirmation(): void
    {
        // 1. Setup: Linked user with existing job
        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_CONFIRM_USER',
        ]);

        $job = AgentJob::create([
            'user_id' => $this->user->id,
            'name' => 'Job To Delete',
            'cron_expression' => '0 * * * *',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => 'php artisan test:command',
            'task_markdown_path' => '/tmp/integration-job.md',
            'working_directory' => '/tmp',
            'is_enabled' => true,
        ]);

        // Mock Slack API for confirmation prompt
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => 'C_TEST_CHANNEL',
                'ts' => '1234567890.444444',
                'message' => ['text' => 'Confirm deletion?'],
            ], 200),
        ]);

        // Create a session and message with delete action
        $session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'channel_id' => 'C_TEST_CHANNEL',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->slackAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'delete job '.$job->id,
            'idempotency_key' => hash('sha256', 'test-delete-message'),
        ]);

        // 2. Create action requiring confirmation
        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_DELETE,
            'parameters' => ['job_id' => $job->id],
            'status' => ChatAction::PENDING,
            'requires_confirmation' => true,
        ]);

        // 3. Assert: Confirmation prompt would be sent
        $this->assertTrue($action->requires_confirmation);
        $this->assertEquals(ChatAction::PENDING, $action->status);
        $this->assertNull($action->confirmed_at);

        // 4. Create pending confirmation
        $confirmation = PendingConfirmation::create([
            'chat_action_id' => $action->id,
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->slackAccount->id,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes(5),
        ]);

        // 5. Simulate: User confirms with "yes"
        $confirmation->confirm();
        $action->update([
            'status' => ChatAction::EXECUTING,
            'confirmed_at' => now(),
        ]);

        // 6. Assert: Confirmation recorded
        $this->assertNotNull($confirmation->fresh()->confirmed_at);
        $this->assertNotNull($action->fresh()->confirmed_at);
        $this->assertEquals(ChatAction::EXECUTING, $action->fresh()->status);
    }

    public function test_confirmation_timeout_cancels_action(): void
    {
        // Setup: Linked user
        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_TIMEOUT_USER',
        ]);

        $job = AgentJob::create([
            'user_id' => $this->user->id,
            'name' => 'Job To Delete (Timeout)',
            'cron_expression' => '0 * * * *',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => 'php artisan test:command',
            'task_markdown_path' => '/tmp/integration-job-timeout.md',
            'working_directory' => '/tmp',
            'is_enabled' => true,
        ]);

        // Create session and action requiring confirmation
        $session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'channel_id' => 'C_TIMEOUT_CHANNEL',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->slackAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'delete job '.$job->id,
            'idempotency_key' => hash('sha256', 'test-timeout-message'),
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_DELETE,
            'parameters' => ['job_id' => $job->id],
            'status' => ChatAction::PENDING,
            'requires_confirmation' => true,
        ]);

        // Create confirmation that expires in 5 minutes
        $startTime = now();
        Carbon::setTestNow($startTime);

        $confirmation = PendingConfirmation::create([
            'chat_action_id' => $action->id,
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->slackAccount->id,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'expires_at' => $startTime->copy()->addMinutes(5),
        ]);

        // Fast forward past expiration
        Carbon::setTestNow($startTime->copy()->addMinutes(6));

        // Assert: Confirmation is expired
        $this->assertTrue($confirmation->fresh()->isExpired());

        // Assert: Job still exists (deletion was not executed)
        $this->assertDatabaseHas('agent_jobs', ['id' => $job->id]);
    }

    // =============================================
    // Error Handling Tests
    // =============================================

    public function test_policy_violation_returns_helpful_error(): void
    {
        // 1. Setup: User without job access permissions
        $restrictedUser = User::factory()->create();

        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $restrictedUser->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_RESTRICTED_USER',
        ]);

        // Create a job owned by a different user
        $otherUser = User::factory()->create();
        $job = AgentJob::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Job',
            'cron_expression' => '0 * * * *',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => 'php artisan other:command',
            'task_markdown_path' => '/tmp/integration-other-job.md',
            'working_directory' => '/tmp',
            'is_enabled' => true,
        ]);

        // Create session for restricted user
        $session = ChatSession::factory()->create([
            'user_id' => $restrictedUser->id,
            'connector_account_id' => $this->slackAccount->id,
            'channel_id' => 'C_RESTRICTED_CHANNEL',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->slackAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'delete job '.$job->id,
            'idempotency_key' => hash('sha256', 'test-policy-message'),
        ]);

        // 2. Create action that should fail policy check
        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_DELETE,
            'parameters' => ['job_id' => $job->id],
            'status' => ChatAction::FAILED,
            'error' => 'You do not have permission to delete this job.',
        ]);

        // 3. Assert: Action failed with error
        $this->assertEquals(ChatAction::FAILED, $action->status);
        $this->assertNotNull($action->error);
        $this->assertStringContains('permission', $action->error);

        // 4. Assert: No job deleted
        $this->assertDatabaseHas('agent_jobs', ['id' => $job->id]);
    }

    public function test_invalid_signature_rejects_webhook(): void
    {
        $timestamp = now()->timestamp;
        $eventPayload = [
            'token' => 'test-verification-token',
            'team_id' => 'T_INT_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_HACKER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'delete all jobs',
                'ts' => '1234567890.555555',
            ],
            'event_id' => 'Ev_FAKE_EVENT',
            'event_time' => $timestamp,
        ];

        // Use wrong signing secret
        $body = json_encode($eventPayload);
        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, 'wrong-secret');

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );

        $response->assertStatus(401);
    }

    public function test_replay_attack_rejected(): void
    {
        // Create identity link
        MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_REPLAY_USER',
        ]);

        // Use timestamp from 10 minutes ago (outside 5 minute window)
        $oldTimestamp = now()->subMinutes(10)->timestamp;
        $eventPayload = [
            'token' => 'test-verification-token',
            'team_id' => 'T_INT_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_REPLAY_USER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'hello',
                'ts' => '1234567890.666666',
            ],
            'event_id' => 'Ev_OLD_EVENT',
            'event_time' => $oldTimestamp,
        ];

        $body = json_encode($eventPayload);
        $sigBaseString = 'v0:'.$oldTimestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->slackSigningSecret);

        $response = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $oldTimestamp,
            ]
        );

        $response->assertStatus(401);
    }

    // =============================================
    // Audit Trail Tests
    // =============================================

    public function test_chat_action_creates_audit_log(): void
    {
        // Setup: Linked user
        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_AUDIT_USER',
        ]);

        // Create job
        $job = AgentJob::create([
            'user_id' => $this->user->id,
            'name' => 'Audited Job',
            'cron_expression' => '0 * * * *',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => 'php artisan audit:test',
            'task_markdown_path' => '/tmp/integration-audit-job.md',
            'working_directory' => '/tmp',
            'is_enabled' => true,
        ]);

        // Create session and action
        $session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'channel_id' => 'C_AUDIT_CHANNEL',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->slackAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'update job '.$job->id.' schedule to every hour',
            'idempotency_key' => hash('sha256', 'test-audit-message'),
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_UPDATE,
            'parameters' => ['job_id' => $job->id, 'schedule' => '0 * * * *'],
            'status' => ChatAction::COMPLETED,
            'confirmed_at' => now(),
            'executed_at' => now(),
        ]);

        // Record audit log via the AuditLogger
        $auditLogger = app(AuditLogger::class);
        $auditLog = $auditLogger->recordMessengerAction(
            action: $action,
            targetType: 'agent_job',
            targetId: $job->id,
            changedFields: ['schedule'],
            before: ['schedule' => '0 0 * * *'],
            after: ['schedule' => '0 * * * *'],
        );

        // Assert: Audit log entry exists
        $this->assertDatabaseHas('agent_audit_logs', [
            'user_id' => $this->user->id,
            'actor_type' => 'messenger',
            'actor_id' => $action->id,
            'action' => ChatAction::ACTION_JOBS_UPDATE,
            'target_type' => 'agent_job',
            'target_id' => $job->id,
            'outcome' => 'success',
        ]);

        // Assert: Audit log has correct details
        $this->assertEquals(['schedule'], $auditLog->changed_fields_json);
        $this->assertEquals('messenger/slack', $auditLog->user_agent);
    }

    // =============================================
    // Idempotency Tests
    // =============================================

    public function test_duplicate_message_is_deduplicated(): void
    {
        // Setup: Linked user
        $identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->slackAccount->id,
            'provider_user_id' => 'U_DEDUPE_USER',
        ]);

        Queue::fake();

        $timestamp = now()->timestamp;
        $eventPayload = [
            'token' => 'test-verification-token',
            'team_id' => 'T_INT_TEST',
            'event' => [
                'type' => 'message',
                'user' => 'U_DEDUPE_USER',
                'channel' => 'C_TEST_CHANNEL',
                'text' => 'list my jobs',
                'ts' => '1234567890.777777',
            ],
            'event_id' => 'Ev_DEDUPE_TEST',
            'event_time' => $timestamp,
        ];

        $body = json_encode($eventPayload);
        $sigBaseString = 'v0:'.$timestamp.':'.$body;
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->slackSigningSecret);

        // Send first request
        $response1 = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );
        $response1->assertOk();

        // Process the first message
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            $job->handle(
                app(ConnectorManager::class),
                app(ChatSessionManager::class)
            );

            return true;
        });

        // Count messages after first request
        $messageCountAfterFirst = ChatMessage::where('provider_message_id', '1234567890.777777')->count();
        $this->assertEquals(1, $messageCountAfterFirst);

        // Send duplicate request (same event_id)
        $response2 = $this->postJson(
            '/agent/api/v1/connectors/slack/webhook',
            $eventPayload,
            [
                'X-Slack-Signature' => $signature,
                'X-Slack-Request-Timestamp' => $timestamp,
            ]
        );
        $response2->assertOk();

        // Process the duplicate
        Queue::assertPushed(ProcessInboundMessage::class);

        // Assert: Still only one message (duplicate was deduplicated)
        $messageCountAfterDuplicate = ChatMessage::where('provider_message_id', '1234567890.777777')->count();
        $this->assertEquals(1, $messageCountAfterDuplicate);
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
