<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Contracts\Runtime\ToolAdapterInterface;
use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\DTOs\Security\FirewallDecision;
use App\DTOs\Security\InjectionDetectionResult;
use App\DTOs\Security\SanitizationResult;
use App\DTOs\Security\SecurityToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Security\ContentTrustLevel;
use App\Enums\Security\InjectionAction;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\ApprovalGate;
use App\Services\Runtime\PolicyEngine;
use App\Services\Runtime\ToolGateway;
use App\Services\Security\ContentSanitizer;
use App\Services\Security\ContentSecurityMiddleware;
use App\Services\Security\ContentTrustClassifier;
use App\Services\Security\InjectionDetectionEngine;
use App\Services\Security\InstructionFirewall;
use App\Services\Security\SecurityConfigProvider;
use App\Services\Security\SecurityEventLogger;
use App\Services\Security\TurnSecurityContext;
use App\Support\Agent\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolGatewaySecurityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private PolicyEngine $policyEngine;

    private ApprovalGate $approvalGate;

    private AuditLogger $auditLogger;

    private ContentSecurityMiddleware $securityMiddleware;

    private ToolGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policyEngine = $this->createMock(PolicyEngine::class);
        $this->policyEngine->method('isToolAllowedByPolicy')->willReturn(true);
        $this->policyEngine->method('requiresApproval')->willReturn(false);

        $this->auditLogger = $this->createMock(AuditLogger::class);

        $this->approvalGate = new ApprovalGate(
            policyEngine: $this->policyEngine,
            auditLogger: $this->auditLogger,
        );

        $this->gateway = new ToolGateway(
            policyEngine: $this->policyEngine,
            approvalGate: $this->approvalGate,
            auditLogger: $this->auditLogger,
        );
    }

    public function test_call_without_turn_context_performs_no_security_processing(): void
    {
        $adapter = $this->createMockAdapter('fs', ToolResult::success('file content', 10));
        $this->gateway->register($adapter);

        $context = $this->createContext(RuntimeMode::Standard);

        // Call without turnContext — no security middleware should be invoked
        $result = $this->gateway->call('fs', $context, ['operation' => 'read', 'path' => '/tmp/test']);

        $this->assertTrue($result->success);
        $this->assertSame('file content', $result->data);

        // Verify no security columns set on the tool call record
        $toolCall = RuntimeToolCall::latest('id')->first();
        $this->assertNotNull($toolCall);
        $this->assertNull($toolCall->content_trust_level);
        $this->assertNull($toolCall->injection_score);
    }

    public function test_call_with_turn_context_persists_security_metadata(): void
    {
        $adapter = $this->createMockAdapter('web', ToolResult::success('page content', 50));
        $this->gateway->register($adapter);

        $middleware = $this->createSecurityMiddleware(
            trustLevel: ContentTrustLevel::Untrusted,
            injectionScore: 0.2,
            injectionAction: InjectionAction::Log,
            sanitized: false,
        );
        $this->gateway->setSecurityMiddleware($middleware);

        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;

        $result = $this->gateway->call('web', $context, ['operation' => 'fetch'], $turnContext);

        $this->assertTrue($result->success);

        // Verify security metadata persisted to RuntimeToolCall
        $toolCall = RuntimeToolCall::latest('id')->first();
        $this->assertNotNull($toolCall);
        $this->assertSame(ContentTrustLevel::Untrusted, $toolCall->content_trust_level);
        $this->assertSame(0.2, (float) $toolCall->injection_score);
        $this->assertSame('log', $toolCall->injection_action);
        $this->assertFalse($toolCall->content_sanitized);
    }

    public function test_pre_execution_firewall_blocks_tainted_sensitive_tool(): void
    {
        $adapter = $this->createMockAdapter('fs', ToolResult::success('written', 10));
        $this->gateway->register($adapter);

        // Create middleware that blocks pre-execution
        $middleware = $this->createMock(ContentSecurityMiddleware::class);
        $middleware->method('checkPreExecution')
            ->willReturn(ToolResult::success(
                ['status' => 'pending_approval', 'reason' => 'Tainted turn'],
                0,
            ));

        $this->gateway->setSecurityMiddleware($middleware);

        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;
        $turnContext->markTainted('web.fetch');

        $result = $this->gateway->call('fs', $context, ['operation' => 'write'], $turnContext);

        $this->assertTrue($result->success);
        $this->assertSame('pending_approval', $result->data['status']);
    }

    public function test_pre_execution_firewall_allows_untainted_context(): void
    {
        $adapter = $this->createMockAdapter('fs', ToolResult::success('read result', 10));
        $this->gateway->register($adapter);

        $middleware = $this->createSecurityMiddleware(
            trustLevel: ContentTrustLevel::Trusted,
            injectionScore: 0.0,
            injectionAction: InjectionAction::Log,
            sanitized: false,
            firewallAllows: true,
        );
        $this->gateway->setSecurityMiddleware($middleware);

        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;

        $result = $this->gateway->call('fs', $context, ['operation' => 'read'], $turnContext);

        $this->assertTrue($result->success);
        $this->assertSame('read result', $result->data);
    }

    public function test_security_result_original_is_returned_to_caller(): void
    {
        $originalResult = ToolResult::success(['key' => 'value'], 25);
        $adapter = $this->createMockAdapter('web', $originalResult);
        $this->gateway->register($adapter);

        $middleware = $this->createSecurityMiddleware(
            trustLevel: ContentTrustLevel::Untrusted,
            injectionScore: 0.1,
            injectionAction: InjectionAction::Log,
            sanitized: false,
        );
        $this->gateway->setSecurityMiddleware($middleware);

        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;

        $result = $this->gateway->call('web', $context, ['operation' => 'fetch'], $turnContext);

        // Caller gets ToolResult back (not SecurityToolResult)
        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertNotInstanceOf(SecurityToolResult::class, $result);
    }

    public function test_unknown_tool_with_turn_context_returns_failure(): void
    {
        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;

        $middleware = $this->createMock(ContentSecurityMiddleware::class);
        $this->gateway->setSecurityMiddleware($middleware);

        $result = $this->gateway->call('nonexistent', $context, [], $turnContext);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown tool', $result->error);
    }

    public function test_policy_denied_tool_with_turn_context_returns_failure(): void
    {
        $policyEngine = $this->createMock(PolicyEngine::class);
        $policyEngine->method('isToolAllowedByPolicy')->willReturn(false);

        $gateway = new ToolGateway(
            policyEngine: $policyEngine,
            approvalGate: $this->approvalGate,
            auditLogger: $this->auditLogger,
        );

        $adapter = $this->createMockAdapter('fs', ToolResult::success('data', 10));
        $gateway->register($adapter);

        $middleware = $this->createMock(ContentSecurityMiddleware::class);
        $gateway->setSecurityMiddleware($middleware);

        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;

        $result = $gateway->call('fs', $context, ['operation' => 'read'], $turnContext);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('denied by runtime policy', $result->error);
    }

    public function test_sanitized_content_metadata_persisted(): void
    {
        $adapter = $this->createMockAdapter('web', ToolResult::success('<script>alert(1)</script>Safe content', 50));
        $this->gateway->register($adapter);

        $middleware = $this->createSecurityMiddleware(
            trustLevel: ContentTrustLevel::Untrusted,
            injectionScore: 0.3,
            injectionAction: InjectionAction::Strip,
            sanitized: true,
        );
        $this->gateway->setSecurityMiddleware($middleware);

        $context = $this->createContext(RuntimeMode::Standard);
        $turnContext = new TurnSecurityContext;

        $this->gateway->call('web', $context, ['operation' => 'fetch'], $turnContext);

        $toolCall = RuntimeToolCall::latest('id')->first();
        $this->assertTrue($toolCall->content_sanitized);
        $this->assertSame('strip', $toolCall->injection_action);
    }

    private function createMockAdapter(string $name, ToolResult $result): ToolAdapterInterface
    {
        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn($name);
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('schema')->willReturn([
            'description' => "Test {$name} adapter",
            'operations' => [],
            'parameters' => [],
        ]);
        $adapter->method('execute')->willReturn($result);

        return $adapter;
    }

    private function createContext(RuntimeMode $mode): RuntimeContext
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);

        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: $mode,
            policy: [],
        );
    }

    private function createSecurityMiddleware(
        ContentTrustLevel $trustLevel,
        float $injectionScore,
        InjectionAction $injectionAction,
        bool $sanitized,
        bool $firewallAllows = true,
    ): ContentSecurityMiddleware {
        $classifier = $this->createMock(ContentTrustClassifier::class);
        $classifier->method('classifyToolResult')->willReturn($trustLevel);

        $detector = $this->createMock(InjectionDetectionEngine::class);
        $detector->method('scan')->willReturn(new InjectionDetectionResult(
            score: $injectionScore,
            matchedPatterns: [],
            recommendedAction: $injectionAction,
            scanDurationMs: 1,
        ));

        $sanitizer = $this->createMock(ContentSanitizer::class);
        $sanitizer->method('sanitize')->willReturn(new SanitizationResult(
            content: 'sanitized content',
            sanitized: $sanitized,
            truncated: false,
            originalTokenCount: 10,
        ));

        $firewall = $this->createMock(InstructionFirewall::class);
        $firewall->method('evaluate')->willReturn(new FirewallDecision(
            allowed: $firewallAllows,
            requiresEscalation: false,
            reason: null,
            taintSource: null,
        ));

        $logger = $this->createMock(SecurityEventLogger::class);

        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')->willReturnCallback(fn (string $key) => match ($key) {
            'injection_detection_enabled' => true,
            default => null,
        });

        return new ContentSecurityMiddleware(
            classifier: $classifier,
            detector: $detector,
            sanitizer: $sanitizer,
            firewall: $firewall,
            logger: $logger,
            config: $config,
        );
    }
}
