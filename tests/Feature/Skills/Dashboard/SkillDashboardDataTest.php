<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Dashboard;

use App\Models\AgentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkillDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;

        config()->set('agent.skills.enabled', true);
    }

    public function test_skill_usage_endpoint_returns_top_skills(): void
    {
        // Create skills and telemetry events
        $skillA = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'popular-skill',
            'slug' => 'popular-skill',
            'invocation_count' => 10,
        ]);
        $skillB = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'less-popular',
            'slug' => 'less-popular',
            'invocation_count' => 3,
        ]);

        // Insert telemetry events
        $this->insertSkillTelemetryEvents($skillA, 'completed', 10);
        $this->insertSkillTelemetryEvents($skillB, 'completed', 3);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/dashboard/usage');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'skill_id',
                    'skill_name',
                    'invocation_count',
                    'success_rate',
                ],
            ],
        ]);

        // popular-skill should be first
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('popular-skill', $data[0]['skill_name']);
    }

    public function test_skill_usage_includes_success_rate(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'mixed-results',
            'slug' => 'mixed-results',
            'invocation_count' => 10,
        ]);

        $this->insertSkillTelemetryEvents($skill, 'completed', 7);
        $this->insertSkillTelemetryEvents($skill, 'failed', 3);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/dashboard/usage');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $skillData = collect($data)->firstWhere('skill_name', 'mixed-results');
        $this->assertNotNull($skillData);
        $this->assertEqualsWithDelta(70.0, $skillData['success_rate'], 0.1);
    }

    public function test_skill_health_endpoint_returns_per_skill_status(): void
    {
        $activeSkill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'healthy-skill',
            'slug' => 'healthy-skill',
            'status' => AgentSkill::STATUS_ACTIVE,
            'validation_result' => ['overall_pass' => true, 'risk_score' => 0.1],
        ]);

        $driftedSkill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'drifted-skill',
            'slug' => 'drifted-skill',
            'status' => AgentSkill::STATUS_PENDING_REVIEW,
            'validation_result' => ['overall_pass' => true, 'risk_score' => 0.2],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/dashboard/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'risk_level',
                    'validation_pass',
                    'invocation_count',
                    'has_drift',
                ],
            ],
        ]);

        $data = $response->json('data');
        $drifted = collect($data)->firstWhere('name', 'drifted-skill');
        $this->assertTrue($drifted['has_drift']);

        $healthy = collect($data)->firstWhere('name', 'healthy-skill');
        $this->assertFalse($healthy['has_drift']);
    }

    public function test_reliability_score_includes_skill_attribution(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'failing-skill',
            'slug' => 'failing-skill',
        ]);

        $this->insertSkillTelemetryEvents($skill, 'failed', 5);
        $this->insertSkillTelemetryEvents($skill, 'completed', 5);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/dashboard/metrics?window=24h');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'metrics' => [
                    'skill_failure_rate',
                ],
            ],
        ]);

        $failureRate = $response->json('data.metrics.skill_failure_rate');
        $this->assertEqualsWithDelta(50.0, $failureRate, 0.1);
    }

    public function test_budget_utilization_includes_skill_spend(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'token-consumer',
            'slug' => 'token-consumer',
        ]);

        // Insert events with token_usage in payload
        $this->insertSkillTelemetryEvents($skill, 'completed', 3, tokenUsage: 500);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/dashboard/metrics?window=24h');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'metrics' => [
                    'skill_token_spend',
                ],
            ],
        ]);

        $spend = $response->json('data.metrics.skill_token_spend');
        $this->assertSame(1500, $spend);
    }

    private function insertSkillTelemetryEvents(
        AgentSkill $skill,
        string $outcome,
        int $count,
        int $durationMs = 100,
        int $tokenUsage = 50,
    ): void {
        for ($i = 0; $i < $count; $i++) {
            DB::table('telemetry_event_ledger')->insert([
                'schema_name' => 'agent.telemetry.event',
                'schema_version' => '1.0.0',
                'event_id' => Str::uuid()->toString(),
                'run_attempt_id' => Str::uuid()->toString(),
                'event_type' => 'skill.'.$outcome,
                'event_sequence' => 0,
                'event_at' => now(),
                'payload_json' => json_encode([
                    'skill_id' => (string) $skill->id,
                    'skill_name' => $skill->name,
                    'skill_version' => $skill->version,
                    'team_id' => $skill->team_id,
                    'duration_ms' => $durationMs,
                    'token_usage' => $tokenUsage,
                    'outcome' => $outcome,
                ]),
                'schema_hash' => 'test',
                'normalizer_version' => '1.0.0',
                'registry_revision' => 1,
                'terminal' => false,
                'ingested_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
