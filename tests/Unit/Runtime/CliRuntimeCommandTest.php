<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Enums\Messenger\ApprovalMode;
use App\Services\Runtime\CliRuntimeExecutor;
use PHPUnit\Framework\TestCase;

class CliRuntimeCommandTest extends TestCase
{
    private function buildCommand(ApprovalMode $mode, ?string $sessionId = null, ?string $systemPrompt = null): array
    {
        $executor = new CliRuntimeExecutor(
            $this->createMock(\App\Services\Credentials\CredentialsManager::class)
        );

        $method = new \ReflectionMethod($executor, 'buildRuntimeCommand');

        return $method->invoke($executor, '/usr/local/bin/claude', 'claude', $sessionId, $systemPrompt, $mode);
    }

    public function test_autonomous_mode_includes_dangerously_skip_permissions(): void
    {
        $command = $this->buildCommand(ApprovalMode::Autonomous);

        $this->assertContains('--dangerously-skip-permissions', $command);
        $this->assertContains('-p', $command);
    }

    public function test_supervised_mode_uses_allowed_tools_with_write(): void
    {
        $command = $this->buildCommand(ApprovalMode::Supervised);

        $this->assertNotContains('--dangerously-skip-permissions', $command);
        $this->assertContains('--allowedTools', $command);
        $allowedIdx = array_search('--allowedTools', $command);
        $this->assertStringContainsString('Write', $command[$allowedIdx + 1]);
        $this->assertStringContainsString('Edit', $command[$allowedIdx + 1]);
        $this->assertContains('-p', $command);
    }

    public function test_restricted_mode_uses_read_only_allowed_tools(): void
    {
        $command = $this->buildCommand(ApprovalMode::Restricted);

        $this->assertNotContains('--dangerously-skip-permissions', $command);
        $this->assertContains('--allowedTools', $command);
        $allowedIdx = array_search('--allowedTools', $command);
        $this->assertStringNotContainsString('Write', $command[$allowedIdx + 1]);
        $this->assertStringNotContainsString('Edit', $command[$allowedIdx + 1]);
        $this->assertContains('-p', $command);
    }

    public function test_command_includes_verbose_and_stream_json(): void
    {
        $command = $this->buildCommand(ApprovalMode::Autonomous);

        $this->assertContains('--verbose', $command);
        $this->assertContains('--output-format', $command);
        $this->assertContains('stream-json', $command);
        $this->assertContains('--strict-mcp-config', $command);
    }

    public function test_command_includes_resume_when_session_id_provided(): void
    {
        $command = $this->buildCommand(ApprovalMode::Autonomous, 'session-abc-123');

        $this->assertContains('--resume', $command);
        $this->assertContains('session-abc-123', $command);
    }

    public function test_command_includes_system_prompt_when_provided(): void
    {
        $command = $this->buildCommand(ApprovalMode::Autonomous, null, 'You are AxiomSpark.');

        $this->assertContains('--system-prompt', $command);
        $this->assertContains('You are AxiomSpark.', $command);
    }

    public function test_command_excludes_resume_when_session_id_null(): void
    {
        $command = $this->buildCommand(ApprovalMode::Autonomous);

        $this->assertNotContains('--resume', $command);
    }
}
