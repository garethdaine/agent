<?php

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgCostLedger;
use App\Models\OrgRitualRun;
use App\Models\User;
use App\Support\Org\OrgCostGovernanceService;
use Tests\TestCase;

class OrgCostGovernanceServiceTest extends TestCase
{
    private OrgCostGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgCostGovernanceService::class);
    }

    public function test_records_cost_at_agent_level(): void
    {
        $user = User::factory()->create();
        $agent = OrgAgentProfile::factory()->for($user)->create();
        $run = OrgRitualRun::factory()->create(['user_id' => $user->id]);

        $this->service->recordCost($run, $agent, [
            'token_count' => 1000,
            'runtime_ms' => 5000,
            'estimated_cost_usd' => 0.002,
        ]);

        $this->assertDatabaseHas('org_cost_ledgers', [
            'org_agent_id' => $agent->id,
            'ritual_run_id' => $run->id,
            'token_count' => 1000,
        ]);
    }

    public function test_get_ritual_run_costs_aggregates_all_agents(): void
    {
        $run = OrgRitualRun::factory()->create();
        OrgCostLedger::factory()->create([
            'ritual_run_id' => $run->id,
            'token_count' => 500,
            'estimated_cost_usd' => 0.001,
        ]);
        OrgCostLedger::factory()->create([
            'ritual_run_id' => $run->id,
            'token_count' => 500,
            'estimated_cost_usd' => 0.001,
        ]);

        $summary = $this->service->getRitualRunCosts($run);

        $this->assertEquals(1000, $summary->total_tokens);
        $this->assertEquals(0.002, $summary->total_cost_usd);
    }

    public function test_hard_stop_behavior_blocks_undispatched(): void
    {
        config(['agent.org.budget_hard_stop_behavior' => 'block_undispatched']);

        $run = OrgRitualRun::factory()->create(['state' => 'running']);

        $result = $this->service->enforceHardStop($run);

        $this->assertEquals('block_undispatched', $result->behavior);
        $this->assertFalse($result->terminate_in_flight);
    }
}
