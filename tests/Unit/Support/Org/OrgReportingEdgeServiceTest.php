<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgReportingEdge;
use App\Models\User;
use App\Support\Org\Exceptions\CycleDetectedException;
use App\Support\Org\Exceptions\HierarchyDepthExceededException;
use App\Support\Org\OrgReportingEdgeService;
use Tests\TestCase;

class OrgReportingEdgeServiceTest extends TestCase
{
    private OrgReportingEdgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgReportingEdgeService::class);
    }

    public function test_set_manager_creates_edge(): void
    {
        $user = User::factory()->create();
        $subordinate = OrgAgentProfile::factory()->forUser($user)->create();
        $manager = OrgAgentProfile::factory()->forUser($user)->create();

        $edge = $this->service->setManager($subordinate, $manager);

        $this->assertInstanceOf(OrgReportingEdge::class, $edge);
        $this->assertEquals($subordinate->id, $edge->subordinate_agent_id);
        $this->assertEquals($manager->id, $edge->manager_agent_id);
    }

    public function test_rejects_hierarchy_depth_exceeding_three(): void
    {
        $user = User::factory()->create();
        $seniorManager = OrgAgentProfile::factory()->forUser($user)->create();
        $manager = OrgAgentProfile::factory()->forUser($user)->create();
        $agent = OrgAgentProfile::factory()->forUser($user)->create();
        $newAgent = OrgAgentProfile::factory()->forUser($user)->create();

        // Create hierarchy: seniorManager <- manager <- agent
        $this->service->setManager($manager, $seniorManager);
        $this->service->setManager($agent, $manager);

        // Attempting to add newAgent under agent would create depth 4
        $this->expectException(HierarchyDepthExceededException::class);

        $this->service->setManager($newAgent, $agent);
    }

    public function test_detects_cycle(): void
    {
        $user = User::factory()->create();
        $agent1 = OrgAgentProfile::factory()->forUser($user)->create();
        $agent2 = OrgAgentProfile::factory()->forUser($user)->create();

        // agent2 reports to agent1
        $this->service->setManager($agent2, $agent1);

        // Attempting to make agent1 report to agent2 would create a cycle
        $this->expectException(CycleDetectedException::class);

        $this->service->setManager($agent1, $agent2);
    }

    public function test_get_escalation_path_returns_ordered_managers(): void
    {
        $user = User::factory()->create();
        $senior = OrgAgentProfile::factory()->forUser($user)->create(['name' => 'senior']);
        $manager = OrgAgentProfile::factory()->forUser($user)->create(['name' => 'manager']);
        $agent = OrgAgentProfile::factory()->forUser($user)->create(['name' => 'agent']);

        // Create hierarchy: senior <- manager <- agent
        $this->service->setManager($manager, $senior);
        $this->service->setManager($agent, $manager);

        $path = $this->service->getEscalationPath($agent);

        $this->assertCount(2, $path);
        $this->assertEquals($manager->id, $path[0]->id);
        $this->assertEquals($senior->id, $path[1]->id);
    }
}
