<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class RuntimeConfigTest extends TestCase
{
    public function test_runtime_config_has_required_keys(): void
    {
        $config = config('runtime');

        $this->assertArrayHasKey('default_mode', $config);
        $this->assertArrayHasKey('approval_model', $config);
        $this->assertArrayHasKey('modes', $config);
        $this->assertArrayHasKey('tool_deny', $config);
        $this->assertArrayHasKey('tool_allow', $config);
        $this->assertArrayHasKey('concurrent_session_limit_default', $config);
        $this->assertArrayHasKey('browser', $config);
        $this->assertArrayHasKey('mcp', $config);
    }

    public function test_tool_deny_and_tool_allow_are_arrays(): void
    {
        $this->assertIsArray(config('runtime.tool_deny'));
        $this->assertIsArray(config('runtime.tool_allow'));
    }

    public function test_strict_approval_model_modes(): void
    {
        $modes = config('runtime.modes');

        // Safe mode: no approvals needed, read-only capabilities
        $this->assertEquals([], $modes['safe']['approvals_required']);
        $this->assertContains('read', $modes['safe']['capabilities']);
        $this->assertNotContains('write', $modes['safe']['capabilities']);

        // Standard mode: approvals for mutations
        $this->assertContains('mutations', $modes['standard']['approvals_required']);

        // Full mode: approvals for mutations + external
        $this->assertContains('mutations', $modes['full']['approvals_required']);
        $this->assertContains('external', $modes['full']['approvals_required']);
    }

    public function test_default_concurrent_session_limit_is_three(): void
    {
        $this->assertEquals(3, config('runtime.concurrent_session_limit_default'));
    }

    public function test_audit_archive_defaults_to_thirty_days(): void
    {
        $this->assertEquals(30, config('runtime.audit_archive_after_days'));
    }

    public function test_default_mode_is_safe(): void
    {
        $this->assertEquals('safe', config('runtime.default_mode'));
    }

    public function test_approval_model_is_strict(): void
    {
        $this->assertEquals('strict', config('runtime.approval_model'));
    }

    public function test_session_timeout_is_null_by_default(): void
    {
        $this->assertNull(config('runtime.session_timeout'));
    }

    public function test_browser_config_has_required_keys(): void
    {
        $browser = config('runtime.browser');

        $this->assertArrayHasKey('sidecar_binary', $browser);
        $this->assertArrayHasKey('default_persistence', $browser);
        $this->assertArrayHasKey('denied_commands', $browser);
        $this->assertArrayHasKey('timeout', $browser);
        $this->assertEquals('ephemeral', $browser['default_persistence']);
    }

    public function test_browser_denied_commands(): void
    {
        $denied = config('runtime.browser.denied_commands');

        $this->assertIsArray($denied);
        $this->assertContains('install', $denied);
        $this->assertContains('connect', $denied);
    }

    public function test_mcp_config_has_required_keys(): void
    {
        $mcp = config('runtime.mcp');

        $this->assertArrayHasKey('enabled', $mcp);
        $this->assertArrayHasKey('transport', $mcp);
        $this->assertFalse($mcp['enabled']);
        $this->assertEquals('stdio', $mcp['transport']);
    }

    public function test_policy_snapshot_triggers(): void
    {
        $triggers = config('runtime.policy_snapshot_triggers');

        $this->assertContains('session_start', $triggers);
        $this->assertContains('mode_change', $triggers);
    }

    public function test_approval_ux_config(): void
    {
        $ux = config('runtime.approval_ux');

        $this->assertArrayHasKey('interactive_providers', $ux);
        $this->assertArrayHasKey('fallback_mode', $ux);
        $this->assertContains('slack', $ux['interactive_providers']);
        $this->assertContains('discord', $ux['interactive_providers']);
        $this->assertEquals('hybrid', $ux['fallback_mode']);
    }

    public function test_safe_mode_capabilities(): void
    {
        $capabilities = config('runtime.modes.safe.capabilities');

        $this->assertContains('read', $capabilities);
        $this->assertContains('query', $capabilities);
        $this->assertContains('browser_snapshot', $capabilities);
    }

    public function test_standard_mode_capabilities(): void
    {
        $capabilities = config('runtime.modes.standard.capabilities');

        $this->assertContains('read', $capabilities);
        $this->assertContains('write', $capabilities);
        $this->assertContains('query', $capabilities);
        $this->assertContains('browser_action', $capabilities);
        $this->assertContains('runtime_command', $capabilities);
    }

    public function test_full_mode_has_wildcard_capabilities(): void
    {
        $capabilities = config('runtime.modes.full.capabilities');

        $this->assertContains('*', $capabilities);
    }

    public function test_full_mode_requires_elevated_approval(): void
    {
        $approvals = config('runtime.modes.full.approvals_required');

        $this->assertContains('elevated', $approvals);
    }

    public function test_cli_tool_approval_timeout_defaults_to_300(): void
    {
        $this->assertEquals(300, config('runtime.cli.tool_approval_timeout'));
    }

    public function test_cli_tool_approval_poll_interval_defaults_to_2(): void
    {
        $this->assertEquals(2, config('runtime.cli.tool_approval_poll_interval'));
    }
}
