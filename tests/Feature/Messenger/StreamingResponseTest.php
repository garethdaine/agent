<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Messenger\ChatActionType;
use App\Jobs\Messenger\ProcessChatIntent;
use App\Jobs\Messenger\SendOutboundMessage;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Services\Messenger\ChatActionExecutor;
use App\Services\Messenger\ChatIntentParser;
use App\Services\Messenger\ChatResponseFormatter;
use App\Services\Messenger\CommandRouter;
use App\Services\Messenger\ConfirmationManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class StreamingResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    private ChatSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'name' => 'Test Discord',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'connection_mode' => 'webhook',
            'credentials' => [
                'bot_token' => 'fake-bot-token',
            ],
            'config' => [
                'default_verbosity' => 'summary',
            ],
        ]);

        $this->session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'channel_id' => 'C12345',
            'thread_id' => null,
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U12345',
        ]);
    }

    public function test_general_task_routes_to_runtime_and_sends_placeholder(): void
    {
        Queue::fake();

        $editContents = [];

        $adapterMock = Mockery::mock(ConnectorAdapterInterface::class);
        $adapterMock->shouldReceive('supportsReactions')->andReturn(false);
        $adapterMock->shouldReceive('supportsMessageEditing')->andReturn(true);
        $adapterMock->shouldReceive('sendMessage')->once()->andReturn(
            ProviderResponse::success('placeholder-msg-id')
        );
        $adapterMock->shouldReceive('editMessage')
            ->once()
            ->with(Mockery::any(), 'placeholder-msg-id', '⏳ Processing request…')
            ->andReturn(ProviderResponse::success('placeholder-msg-id'));

        $connectorManager = $this->createConnectorManager($adapterMock);

        $intentParser = Mockery::mock(ChatIntentParser::class);
        $intentParser->shouldReceive('parse')->andReturn(
            $this->makeParsedAction('Say hello')
        );

        $message = $this->createInboundMessage('Say hello');

        $job = new ProcessChatIntent(
            messageId: $message->id,
            sessionId: $this->session->id,
            userId: $this->user->id
        );

        $job->handle(
            $intentParser,
            app(ChatActionExecutor::class),
            app(ConfirmationManager::class),
            app(ChatResponseFormatter::class),
            $connectorManager,
            app(CommandRouter::class)
        );

        Queue::assertPushed(ProcessRuntimeTurnJob::class);

        $action = ChatAction::where('chat_message_id', $message->id)->first();
        $this->assertNull($action, 'General task should not create a ChatAction; it is routed to runtime.');
    }

    public function test_non_editing_adapter_sends_runtime_placeholder_message(): void
    {
        Queue::fake();

        $adapterMock = Mockery::mock(ConnectorAdapterInterface::class);
        $adapterMock->shouldReceive('supportsReactions')->andReturn(false);
        $adapterMock->shouldReceive('supportsMessageEditing')->andReturn(false);
        $adapterMock->shouldNotReceive('editMessage');
        $adapterMock->shouldReceive('sendMessage')->zeroOrMoreTimes()->andReturn(
            ProviderResponse::success('direct-msg-id')
        );

        $connectorManager = $this->createConnectorManager($adapterMock);

        $intentParser = Mockery::mock(ChatIntentParser::class);
        $intentParser->shouldReceive('parse')->andReturn(
            $this->makeParsedAction('Say hello')
        );

        $message = $this->createInboundMessage('Say hello');

        $job = new ProcessChatIntent(
            messageId: $message->id,
            sessionId: $this->session->id,
            userId: $this->user->id
        );

        $job->handle(
            $intentParser,
            app(ChatActionExecutor::class),
            app(ConfirmationManager::class),
            app(ChatResponseFormatter::class),
            $connectorManager,
            app(CommandRouter::class)
        );

        Queue::assertPushed(ProcessRuntimeTurnJob::class);

        Queue::assertPushed(SendOutboundMessage::class, function (SendOutboundMessage $job) {
            return str_contains($job->content, 'Processing request');
        });
    }

    public function test_general_task_routes_to_runtime_without_executor(): void
    {
        Queue::fake();

        $adapterMock = Mockery::mock(ConnectorAdapterInterface::class);
        $adapterMock->shouldReceive('supportsReactions')->andReturn(false);
        $adapterMock->shouldReceive('supportsMessageEditing')->andReturn(true);
        $adapterMock->shouldReceive('sendMessage')->once()->andReturn(
            ProviderResponse::success('placeholder-msg-id')
        );
        $adapterMock->shouldReceive('editMessage')
            ->once()
            ->with(Mockery::any(), 'placeholder-msg-id', '⏳ Processing request…')
            ->andReturn(ProviderResponse::success('placeholder-msg-id'));

        $connectorManager = $this->createConnectorManager($adapterMock);

        $intentParser = Mockery::mock(ChatIntentParser::class);
        $intentParser->shouldReceive('parse')->andReturn(
            $this->makeParsedAction('Do something')
        );

        $message = $this->createInboundMessage('Do something');

        $job = new ProcessChatIntent(
            messageId: $message->id,
            sessionId: $this->session->id,
            userId: $this->user->id
        );

        $job->handle(
            $intentParser,
            app(ChatActionExecutor::class),
            app(ConfirmationManager::class),
            app(ChatResponseFormatter::class),
            $connectorManager,
            app(CommandRouter::class)
        );

        Queue::assertPushed(ProcessRuntimeTurnJob::class);

        $action = ChatAction::where('chat_message_id', $message->id)->first();
        $this->assertNull($action, 'General task is routed to runtime; no ChatAction created.');
    }

    public function test_general_task_dispatches_runtime_job_even_when_placeholder_fails(): void
    {
        Queue::fake();

        $adapterMock = Mockery::mock(ConnectorAdapterInterface::class);
        $adapterMock->shouldReceive('supportsReactions')->andReturn(false);
        $adapterMock->shouldReceive('supportsMessageEditing')->andReturn(true);
        $adapterMock->shouldReceive('sendMessage')
            ->andReturn(ProviderResponse::failure('Rate limited'));
        $adapterMock->shouldNotReceive('editMessage');

        $connectorManager = $this->createConnectorManager($adapterMock);

        $intentParser = Mockery::mock(ChatIntentParser::class);
        $intentParser->shouldReceive('parse')->andReturn(
            $this->makeParsedAction('Hello')
        );

        $message = $this->createInboundMessage('Hello');

        $job = new ProcessChatIntent(
            messageId: $message->id,
            sessionId: $this->session->id,
            userId: $this->user->id
        );

        $job->handle(
            $intentParser,
            app(ChatActionExecutor::class),
            app(ConfirmationManager::class),
            app(ChatResponseFormatter::class),
            $connectorManager,
            app(CommandRouter::class)
        );

        Queue::assertPushed(ProcessRuntimeTurnJob::class);

        $action = ChatAction::where('chat_message_id', $message->id)->first();
        $this->assertNull($action, 'General task is routed to runtime; no ChatAction created.');
    }

    public function test_executor_streaming_falls_back_for_non_streamable_handler(): void
    {
        $executor = app(ChatActionExecutor::class);

        $message = $this->createInboundMessage('list my jobs');
        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => ChatActionType::JOBS_LIST->value,
            'parameters' => [],
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $chunks = [];
        $result = $executor->executeStreaming($action, $this->user, function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertTrue($result->success);
        $this->assertNotEmpty($chunks);
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

    private function createConnectorManager(ConnectorAdapterInterface $adapter): ConnectorManager
    {
        return new class($adapter) extends ConnectorManager
        {
            public function __construct(private ConnectorAdapterInterface $adapter) {}

            public function resolve(string $provider): ConnectorAdapterInterface
            {
                return $this->adapter;
            }
        };
    }

    private function makeParsedAction(string $taskDescription): \App\DTOs\Messenger\ParsedAction
    {
        return new \App\DTOs\Messenger\ParsedAction(
            type: ChatActionType::GENERAL_TASK,
            parameters: ['task_description' => $taskDescription],
            confidence: 0.9,
            requiresConfirmation: false,
            rawIntent: $taskDescription,
        );
    }
}
