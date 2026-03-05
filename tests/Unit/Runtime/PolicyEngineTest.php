<?php

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\PolicySnapshotReason;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimePolicySnapshot;
use App\Models\Runtime\RuntimeSession;
use App\Services\Runtime\PolicyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    private PolicyEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PolicyEngine;
    }

    public function test_safe_mode_allows_read_capability(): void
    {
        $this->assertTrue($this->engine->isCapabilityAllowed(RuntimeMode::Safe, 'read'));
        $this->assertTrue($this->engine->isCapabilityAllowed(RuntimeMode::Safe, 'query'));
    }

    public function test_safe_mode_denies_write_capability(): void
    {
        $this->assertFalse($this->engine->isCapabilityAllowed(RuntimeMode::Safe, 'write'));
    }

    public function test_standard_mode_allows_write_capability(): void
    {
        $this->assertTrue($this->engine->isCapabilityAllowed(RuntimeMode::Standard, 'write'));
    }

    public function test_standard_mode_requires_approval_for_mutations(): void
    {
        $this->assertTrue($this->engine->requiresApproval(RuntimeMode::Standard, 'mutations'));
    }

    public function test_full_mode_requires_approval_for_external(): void
    {
        $this->assertTrue($this->engine->requiresApproval(RuntimeMode::Full, 'external'));
        $this->assertTrue($this->engine->requiresApproval(RuntimeMode::Full, 'mutations'));
    }

    public function test_safe_mode_requires_no_approvals(): void
    {
        $this->assertFalse($this->engine->requiresApproval(RuntimeMode::Safe, 'mutations'));
    }

    public function test_capture_snapshot_creates_policy_snapshot_record(): void
    {
        $session = RuntimeSession::factory()->create();

        $snapshot = $this->engine->captureSnapshot($session, PolicySnapshotReason::SessionStart);

        $this->assertInstanceOf(RuntimePolicySnapshot::class, $snapshot);
        $this->assertEquals($session->id, $snapshot->runtime_session_id);
        $this->assertEquals(PolicySnapshotReason::SessionStart, $snapshot->snapshot_reason);
        $this->assertArrayHasKey('mode', $snapshot->policy_json);
    }

    public function test_get_effective_policy_returns_mode_config(): void
    {
        $policy = $this->engine->getEffectivePolicy(RuntimeMode::Standard);

        $this->assertArrayHasKey('capabilities', $policy);
        $this->assertArrayHasKey('approvals_required', $policy);
        $this->assertContains('mutations', $policy['approvals_required']);
    }

    public function test_full_mode_allows_all_capabilities_via_wildcard(): void
    {
        $this->assertTrue($this->engine->isCapabilityAllowed(RuntimeMode::Full, 'read'));
        $this->assertTrue($this->engine->isCapabilityAllowed(RuntimeMode::Full, 'write'));
        $this->assertTrue($this->engine->isCapabilityAllowed(RuntimeMode::Full, 'any_arbitrary_capability'));
    }

    public function test_should_trigger_snapshot_returns_true_for_configured_triggers(): void
    {
        $this->assertTrue($this->engine->shouldTriggerSnapshot('session_start'));
        $this->assertTrue($this->engine->shouldTriggerSnapshot('mode_change'));
    }

    public function test_should_trigger_snapshot_returns_false_for_unknown_events(): void
    {
        $this->assertFalse($this->engine->shouldTriggerSnapshot('unknown_event'));
        $this->assertFalse($this->engine->shouldTriggerSnapshot('random_event'));
    }

    public function test_standard_mode_does_not_require_approval_for_external(): void
    {
        // Standard mode only requires approval for mutations, not external
        $this->assertFalse($this->engine->requiresApproval(RuntimeMode::Standard, 'external'));
    }

    public function test_full_mode_requires_approval_for_elevated(): void
    {
        $this->assertTrue($this->engine->requiresApproval(RuntimeMode::Full, 'elevated'));
    }

    public function test_capture_snapshot_with_mode_change_reason(): void
    {
        $session = RuntimeSession::factory()->standardMode()->create();

        $snapshot = $this->engine->captureSnapshot($session, PolicySnapshotReason::ModeChange);

        $this->assertEquals(PolicySnapshotReason::ModeChange, $snapshot->snapshot_reason);
        $this->assertEquals('standard', $snapshot->policy_json['mode']);
        $this->assertContains('mutations', $snapshot->policy_json['approvals_required']);
    }

    public function test_get_effective_policy_includes_approval_model(): void
    {
        $policy = $this->engine->getEffectivePolicy(RuntimeMode::Safe);

        $this->assertArrayHasKey('approval_model', $policy);
        $this->assertEquals('strict', $policy['approval_model']);
    }
}
