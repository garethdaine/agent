<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Security\FirewallDecision;
use App\Enums\Runtime\RuntimeMode;
use App\Services\Security\InstructionFirewall;
use App\Services\Security\SecurityConfigProvider;
use App\Services\Security\TurnSecurityContext;
use PHPUnit\Framework\TestCase;

class InstructionFirewallTest extends TestCase
{
    private InstructionFirewall $firewall;

    private SecurityConfigProvider $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(SecurityConfigProvider::class);
        $this->firewall = new InstructionFirewall($this->config, allowedHosts: []);
    }

    public function test_untainted_turn_with_sensitive_tool_is_allowed(): void
    {
        $context = new TurnSecurityContext;

        $decision = $this->firewall->evaluate('fs.write', [], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->allowed);
        $this->assertFalse($decision->requiresEscalation);
    }

    public function test_tainted_turn_with_web_fetch_to_non_allowlisted_host_requires_escalation(): void
    {
        $this->config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                default => null,
            });

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $this->firewall->evaluate(
            'web.fetch',
            ['url' => 'https://evil.com/exfil'],
            $context,
            RuntimeMode::Standard,
        );

        $this->assertTrue($decision->requiresEscalation);
        $this->assertFalse($decision->allowed);
    }

    public function test_tainted_turn_with_web_fetch_to_allowlisted_host_is_allowed(): void
    {
        $this->config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                default => null,
            });

        $firewall = new InstructionFirewall($this->config, allowedHosts: ['allowed.com']);

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $firewall->evaluate(
            'web.fetch',
            ['url' => 'https://allowed.com/api/data'],
            $context,
            RuntimeMode::Standard,
        );

        $this->assertTrue($decision->allowed);
        $this->assertFalse($decision->requiresEscalation);
    }

    public function test_tainted_turn_with_fs_write_requires_escalation(): void
    {
        $this->config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                default => null,
            });

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $this->firewall->evaluate('fs.write', ['/tmp/test.txt'], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->requiresEscalation);
        $this->assertFalse($decision->allowed);
    }

    public function test_tainted_turn_with_runtime_exec_requires_escalation(): void
    {
        $this->config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                default => null,
            });

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $this->firewall->evaluate('runtime.exec', ['ls'], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->requiresEscalation);
        $this->assertFalse($decision->allowed);
    }

    public function test_tainted_turn_with_mcp_call_requires_escalation(): void
    {
        $this->config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                default => null,
            });

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $this->firewall->evaluate('mcp.call', [], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->requiresEscalation);
        $this->assertFalse($decision->allowed);
    }

    public function test_tainted_turn_with_non_sensitive_tool_and_default_deny_disabled_is_allowed(): void
    {
        $this->config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => false,
                default => null,
            });

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $this->firewall->evaluate('fs.read', ['/tmp/test.txt'], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->allowed);
        $this->assertFalse($decision->requiresEscalation);
    }

    public function test_tainted_turn_with_fs_read_and_default_deny_enabled_requires_escalation(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => true,
                default => null,
            });

        $firewall = new InstructionFirewall($config, allowedHosts: []);

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $firewall->evaluate('fs.read', [], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->requiresEscalation);
        $this->assertFalse($decision->allowed);
    }

    public function test_tainted_turn_with_non_sensitive_tool_and_default_deny_enabled_requires_escalation(): void
    {
        $config = $this->createMock(SecurityConfigProvider::class);
        $config->method('get')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'default_deny_external' => true,
                default => null,
            });

        $firewall = new InstructionFirewall($config, allowedHosts: []);

        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $decision = $firewall->evaluate('web.search', [], $context, RuntimeMode::Standard);

        $this->assertTrue($decision->requiresEscalation);
        $this->assertFalse($decision->allowed);
    }

    public function test_is_sensitive_tool_web_fetch_to_non_allowlisted_host(): void
    {
        $firewall = new InstructionFirewall($this->config, allowedHosts: ['safe.com']);

        $this->assertTrue(
            $firewall->isSensitiveTool('web.fetch', ['url' => 'https://evil.com'])
        );
    }

    public function test_is_sensitive_tool_web_fetch_to_allowlisted_host(): void
    {
        $firewall = new InstructionFirewall($this->config, allowedHosts: ['allowed.com']);

        $this->assertFalse(
            $firewall->isSensitiveTool('web.fetch', ['url' => 'https://allowed.com'])
        );
    }

    public function test_firewall_decision_dto_fields_accessible(): void
    {
        $decision = new FirewallDecision(
            allowed: false,
            requiresEscalation: true,
            reason: 'Tainted turn detected',
            taintSource: 'web.fetch',
        );

        $this->assertFalse($decision->allowed);
        $this->assertTrue($decision->requiresEscalation);
        $this->assertSame('Tainted turn detected', $decision->reason);
        $this->assertSame('web.fetch', $decision->taintSource);
    }
}
