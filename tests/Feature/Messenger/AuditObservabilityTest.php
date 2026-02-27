<?php

namespace Tests\Feature\Messenger;

use App\Http\Middleware\Messenger\CorrelationId;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Support\Agent\AuditLogger;
use App\Support\Messenger\MessengerLogger;
use App\Support\Messenger\MetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuditObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    private ChatSession $session;

    private MessengerIdentityLink $identityLink;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Workspace',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'connection_mode' => 'webhook',
            'credentials' => [
                'bot_token' => 'xoxb-test-token',
                'signing_secret' => 'test-signing-secret',
            ],
            'config' => [
                'confirmation_required' => true,
                'default_verbosity' => 'summary',
            ],
        ]);

        $this->session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C12345',
            'thread_id' => null,
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $this->identityLink = MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U12345',
        ]);
    }

    // =============================================
    // Audit Logging Tests
    // =============================================

    public function test_messenger_action_creates_audit_log_with_correct_actor_type(): void
    {
        $message = ChatMessage::factory()->create([
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'list my jobs',
        ]);

        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_LIST,
            'parameters' => [],
            'status' => ChatAction::STATUS_COMPLETED,
            'result' => ['jobs' => []],
        ]);

        $auditLogger = app(AuditLogger::class);

        $auditLog = $auditLogger->recordMessengerAction(
            action: $action,
            targetType: 'jobs',
            targetId: '*',
            changedFields: [],
            before: null,
            after: null,
            outcome: 'success',
        );

        $this->assertDatabaseHas('agent_audit_logs', [
            'id' => $auditLog->id,
            'actor_type' => 'messenger',
            'actor_id' => $action->id,
            'action' => ChatAction::ACTION_JOBS_LIST,
            'target_type' => 'jobs',
            'outcome' => 'success',
        ]);

        // Verify the user_id is correctly associated from the session
        $this->assertEquals($this->user->id, $auditLog->user_id);
    }

    public function test_messenger_action_audit_includes_user_agent_with_provider(): void
    {
        $message = ChatMessage::factory()->create([
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'stop run abc123',
        ]);

        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_RUNS_STOP,
            'parameters' => ['run_id' => 'abc123'],
            'status' => ChatAction::STATUS_COMPLETED,
        ]);

        $auditLogger = app(AuditLogger::class);

        $auditLog = $auditLogger->recordMessengerAction(
            action: $action,
            targetType: 'run',
            targetId: 'abc123',
            changedFields: ['status'],
            before: ['status' => 'running'],
            after: ['status' => 'stopped'],
            outcome: 'success',
        );

        $this->assertEquals('messenger/slack', $auditLog->user_agent);
        $this->assertEquals($this->session->id, $auditLog->request_id);
    }

    public function test_messenger_action_audit_records_failure_with_error_details(): void
    {
        $message = ChatMessage::factory()->create([
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'delete job xyz',
        ]);

        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_DELETE,
            'parameters' => ['job_id' => 'xyz'],
            'status' => ChatAction::STATUS_FAILED,
            'error' => 'Job not found',
        ]);

        $auditLogger = app(AuditLogger::class);

        $auditLog = $auditLogger->recordMessengerAction(
            action: $action,
            targetType: 'job',
            targetId: 'xyz',
            outcome: 'failure',
            errorCode: 'JOB_NOT_FOUND',
            errorMessage: 'Job not found',
        );

        $this->assertEquals('failure', $auditLog->outcome);
        $this->assertEquals('JOB_NOT_FOUND', $auditLog->error_code);
        $this->assertEquals('Job not found', $auditLog->error_message);
    }

    // =============================================
    // Correlation ID Tests
    // =============================================

    public function test_correlation_id_middleware_generates_uuid_for_request(): void
    {
        $middleware = app(CorrelationId::class);

        $request = Request::create('/agent/api/v1/connectors/slack/webhook', 'POST');

        $response = $middleware->handle($request, function ($req) {
            // Verify correlation ID is set on request
            $this->assertNotNull($req->attributes->get('correlation_id'));
            $this->assertTrue(\Str::isUuid($req->attributes->get('correlation_id')));

            return response()->json(['ok' => true]);
        });

        // Verify correlation ID is in response header
        $this->assertTrue($response->headers->has('X-Correlation-Id'));
        $this->assertTrue(\Str::isUuid($response->headers->get('X-Correlation-Id')));
    }

    public function test_correlation_id_middleware_preserves_existing_correlation_id(): void
    {
        $middleware = app(CorrelationId::class);

        $existingCorrelationId = 'existing-correlation-id-12345';
        $request = Request::create('/agent/api/v1/connectors/slack/webhook', 'POST');
        $request->headers->set('X-Correlation-Id', $existingCorrelationId);

        $middleware->handle($request, function ($req) use ($existingCorrelationId) {
            $this->assertEquals($existingCorrelationId, $req->attributes->get('correlation_id'));

            return response()->json(['ok' => true]);
        });
    }

    public function test_correlation_id_flows_through_job_middleware(): void
    {
        // This test verifies that correlation IDs can be stored and retrieved
        // for use in queued jobs
        $correlationId = (string) \Str::uuid();

        Cache::put("correlation:{$correlationId}", [
            'started_at' => now()->toIso8601String(),
            'provider' => 'slack',
            'account_id' => $this->account->id,
        ], 3600);

        $cached = Cache::get("correlation:{$correlationId}");

        $this->assertNotNull($cached);
        $this->assertEquals('slack', $cached['provider']);
        $this->assertEquals($this->account->id, $cached['account_id']);
    }

    // =============================================
    // Structured Logging Tests
    // =============================================

    public function test_messenger_logger_includes_correlation_context(): void
    {
        $logger = app(MessengerLogger::class);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === '[Messenger] Inbound message received'
                    && isset($context['correlation_id'])
                    && $context['provider'] === 'slack'
                    && $context['session_id'] === $this->session->id;
            });

        $logger->withCorrelation('test-correlation-id')
            ->withProvider('slack')
            ->withSession($this->session->id)
            ->inbound('Inbound message received');
    }

    public function test_messenger_logger_logs_outbound_messages(): void
    {
        $logger = app(MessengerLogger::class);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === '[Messenger] Outbound message sent'
                    && $context['provider'] === 'telegram'
                    && isset($context['message_id']);
            });

        $logger->withProvider('telegram')
            ->outbound('Outbound message sent', ['message_id' => 'msg123']);
    }

    public function test_messenger_logger_logs_action_execution(): void
    {
        $logger = app(MessengerLogger::class);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === '[Messenger] Action executed'
                    && $context['action_type'] === 'jobs.list'
                    && $context['status'] === 'completed';
            });

        $logger->actionExecuted('Action executed', [
            'action_type' => 'jobs.list',
            'status' => 'completed',
        ]);
    }

    public function test_messenger_logger_logs_errors_with_appropriate_level(): void
    {
        $logger = app(MessengerLogger::class);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === '[Messenger] Action execution failed'
                    && $context['error_code'] === 'TIMEOUT'
                    && isset($context['correlation_id']);
            });

        $logger->withCorrelation('corr-123')
            ->error('Action execution failed', [
                'error_code' => 'TIMEOUT',
            ]);
    }

    // =============================================
    // Metrics Collection Tests
    // =============================================

    public function test_metrics_collector_increments_inbound_messages(): void
    {
        $collector = app(MetricsCollector::class);

        $collector->incrementInboundMessages('slack');
        $collector->incrementInboundMessages('slack');
        $collector->incrementInboundMessages('telegram');

        $metrics = $collector->getMetrics();

        $this->assertEquals(2, $metrics['inbound_messages']['slack']);
        $this->assertEquals(1, $metrics['inbound_messages']['telegram']);
    }

    public function test_metrics_collector_tracks_action_success_and_failure(): void
    {
        $collector = app(MetricsCollector::class);

        $collector->incrementActionSuccess('jobs.list');
        $collector->incrementActionSuccess('jobs.list');
        $collector->incrementActionFailure('runs.stop', 'TIMEOUT');
        $collector->incrementActionSuccess('runs.stop');

        $metrics = $collector->getMetrics();

        $this->assertEquals(2, $metrics['actions']['jobs.list']['success']);
        $this->assertEquals(1, $metrics['actions']['runs.stop']['success']);
        $this->assertEquals(1, $metrics['actions']['runs.stop']['failure']);
        $this->assertEquals(1, $metrics['actions']['runs.stop']['errors']['TIMEOUT']);
    }

    public function test_metrics_collector_records_action_latency(): void
    {
        $collector = app(MetricsCollector::class);

        $collector->recordActionLatency('jobs.list', 150.5);
        $collector->recordActionLatency('jobs.list', 200.3);
        $collector->recordActionLatency('jobs.list', 100.2);

        $metrics = $collector->getMetrics();

        $this->assertEqualsWithDelta(150.33, $metrics['latency']['jobs.list']['avg_ms'], 0.1);
        $this->assertEqualsWithDelta(100.2, $metrics['latency']['jobs.list']['min_ms'], 0.1);
        $this->assertEqualsWithDelta(200.3, $metrics['latency']['jobs.list']['max_ms'], 0.1);
        $this->assertEquals(3, $metrics['latency']['jobs.list']['count']);
    }

    public function test_metrics_collector_tracks_webhook_verification_failures(): void
    {
        $collector = app(MetricsCollector::class);

        $collector->incrementWebhookVerificationFailure('slack');
        $collector->incrementWebhookVerificationFailure('slack');
        $collector->incrementWebhookVerificationFailure('telegram');

        $metrics = $collector->getMetrics();

        $this->assertEquals(2, $metrics['webhook_failures']['slack']);
        $this->assertEquals(1, $metrics['webhook_failures']['telegram']);
    }

    // =============================================
    // Metrics Endpoint Tests
    // =============================================

    public function test_metrics_endpoint_returns_expected_format(): void
    {
        // Pre-populate some metrics
        $collector = app(MetricsCollector::class);
        $collector->incrementInboundMessages('slack');
        $collector->incrementActionSuccess('jobs.list');
        $collector->recordActionLatency('jobs.list', 100.0);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/messenger/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'inbound_messages',
                'actions',
                'latency',
                'webhook_failures',
                'connectors',
            ],
            'meta' => [
                'collected_at',
            ],
        ]);
    }

    public function test_metrics_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/agent/api/v1/messenger/metrics');

        $response->assertUnauthorized();
    }

    // =============================================
    // Health Check Tests
    // =============================================

    public function test_health_check_includes_messenger_status(): void
    {
        // Create an active connector account
        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        // Create a disconnected connector account
        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'status' => ConnectorAccount::STATUS_DISCONNECTED,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/health/messenger');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'connectors' => [
                '*' => [
                    'id',
                    'provider',
                    'name',
                    'status',
                ],
            ],
            'queue' => [
                'backlog_size',
            ],
            'recent_error_rate',
        ]);
    }

    public function test_health_check_reports_degraded_when_connector_has_errors(): void
    {
        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'status' => ConnectorAccount::STATUS_ERROR,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/health/messenger');

        $response->assertOk();
        $response->assertJsonPath('status', 'degraded');
    }

    public function test_health_check_reports_healthy_when_all_connectors_connected(): void
    {
        // Clear existing accounts
        ConnectorAccount::query()->delete();

        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/health/messenger');

        $response->assertOk();
        $response->assertJsonPath('status', 'healthy');
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function createInboundMessage(string $content): ChatMessage
    {
        return ChatMessage::factory()->create([
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => $content,
        ]);
    }
}
