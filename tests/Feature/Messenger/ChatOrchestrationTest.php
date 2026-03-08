<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\DTOs\Messenger\ActionResult;
use App\Enums\Messenger\ChatActionType;
use App\Jobs\Messenger\ProcessChatIntent;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\PendingConfirmation;
use App\Models\User;
use App\Services\Messenger\ChatActionExecutor;
use App\Services\Messenger\ChatActionPolicyValidator;
use App\Services\Messenger\ChatIntentParser;
use App\Services\Messenger\ChatResponseFormatter;
use App\Services\Messenger\ConfirmationManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatOrchestrationTest extends TestCase
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
    // Intent Parsing Tests
    // =============================================

    public function test_intent_parser_recognizes_list_jobs_command(): void
    {
        $parser = app(ChatIntentParser::class);

        $message = $this->createInboundMessage('list my jobs');
        $parsedAction = $parser->parse($message, $this->session);

        $this->assertNotNull($parsedAction);
        $this->assertEquals(ChatActionType::JOBS_LIST, $parsedAction->type);
        $this->assertEquals(1.0, $parsedAction->confidence);
        $this->assertFalse($parsedAction->requiresConfirmation);
    }

    public function test_intent_parser_recognizes_list_active_jobs_command(): void
    {
        $parser = app(ChatIntentParser::class);

        $message = $this->createInboundMessage('list my active jobs');
        $parsedAction = $parser->parse($message, $this->session);

        $this->assertNotNull($parsedAction);
        $this->assertEquals(ChatActionType::JOBS_LIST, $parsedAction->type);
        $this->assertEquals(['filter' => 'active'], $parsedAction->parameters);
        $this->assertEquals(1.0, $parsedAction->confidence);
    }

    public function test_intent_parser_recognizes_list_runs_command(): void
    {
        $parser = app(ChatIntentParser::class);

        $message = $this->createInboundMessage('show active runs');
        $parsedAction = $parser->parse($message, $this->session);

        $this->assertNotNull($parsedAction);
        $this->assertEquals(ChatActionType::RUNS_LIST_ACTIVE, $parsedAction->type);
        $this->assertEquals(1.0, $parsedAction->confidence);
    }

    public function test_intent_parser_recognizes_stop_run_command(): void
    {
        $parser = app(ChatIntentParser::class);

        $message = $this->createInboundMessage('stop run abc-123-def');
        $parsedAction = $parser->parse($message, $this->session);

        $this->assertNotNull($parsedAction);
        $this->assertEquals(ChatActionType::RUNS_STOP, $parsedAction->type);
        $this->assertEquals(['run_id' => 'abc-123-def'], $parsedAction->parameters);
        $this->assertTrue($parsedAction->requiresConfirmation);
    }

    public function test_intent_parser_recognizes_retry_run_command(): void
    {
        $parser = app(ChatIntentParser::class);

        $message = $this->createInboundMessage('retry run xyz-789');
        $parsedAction = $parser->parse($message, $this->session);

        $this->assertNotNull($parsedAction);
        $this->assertEquals(ChatActionType::RUNS_RETRY, $parsedAction->type);
        $this->assertEquals(['run_id' => 'xyz-789'], $parsedAction->parameters);
    }

    public function test_intent_parser_recognizes_run_now_command(): void
    {
        $parser = app(ChatIntentParser::class);

        $message = $this->createInboundMessage('run job-456 now');
        $parsedAction = $parser->parse($message, $this->session);

        $this->assertNotNull($parsedAction);
        $this->assertEquals(ChatActionType::RUNS_RUN_NOW, $parsedAction->type);
        $this->assertEquals(['job_id' => 'job-456'], $parsedAction->parameters);
    }

    // =============================================
    // Policy Validation Tests
    // =============================================

    public function test_policy_validator_allows_query_actions(): void
    {
        $validator = app(ChatActionPolicyValidator::class);

        $message = $this->createInboundMessage('list jobs');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::JOBS_LIST->value,
            'parameters' => [],
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $result = $validator->validate($action, $this->user);

        $this->assertTrue($result->allowed);
    }

    public function test_policy_validator_blocks_channel_restricted_mutations(): void
    {
        // Configure channel restriction
        $this->account->update([
            'config' => array_merge($this->account->config ?? [], [
                'channel_restrictions' => ['C12345'],
            ]),
        ]);

        $validator = app(ChatActionPolicyValidator::class);

        $message = $this->createInboundMessage('stop run abc-123');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => 'abc-123'],
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $result = $validator->validate($action, $this->user);

        $this->assertFalse($result->allowed);
        $this->assertStringContainsString('read-only', $result->reason);
    }

    public function test_policy_validator_allows_queries_in_restricted_channel(): void
    {
        $this->account->update([
            'config' => array_merge($this->account->config ?? [], [
                'channel_restrictions' => ['C12345'],
            ]),
        ]);

        $validator = app(ChatActionPolicyValidator::class);

        $message = $this->createInboundMessage('list jobs');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::JOBS_LIST->value,
            'parameters' => [],
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $result = $validator->validate($action, $this->user);

        $this->assertTrue($result->allowed);
    }

    public function test_policy_validator_blocks_dangerous_commands(): void
    {
        $validator = app(ChatActionPolicyValidator::class);

        $message = $this->createInboundMessage('create job');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::JOBS_CREATE->value,
            'parameters' => [
                'name' => 'Dangerous Job',
                'command' => 'rm -rf /',
                'schedule' => '0 0 * * *',
            ],
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $result = $validator->validate($action, $this->user);

        $this->assertFalse($result->allowed);
        $this->assertStringContainsString('security policies', $result->reason);
    }

    // =============================================
    // Confirmation Flow Tests
    // =============================================

    public function test_confirmation_manager_creates_pending_confirmation(): void
    {
        $manager = app(ConfirmationManager::class);

        $message = $this->createInboundMessage('stop run abc-123');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => 'abc-123'],
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => true,
        ]);

        $confirmation = $manager->createPendingConfirmation(
            $action,
            $this->session,
            $this->account
        );

        $this->assertInstanceOf(PendingConfirmation::class, $confirmation);
        $this->assertEquals($action->id, $confirmation->chat_action_id);
        $this->assertTrue($confirmation->isPending());
        $this->assertFalse($confirmation->isExpired());
    }

    public function test_confirmation_manager_processes_yes_response(): void
    {
        $manager = app(ConfirmationManager::class);

        $message = $this->createInboundMessage('stop run abc-123');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => 'abc-123'],
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => true,
        ]);

        PendingConfirmation::create([
            'chat_action_id' => $action->id,
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'confirmation_token' => hash('sha256', 'test-token'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $confirmedAction = $manager->processConfirmationResponse(
            'yes',
            $this->session,
            $this->account
        );

        $this->assertNotNull($confirmedAction);
        $this->assertEquals($action->id, $confirmedAction->id);
        $this->assertNotNull($confirmedAction->fresh()->confirmed_at);
    }

    public function test_confirmation_manager_processes_no_response(): void
    {
        $manager = app(ConfirmationManager::class);

        $message = $this->createInboundMessage('stop run abc-123');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => 'abc-123'],
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => true,
        ]);

        PendingConfirmation::create([
            'chat_action_id' => $action->id,
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'confirmation_token' => hash('sha256', 'test-token'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $confirmedAction = $manager->processConfirmationResponse(
            'no',
            $this->session,
            $this->account
        );

        $this->assertNull($confirmedAction);
        $this->assertEquals(ChatAction::STATUS_CANCELLED, $action->fresh()->status);
    }

    public function test_confirmation_timeout_sets_action_status(): void
    {
        $manager = app(ConfirmationManager::class);

        $message = $this->createInboundMessage('stop run abc-123');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => 'abc-123'],
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => true,
        ]);

        PendingConfirmation::create([
            'chat_action_id' => $action->id,
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'confirmation_token' => hash('sha256', 'test-token'),
            'expires_at' => now()->subMinutes(1), // Already expired
        ]);

        $cleanedUp = $manager->cleanupExpired();

        $this->assertEquals(1, $cleanedUp);
        $this->assertEquals(ChatAction::STATUS_TIMEOUT, $action->fresh()->status);
    }

    // =============================================
    // Action Execution Tests
    // =============================================

    public function test_action_executor_handles_jobs_list(): void
    {
        $executor = app(ChatActionExecutor::class);

        $message = $this->createInboundMessage('list jobs');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::JOBS_LIST->value,
            'parameters' => [],
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $result = $executor->execute($action, $this->user);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('jobs', $result->data);
    }

    public function test_action_executor_handles_runs_stop(): void
    {
        $executor = app(ChatActionExecutor::class);

        // Create a real job and run for this user
        $job = AgentJob::factory()->create(['user_id' => $this->user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_RUNNING,
        ]);

        $message = $this->createInboundMessage("stop run {$run->id}");
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => $run->id],
            'status' => ChatAction::STATUS_PENDING,
            'confirmed_at' => now(), // Mark as already confirmed
        ]);

        $result = $executor->execute($action, $this->user);

        $this->assertTrue($result->success, "Expected success but got error: {$result->error}");
        $this->assertStringContainsString('stopped successfully', $result->message);
    }

    public function test_action_executor_validates_parameters(): void
    {
        $executor = app(ChatActionExecutor::class);

        $message = $this->createInboundMessage('stop run');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => [], // Missing run_id
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $result = $executor->execute($action, $this->user);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No target run specified', $result->error);
    }

    // =============================================
    // Response Formatting Tests
    // =============================================

    public function test_response_formatter_formats_jobs_list(): void
    {
        $formatter = app(ChatResponseFormatter::class);

        $message = $this->createInboundMessage('list jobs');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::JOBS_LIST->value,
            'parameters' => [],
            'status' => ChatAction::STATUS_COMPLETED,
        ]);

        $result = ActionResult::success(
            data: [
                'jobs' => [
                    ['id' => 'job-001', 'name' => 'Daily Backup', 'enabled' => true],
                    ['id' => 'job-002', 'name' => 'Hourly Sync', 'enabled' => false],
                ],
                'total' => 2,
            ],
            message: "Your jobs (2):\n- [job-001] Daily Backup (active)\n- [job-002] Hourly Sync (paused)"
        );

        $formatted = $formatter->format($result, $action, $this->account);

        $this->assertStringContainsString('List Jobs', $formatted);
        $this->assertStringContainsString('Daily Backup', $formatted);
        $this->assertStringContainsString('job-001', $formatted);
    }

    public function test_response_formatter_formats_active_runs_with_nested_job_payload(): void
    {
        $formatter = app(ChatResponseFormatter::class);

        $message = $this->createInboundMessage('show active runs');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_LIST_ACTIVE->value,
            'parameters' => [],
            'status' => ChatAction::STATUS_COMPLETED,
        ]);

        $result = ActionResult::success(
            data: [
                'runs' => [
                    [
                        'id' => 'run-123',
                        'status' => 'running',
                        'job' => ['name' => 'Nightly Sync'],
                    ],
                ],
            ],
            message: "Active runs (1):\n• [run-123] Nightly Sync (running)"
        );

        $formatted = $formatter->format($result, $action, $this->account);

        $this->assertStringContainsString('List Active Runs', $formatted);
        $this->assertStringContainsString('Nightly Sync', $formatted);
        $this->assertStringContainsString('run-123', $formatted);
    }

    public function test_response_formatter_includes_ids_in_response(): void
    {
        $formatter = app(ChatResponseFormatter::class);

        $message = $this->createInboundMessage('run job-123 now');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_RUN_NOW->value,
            'parameters' => ['job_id' => 'job-123'],
            'status' => ChatAction::STATUS_COMPLETED,
        ]);

        $result = ActionResult::success(
            data: [
                'job_id' => 'job-123',
                'run_id' => 'run-456',
                'queued_at' => now()->toIso8601String(),
            ],
            message: 'Job `job-123` queued (run `run-456`)'
        );

        $formatted = $formatter->format($result, $action, $this->account);

        $this->assertStringContainsString('job-123', $formatted);
        $this->assertStringContainsString('run-456', $formatted);
    }

    public function test_response_formatter_formats_errors_with_suggestions(): void
    {
        $formatter = app(ChatResponseFormatter::class);

        $message = $this->createInboundMessage('stop run invalid');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::RUNS_STOP->value,
            'parameters' => ['run_id' => 'invalid'],
            'status' => ChatAction::STATUS_FAILED,
        ]);

        $error = new \RuntimeException('Run not found');
        $formatted = $formatter->formatError($error, $action, $this->account);

        $this->assertStringContainsString('Error', $formatted);
        $this->assertStringContainsString('Stop Run', $formatted);
        $this->assertStringContainsString('not found', $formatted);
        $this->assertStringContainsString('Suggestions', $formatted);
    }

    // =============================================
    // End-to-End Orchestration Tests
    // =============================================

    public function test_process_chat_intent_job_executes_query(): void
    {
        $message = $this->createInboundMessage('list my jobs');

        $adapterMock = \Mockery::mock(\App\Contracts\Messenger\ConnectorAdapterInterface::class);
        $adapterMock->shouldReceive('supportsReactions')->andReturn(false);
        $adapterMock->shouldReceive('supportsMessageEditing')->andReturn(false);
        $adapterMock->shouldReceive('sendMessage')->andReturn(
            new \App\DTOs\Messenger\ProviderResponse(
                success: true,
                providerMessageId: 'msg-123'
            )
        );

        // Create a mock ConnectorManager that will be passed directly
        $connectorManagerMock = new class($adapterMock) extends ConnectorManager
        {
            private $adapter;

            public function __construct($adapter)
            {
                $this->adapter = $adapter;
            }

            public function resolve(string $provider): \App\Contracts\Messenger\ConnectorAdapterInterface
            {
                return $this->adapter;
            }
        };

        $job = new ProcessChatIntent(
            messageId: $message->id,
            sessionId: $this->session->id,
            userId: $this->user->id
        );

        $job->handle(
            app(ChatIntentParser::class),
            app(ChatActionExecutor::class),
            app(ConfirmationManager::class),
            app(ChatResponseFormatter::class),
            $connectorManagerMock,
            app(\App\Services\Messenger\CommandRouter::class)
        );

        // Verify action was created and completed
        $action = ChatAction::where('chat_message_id', $message->id)->first();

        $this->assertNotNull($action);
        $this->assertEquals(ChatActionType::JOBS_LIST->value, $action->action_type);
        $this->assertEquals(ChatAction::STATUS_COMPLETED, $action->status);
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function createInboundMessage(string $content): ChatMessage
    {
        return ChatMessage::create([
            'chat_session_id' => $this->session->id,
            'connector_account_id' => $this->account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => $content,
            'idempotency_key' => hash('sha256', uniqid()),
            'provider_timestamp' => now(),
        ]);
    }
}
