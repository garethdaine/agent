<?php

namespace Tests\Unit\Enums\Runtime;

use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\PolicySnapshotReason;
use App\Enums\Runtime\RuntimeApprovalState;
use App\Enums\Runtime\RuntimeArtifactType;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Enums\Runtime\RuntimeTurnStatus;
use PHPUnit\Framework\TestCase;

class RuntimeEnumsTest extends TestCase
{
    public function test_runtime_session_status_has_required_cases(): void
    {
        $cases = RuntimeSessionStatus::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('pending', $names);
        $this->assertContains('active', $names);
        $this->assertContains('completed', $names);
        $this->assertContains('failed', $names);
        $this->assertContains('stopped', $names);
    }

    public function test_runtime_mode_has_safe_standard_full(): void
    {
        $this->assertEquals('safe', RuntimeMode::Safe->value);
        $this->assertEquals('standard', RuntimeMode::Standard->value);
        $this->assertEquals('full', RuntimeMode::Full->value);
    }

    public function test_runtime_tool_call_status_includes_pending_approval(): void
    {
        $cases = RuntimeToolCallStatus::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('pending_approval', $names);
        $this->assertContains('approved', $names);
        $this->assertContains('denied', $names);
    }

    public function test_runtime_turn_status_has_required_cases(): void
    {
        $cases = RuntimeTurnStatus::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('pending', $names);
        $this->assertContains('running', $names);
        $this->assertContains('completed', $names);
        $this->assertContains('failed', $names);
    }

    public function test_runtime_tool_call_status_has_all_lifecycle_states(): void
    {
        $cases = RuntimeToolCallStatus::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('running', $names);
        $this->assertContains('completed', $names);
        $this->assertContains('failed', $names);
    }

    public function test_runtime_approval_state_has_required_cases(): void
    {
        $cases = RuntimeApprovalState::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('pending', $names);
        $this->assertContains('approved', $names);
        $this->assertContains('denied', $names);
        $this->assertContains('expired', $names);
    }

    public function test_runtime_artifact_type_has_required_cases(): void
    {
        $cases = RuntimeArtifactType::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('file', $names);
        $this->assertContains('screenshot', $names);
        $this->assertContains('log', $names);
        $this->assertContains('report', $names);
    }

    public function test_policy_snapshot_reason_has_required_cases(): void
    {
        $cases = PolicySnapshotReason::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('session_start', $names);
        $this->assertContains('mode_change', $names);
    }

    public function test_browser_persistence_mode_has_required_cases(): void
    {
        $cases = BrowserPersistenceMode::cases();
        $names = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('ephemeral', $names);
        $this->assertContains('persistent', $names);
    }
}
