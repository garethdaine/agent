<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\OrgAgentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeStateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_complete_office_state_structure(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->create(['user_id' => $user->id, 'name' => 'Atlas']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'agents',
                    'system',
                    'delegation',
                    'messenger',
                    'memory',
                    'jobs',
                    'tools',
                    'escalations',
                    'recent_activity',
                ],
            ]);
    }

    public function test_agents_section_includes_enriched_fields(): void
    {
        $user = User::factory()->create();
        $agent = OrgAgentProfile::factory()->create([
            'user_id' => $user->id,
            'name' => 'Sentinel',
            'role_slug' => 'security',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk();
        $agentData = $response->json('data.agents.0');
        $this->assertEquals('Sentinel', $agentData['name']);
        $this->assertArrayHasKey('status', $agentData);
        $this->assertArrayHasKey('current_activity', $agentData);
        $this->assertArrayHasKey('current_run', $agentData);
        $this->assertArrayHasKey('tools_active', $agentData);
        $this->assertArrayHasKey('needs_attention', $agentData);
    }

    public function test_jobs_section_returns_summary(): void
    {
        $user = User::factory()->create();
        AgentJob::factory()->for($user)->count(3)->create(['is_enabled' => true]);
        AgentJob::factory()->for($user)->create(['is_enabled' => false]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk();
        $jobs = $response->json('data.jobs');
        $this->assertEquals(4, $jobs['total']);
        $this->assertEquals(3, $jobs['enabled']);
        $this->assertEquals(1, $jobs['disabled']);
    }

    public function test_tools_section_returns_recent_tool_calls(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk();
        $tools = $response->json('data.tools');
        $this->assertArrayHasKey('recent_calls', $tools);
        $this->assertArrayHasKey('pending_approvals', $tools);
        $this->assertArrayHasKey('unique_tools_24h', $tools);
    }

    public function test_escalations_section_returns_open_counts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk();
        $esc = $response->json('data.escalations');
        $this->assertArrayHasKey('open_incidents', $esc);
        $this->assertArrayHasKey('pending_org_escalations', $esc);
        $this->assertArrayHasKey('recent_items', $esc);
    }

    public function test_recent_activity_returns_run_events(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->for($user)->create();
        AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => 'succeeded',
            'finished_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk();
        $activity = $response->json('data.recent_activity');
        $this->assertIsArray($activity);
        $this->assertNotEmpty($activity);
        $this->assertArrayHasKey('type', $activity[0]);
        $this->assertArrayHasKey('label', $activity[0]);
        $this->assertArrayHasKey('timestamp', $activity[0]);
    }

    public function test_system_section_includes_security_detail(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/agent/api/v1/office/state');

        $response->assertOk();
        $system = $response->json('data.system');
        $this->assertArrayHasKey('runtime_mode', $system);
        $this->assertArrayHasKey('scheduler_healthy', $system);
        $this->assertArrayHasKey('active_runs', $system);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/agent/api/v1/office/state')
            ->assertUnauthorized();
    }
}
