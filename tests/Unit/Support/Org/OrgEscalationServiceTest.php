<?php

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgEscalation;
use App\Models\OrgReportingEdge;
use App\Models\OrgRitualRun;
use App\Models\User;
use App\Support\Org\OrgEscalationService;
use Tests\TestCase;

class OrgEscalationServiceTest extends TestCase
{
    private OrgEscalationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgEscalationService::class);
    }

    public function test_creates_escalation_with_timeout(): void
    {
        $user = User::factory()->create();
        $manager = OrgAgentProfile::factory()->for($user)->create();
        $run = OrgRitualRun::factory()->create(['user_id' => $user->id]);

        $escalation = $this->service->createEscalation(
            $run,
            'budget',
            $manager,
            timeoutSeconds: 3600
        );

        $this->assertEquals('pending', $escalation->state);
        $this->assertNotNull($escalation->timeout_at);
    }

    public function test_resolve_updates_state(): void
    {
        $escalation = OrgEscalation::factory()->create(['state' => 'pending']);

        $this->service->resolve($escalation, 'approved', 'admin-user', 'Approved after review');

        $this->assertEquals('approved', $escalation->fresh()->state);
        $this->assertEquals('admin-user', $escalation->fresh()->resolved_by);
    }

    public function test_check_timeouts_marks_expired_as_timed_out(): void
    {
        $escalation = OrgEscalation::factory()->create([
            'state' => 'pending',
            'timeout_at' => now()->subHour(),
        ]);

        $timedOut = $this->service->checkTimeouts();

        $this->assertCount(1, $timedOut);
        $this->assertEquals('timed_out', $escalation->fresh()->state);
    }

    public function test_escalation_routes_through_reporting_edge(): void
    {
        $user = User::factory()->create();
        $senior = OrgAgentProfile::factory()->for($user)->create();
        $manager = OrgAgentProfile::factory()->for($user)->create();
        $agent = OrgAgentProfile::factory()->for($user)->create();

        OrgReportingEdge::factory()->create([
            'subordinate_agent_id' => $agent->id,
            'manager_agent_id' => $manager->id,
            'user_id' => $user->id,
        ]);

        $target = $this->service->getEscalationTarget($agent);

        $this->assertEquals($manager->id, $target->id);
    }
}
