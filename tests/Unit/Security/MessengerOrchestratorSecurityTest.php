<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Services\Credentials\CredentialsManager;
use App\Services\Runtime\CliRuntimeExecutor;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Services\Runtime\RuntimeLlmClient;
use App\Services\Runtime\SessionProcessManager;
use App\Services\Runtime\ToolGateway;
use App\Services\Security\MessengerSecurityGuard;
use App\Services\Security\TurnSecurityContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MessengerOrchestratorSecurityTest extends TestCase
{
    use RefreshDatabase;

    private ToolGateway $toolGateway;

    private RuntimeLlmClient $llmClient;

    private CliRuntimeExecutor $cliExecutor;

    private SessionProcessManager $sessionProcessManager;

    private CredentialsManager $credentialsManager;

    private MessengerSecurityGuard $securityGuard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolGateway = Mockery::mock(ToolGateway::class);
        $this->llmClient = Mockery::mock(RuntimeLlmClient::class);
        $this->cliExecutor = Mockery::mock(CliRuntimeExecutor::class);
        $this->sessionProcessManager = Mockery::mock(SessionProcessManager::class);
        $this->credentialsManager = Mockery::mock(CredentialsManager::class);
        $this->securityGuard = Mockery::mock(MessengerSecurityGuard::class);
    }

    private function makeOrchestrator(?MessengerSecurityGuard $guard = null): MessengerRuntimeOrchestrator
    {
        return new MessengerRuntimeOrchestrator(
            $this->toolGateway,
            $this->llmClient,
            $this->cliExecutor,
            $this->sessionProcessManager,
            $this->credentialsManager,
            $guard,
        );
    }

    private function createSession(): RuntimeSession
    {
        return RuntimeSession::factory()->standardMode()->create();
    }

    // ── CLI path: response text sanitized ──

    public function test_cli_path_response_text_is_sanitized(): void
    {
        $orchestrator = $this->makeOrchestrator($this->securityGuard);
        $session = $this->createSession();
        config(['runtime.use_cli' => true]);

        $this->sessionProcessManager->shouldReceive('isWrapperEnabled')->andReturn(false);
        $this->sessionProcessManager->shouldReceive('getRunnerSessionId')->andReturn(null);

        $this->cliExecutor->shouldReceive('executeTurn')
            ->once()
            ->andReturn(['status' => 'completed', 'text' => 'Result with <script>alert("xss")</script> content']);

        $this->securityGuard->shouldReceive('sanitizeResponse')
            ->with('Result with <script>alert("xss")</script> content')
            ->once()
            ->andReturn('Result with  content');

        $result = $orchestrator->executeTurn($session, 'test message');

        $this->assertSame('completed', $result['status']);
        $this->assertSame('Result with  content', $result['text']);
    }

    public function test_cli_path_clean_response_text_unchanged(): void
    {
        $orchestrator = $this->makeOrchestrator($this->securityGuard);
        $session = $this->createSession();
        config(['runtime.use_cli' => true]);

        $this->sessionProcessManager->shouldReceive('isWrapperEnabled')->andReturn(false);
        $this->sessionProcessManager->shouldReceive('getRunnerSessionId')->andReturn(null);

        $cleanText = 'The deployment completed successfully. All 12 tests pass.';

        $this->cliExecutor->shouldReceive('executeTurn')
            ->once()
            ->andReturn(['status' => 'completed', 'text' => $cleanText]);

        $this->securityGuard->shouldReceive('sanitizeResponse')
            ->with($cleanText)
            ->once()
            ->andReturn($cleanText);

        $result = $orchestrator->executeTurn($session, 'deploy');

        $this->assertSame($cleanText, $result['text']);
    }

    // ── In-app LLM path: TurnSecurityContext created and passed ──

    public function test_in_app_llm_path_creates_turn_security_context(): void
    {
        $orchestrator = $this->makeOrchestrator($this->securityGuard);
        $session = $this->createSession();
        config(['runtime.use_cli' => false]);

        $this->llmClient->shouldReceive('complete')
            ->once()
            ->andReturn([
                'content' => [['type' => 'text', 'text' => 'Done']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]);

        $this->securityGuard->shouldReceive('sanitizeResponse')
            ->with('Done')
            ->once()
            ->andReturn('Done');

        $result = $orchestrator->executeTurn($session, 'hello');

        $this->assertSame('completed', $result['status']);
        $this->assertSame('Done', $result['text']);
    }

    public function test_in_app_llm_path_passes_turn_context_to_tool_gateway(): void
    {
        $orchestrator = $this->makeOrchestrator($this->securityGuard);
        $session = $this->createSession();
        config(['runtime.use_cli' => false]);

        $this->llmClient->shouldReceive('complete')
            ->twice()
            ->andReturn(
                [
                    'content' => [
                        ['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'web', 'input' => ['operation' => 'fetch', 'url' => 'https://example.com']],
                    ],
                    'stop_reason' => 'tool_use',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ],
                [
                    'content' => [['type' => 'text', 'text' => 'Fetched result']],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
                ],
            );

        $this->toolGateway->shouldReceive('call')
            ->withArgs(function (string $name, RuntimeContext $ctx, array $args, ?TurnSecurityContext $turnCtx) {
                return $name === 'web' && $turnCtx instanceof TurnSecurityContext;
            })
            ->once()
            ->andReturn(ToolResult::success(['data' => 'ok'], 10));

        $this->securityGuard->shouldReceive('sanitizeResponse')
            ->with('Fetched result')
            ->once()
            ->andReturn('Fetched result');

        $result = $orchestrator->executeTurn($session, 'fetch example.com');

        $this->assertSame('completed', $result['status']);
    }

    // ── Null security guard: backward compatibility ──

    public function test_null_security_guard_no_sanitization(): void
    {
        $orchestrator = $this->makeOrchestrator(null);
        $session = $this->createSession();
        config(['runtime.use_cli' => true]);

        $this->sessionProcessManager->shouldReceive('isWrapperEnabled')->andReturn(false);
        $this->sessionProcessManager->shouldReceive('getRunnerSessionId')->andReturn(null);

        $textWithHtml = 'Result with <b>bold</b> text';

        $this->cliExecutor->shouldReceive('executeTurn')
            ->once()
            ->andReturn(['status' => 'completed', 'text' => $textWithHtml]);

        $result = $orchestrator->executeTurn($session, 'test');

        $this->assertSame($textWithHtml, $result['text']);
    }
}
