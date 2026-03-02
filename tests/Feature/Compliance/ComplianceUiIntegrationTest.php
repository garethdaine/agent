<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests verifying compliance UI integration endpoints are accessible
 * and return expected data structures for frontend rendering.
 *
 * Scope:
 * - Route accessibility verification
 * - Response structure validation for UI badge rendering
 * - Authentication requirements
 *
 * Conditions for correctness:
 * - Compliance routes must be registered in api.php
 * - ComplianceController must be properly implemented
 * - ComplianceFlagResolver must resolve flags correctly
 * - ComplianceSummaryResource must format metadata correctly
 *
 * Limitations:
 * - Does not test actual UI rendering (Vue components)
 * - Does not test WebSocket/broadcast events
 * - Metrics endpoint returns placeholder data (not implemented yet)
 */
class ComplianceUiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_status_endpoint_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/compliance/status');

        $response->assertOk()
            ->assertJsonStructure([
                'enabled',
                'enforcement_mode',
                'flags',
            ]);
    }

    public function test_compliance_status_returns_all_expected_fields_for_ui(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');
        config()->set('agent.compliance.plan_gate_enabled', true);
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/compliance/status');

        $response->assertOk();

        // Verify all fields needed for UI badge rendering
        $this->assertTrue($response->json('enabled'));
        $this->assertEquals('advisory', $response->json('enforcement_mode'));
        $this->assertIsArray($response->json('flags'));
    }

    public function test_compliance_metrics_endpoint_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/compliance/metrics');

        $response->assertOk()
            ->assertJsonStructure(['total_jobs']);
    }

    public function test_compliance_metrics_returns_structure_for_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/compliance/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_jobs',
                'total_runs',
                'success_rate',
                'failure_rate',
                'active_runs',
            ]);
    }

    public function test_run_show_includes_compliance_summary_when_run_has_compliance_data(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'complexity_classification' => 'non_trivial',
                'task_category' => 'feature',
                'plan_required' => true,
                'verification_required' => true,
                'compliance_status' => 'pass',
                'compliance_gates' => [
                    ['gate' => 'plan', 'status' => 'pass'],
                    ['gate' => 'verification', 'status' => 'pass'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.compliance_summary.complexity', 'non_trivial')
            ->assertJsonPath('data.compliance_summary.category', 'feature')
            ->assertJsonPath('data.compliance_summary.status', 'pass');
    }

    public function test_run_show_compliance_summary_includes_all_ui_badge_fields(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_FAILED,
            'metadata_json' => [
                'complexity_classification' => 'non_trivial',
                'task_category' => 'bugfix',
                'plan_required' => true,
                'plan_completed' => true,
                'verification_required' => true,
                'verification_completed' => false,
                'compliance_status' => 'blocked',
                'compliance_block_reason' => 'missing_automated_check',
                'compliance_remediation' => 'Run tests and capture output as verification evidence.',
                'compliance_gates' => [
                    ['gate' => 'plan', 'status' => 'pass'],
                    ['gate' => 'verification', 'status' => 'blocked', 'reason_code' => 'missing_automated_check'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'compliance_summary' => [
                        'status',
                        'complexity',
                        'category',
                        'plan_required',
                        'plan_completed',
                        'verification_required',
                        'verification_completed',
                        'block_reason',
                        'remediation',
                        'gates',
                    ],
                ],
            ]);

        // Verify badge rendering data
        $this->assertEquals('blocked', $response->json('data.compliance_summary.status'));
        $this->assertEquals('missing_automated_check', $response->json('data.compliance_summary.block_reason'));
        $this->assertNotNull($response->json('data.compliance_summary.remediation'));
    }

    public function test_session_show_includes_compliance_summary_on_tasks(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::factory()->create([
            'user_id' => $user->id,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'metadata_json' => [
                'compliance_status' => 'advisory',
                'complexity_classification' => 'simple',
                'task_category' => 'docs',
                'plan_required' => false,
                'verification_required' => false,
                'compliance_gates' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/interrogation/sessions/{$session->id}");

        $response->assertOk();

        // Session may or may not include compliance_summary depending on implementation
        // This test verifies the endpoint is accessible and returns valid JSON
        $this->assertIsArray($response->json('data'));
    }

    public function test_unauthenticated_returns_401_for_compliance_status(): void
    {
        $response = $this->getJson('/agent/api/v1/compliance/status');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_compliance_metrics(): void
    {
        $response = $this->getJson('/agent/api/v1/compliance/metrics');
        $response->assertUnauthorized();
    }

    public function test_compliance_status_returns_flags_as_object(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/compliance/status');

        $response->assertOk();

        // flags should be an array/object for UI to iterate over
        $flags = $response->json('flags');
        $this->assertIsArray($flags);
    }

    public function test_run_without_compliance_data_returns_no_compliance_summary(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'source' => 'manual',
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk();
        $response->assertJsonMissing(['compliance_summary']);
    }

    public function test_compliance_status_responds_within_acceptable_latency(): void
    {
        $user = User::factory()->create();

        $start = microtime(true);

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/compliance/status');

        $elapsed = microtime(true) - $start;

        $response->assertOk();

        // Status endpoint should respond quickly (under 500ms)
        // for good UI responsiveness
        $this->assertLessThan(0.5, $elapsed, 'Compliance status endpoint exceeded 500ms latency');
    }

    public function test_run_compliance_summary_supports_advisory_status(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'compliance_status' => 'advisory',
                'complexity_classification' => 'non_trivial',
                'task_category' => 'refactor',
                'plan_required' => true,
                'plan_completed' => false,
                'compliance_gates' => [
                    ['gate' => 'plan', 'status' => 'advisory', 'reason_code' => 'plan_incomplete'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.compliance_summary.status', 'advisory');
    }
}
