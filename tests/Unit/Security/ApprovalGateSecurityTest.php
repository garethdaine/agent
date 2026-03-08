<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Services\Runtime\ApprovalGate;
use App\Services\Runtime\PolicyEngine;
use App\Services\Security\SecurityConfigProvider;
use App\Services\Security\TurnSecurityContext;
use App\Support\Agent\AuditLogger;
use PHPUnit\Framework\TestCase;

class ApprovalGateSecurityTest extends TestCase
{
    private PolicyEngine $policyEngine;

    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policyEngine = $this->createMock(PolicyEngine::class);
        $this->policyEngine->method('requiresApproval')->willReturn(false);

        $this->auditLogger = $this->createMock(AuditLogger::class);
    }

    public function test_null_turn_context_preserves_existing_behavior_safe_mode(): void
    {
        $gate = $this->createGate();

        $result = $gate->requiresApproval(RuntimeMode::Safe, 'fs.write');
        $this->assertFalse($result);
    }

    public function test_null_turn_context_preserves_existing_behavior_standard_mode(): void
    {
        $gate = $this->createGate();

        // Without turnContext, existing behavior is maintained
        $result = $gate->requiresApproval(RuntimeMode::Standard, 'fs.read');
        $this->assertFalse($result);
    }

    public function test_tainted_turn_with_default_deny_enabled_mutation_tool_requires_approval(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => true,
                'messenger_high_impact_confirmation' => false,
                default => null,
            });

        $gate = $this->createGate($config);

        $turnContext = new TurnSecurityContext;
        $turnContext->markTainted('web.fetch');

        $result = $gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.write',
            [],
            null,
            $turnContext,
        );

        $this->assertTrue($result);
    }

    public function test_tainted_turn_with_default_deny_enabled_read_tool_requires_approval(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => true,
                'messenger_high_impact_confirmation' => false,
                default => null,
            });

        $gate = $this->createGate($config);

        $turnContext = new TurnSecurityContext;
        $turnContext->markTainted('web.fetch');

        // External tool (web.search) should also be denied when default_deny is on
        $result = $gate->requiresApproval(
            RuntimeMode::Standard,
            'web.search',
            [],
            null,
            $turnContext,
        );

        $this->assertTrue($result);
    }

    public function test_tainted_turn_with_default_deny_disabled_no_escalation(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                'messenger_high_impact_confirmation' => false,
                default => null,
            });

        $gate = $this->createGate($config);

        $turnContext = new TurnSecurityContext;
        $turnContext->markTainted('web.fetch');

        // Non-sensitive, non-mutation tool — existing behavior
        $result = $gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.read',
            [],
            null,
            $turnContext,
        );

        $this->assertFalse($result);
    }

    public function test_messenger_high_impact_confirmation_standard_mode_mutation_requires_approval(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                'messenger_high_impact_confirmation' => true,
                default => null,
            });

        $gate = $this->createGate($config);

        $result = $gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.write',
        );

        $this->assertTrue($result);
    }

    public function test_messenger_high_impact_confirmation_safe_mode_existing_behavior(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                'messenger_high_impact_confirmation' => true,
                default => null,
            });

        $gate = $this->createGate($config);

        // Safe mode blocks writes, doesn't use approval
        $result = $gate->requiresApproval(RuntimeMode::Safe, 'fs.write');

        $this->assertFalse($result);
    }

    public function test_auto_approved_tool_tainted_default_deny_still_requires_approval(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => true,
                'messenger_high_impact_confirmation' => false,
                default => null,
            });

        $gate = $this->createGate($config);

        $session = $this->createMock(RuntimeSession::class);
        $session->method('isToolAutoApproved')->willReturn(true);

        $turnContext = new TurnSecurityContext;
        $turnContext->markTainted('web.fetch');

        // Even though tool is auto-approved, security overrides when tainted
        $result = $gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.write',
            [],
            $session,
            $turnContext,
        );

        $this->assertTrue($result);
    }

    public function test_non_mutation_non_external_tool_not_escalated_when_tainted_default_deny(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => true,
                'messenger_high_impact_confirmation' => false,
                default => null,
            });

        $gate = $this->createGate($config);

        $turnContext = new TurnSecurityContext;
        $turnContext->markTainted('web.fetch');

        // fs.read is not in MUTATION_TOOLS or EXTERNAL_TOOLS
        $result = $gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.read',
            [],
            null,
            $turnContext,
        );

        // default_deny only applies to mutation and external tools
        $this->assertFalse($result);
    }

    private function createGate(?SecurityConfigProvider $config = null): ApprovalGate
    {
        return new ApprovalGate(
            policyEngine: $this->policyEngine,
            auditLogger: $this->auditLogger,
            securityConfig: $config,
        );
    }
}
