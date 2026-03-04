<?php

declare(strict_types=1);

namespace Tests\Feature\AgentUi;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NavigationDiscoverabilityTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_exposes_top_level_operator_navigation_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('operatorNavigation.deployments', '/agent/deployments')
                ->where('operatorNavigation.systemOverview', '/agent/system-overview')
            );
    }

    public function test_deployments_index_exposes_operator_secondary_links_within_two_clicks(): void
    {
        $user = User::factory()->create();

        $workflowKey = 'eng.repo-analysis.v1';
        $activeBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->setBuildState($activeBuildId, null, now()->subMinute());
        $this->insertReliabilityRow($workflowKey, $activeBuildId);

        $this->actingAs($user)
            ->get('/agent/deployments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Agent/Deployments/Index')
                ->where('navigation.systemOverview', '/agent/system-overview')
                ->where('navigation.escalations', '/agent/escalations')
                ->where('navigation.budgets', '/agent/budgets')
                ->where('navigation.replayBuilds', '/agent/replay-builds')
                ->where('workflows.0.workflow_key', $workflowKey)
                ->where('workflows.0.links.detail', '/agent/deployments/'.$workflowKey)
            );
    }

    public function test_workflow_detail_exposes_required_deep_links(): void
    {
        $user = User::factory()->create();

        $workflowKey = 'eng.repo-analysis.v1';
        $activeBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->setBuildState($activeBuildId, null, now()->subMinute());
        $this->insertReliabilityRow($workflowKey, $activeBuildId);

        $this->actingAs($user)
            ->get('/agent/deployments/'.$workflowKey)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Agent/Deployments/Show')
                ->where('workflowKey', $workflowKey)
                ->where('deepLinks.health', '/agent/api/v1/workflows/'.$workflowKey.'/health')
                ->where('deepLinks.reliability', '/agent/api/v1/workflows/'.$workflowKey.'/reliability')
                ->where('deepLinks.cost', '/agent/api/v1/workflows/'.$workflowKey.'/cost')
                ->where('deepLinks.attemptLineage', '/agent/monitor?workflow='.$workflowKey)
                ->where('deepLinks.gateTransitions', '/agent/api/v1/workflows/'.$workflowKey.'/gate-transitions')
                ->where('deepLinks.escalationHistory', '/agent/api/v1/workflows/'.$workflowKey.'/escalations')
                ->where('deepLinks.replayBuilds', '/agent/replay-builds')
            );
    }

    private function ensureBuildExists(string $buildId, string $status): void
    {
        DB::table($this->projectionTable('telemetry_projection_builds'))->updateOrInsert(
            ['id' => $buildId],
            [
                'status' => $status,
                'activated_at' => now()->subMinute(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]
        );
    }

    private function setBuildState(?string $activeBuildId, ?string $rebuildingBuildId, mixed $activatedAt): void
    {
        DB::table($this->projectionTable('telemetry_projection_build_state'))->updateOrInsert(
            ['id' => 1],
            [
                'active_projection_build_id' => $activeBuildId,
                'rebuilding_build_id' => $rebuildingBuildId,
                'activated_at' => $activatedAt,
                'updated_at' => now(),
            ]
        );
    }

    private function insertReliabilityRow(string $workflowKey, string $buildId): void
    {
        DB::table($this->projectionTable('workflow_reliability_current'))->insert([
            'workflow_key' => $workflowKey,
            'reliability_score' => 98.1,
            'degraded_rate' => 1.2,
            'hard_fail_rate' => 0.0,
            'escalation_events' => 0,
            'projection_build_id' => $buildId,
            'source_high_watermark_ingested_at' => now()->subSeconds(5),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
