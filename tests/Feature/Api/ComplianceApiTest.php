<?php

namespace Tests\Feature\Api;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_status_requires_authentication(): void
    {
        $response = $this->getJson('/agent/api/v1/compliance/status');

        $response->assertStatus(401);
    }

    public function test_compliance_status_returns_current_flag_states(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/compliance/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'enabled',
            'enforcement_mode',
            'flags',
        ]);
        $response->assertJsonPath('enabled', true);
        $response->assertJsonPath('enforcement_mode', 'advisory');
    }

    public function test_compliance_status_returns_disabled_when_compliance_not_enabled(): void
    {
        config()->set('agent.compliance.enabled', false);
        config()->set('agent.compliance.enforcement_mode', 'advisory');

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/compliance/status');

        $response->assertOk();
        $response->assertJsonPath('enabled', false);
    }

    public function test_compliance_metrics_requires_authentication(): void
    {
        $response = $this->getJson('/agent/api/v1/compliance/metrics');

        $response->assertStatus(401);
    }

    public function test_compliance_metrics_returns_aggregated_metrics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/compliance/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'gate_evaluations',
            'pass_rate',
            'block_rate',
            'top_block_reasons',
        ]);
        $response->assertJsonPath('period', 'last_24h');
    }

    public function test_run_show_includes_compliance_summary_when_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
        ]);

        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'compliance_status' => 'pass',
                'complexity_classification' => 'non_trivial',
                'task_category' => 'feature',
                'plan_required' => true,
                'plan_completed' => true,
                'verification_required' => true,
                'verification_completed' => true,
                'compliance_gates' => [
                    ['gate' => 'plan', 'status' => 'pass'],
                    ['gate' => 'verification', 'status' => 'pass'],
                ],
            ],
        ]);

        $response = $this->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'compliance_summary' => [
                    'status',
                    'complexity',
                    'category',
                    'plan_required',
                    'plan_completed',
                    'verification_required',
                    'verification_completed',
                    'gates',
                ],
            ],
        ]);
        $response->assertJsonPath('data.compliance_summary.status', 'pass');
        $response->assertJsonPath('data.compliance_summary.complexity', 'non_trivial');
        $response->assertJsonPath('data.compliance_summary.category', 'feature');
    }

    public function test_run_show_omits_compliance_summary_when_no_compliance_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
        ]);

        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'source' => 'manual',
            ],
        ]);

        $response = $this->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk();
        $response->assertJsonMissing(['compliance_summary']);
    }

    public function test_interrogation_session_show_includes_compliance_summary_when_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::factory()->create([
            'user_id' => $user->id,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'metadata_json' => [
                'compliance_status' => 'blocked',
                'complexity_classification' => 'non_trivial',
                'task_category' => 'bugfix',
                'plan_required' => true,
                'plan_completed' => true,
                'verification_required' => true,
                'verification_completed' => false,
                'compliance_block_reason' => 'missing_automated_check',
                'compliance_remediation' => 'Run tests and capture output.',
                'compliance_gates' => [
                    ['gate' => 'plan', 'status' => 'pass'],
                    ['gate' => 'verification', 'status' => 'blocked', 'reason_code' => 'missing_automated_check'],
                ],
            ],
        ]);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}");

        $response->assertOk();
        $response->assertJsonStructure([
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
        $response->assertJsonPath('data.compliance_summary.status', 'blocked');
        $response->assertJsonPath('data.compliance_summary.block_reason', 'missing_automated_check');
        $response->assertJsonPath('data.compliance_summary.remediation', 'Run tests and capture output.');
    }

    public function test_backward_compatibility_old_clients_can_ignore_compliance_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
        ]);

        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'compliance_status' => 'pass',
                'complexity_classification' => 'simple',
            ],
        ]);

        $response = $this->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk();

        // Verify existing fields are still present
        $response->assertJsonStructure([
            'data' => [
                'id',
                'agent_job_id',
                'user_id',
                'status',
                'metadata_json',
            ],
        ]);

        // compliance_summary is an optional addition - clients can safely ignore it
        $this->assertArrayHasKey('compliance_summary', $response->json('data'));
    }
}
