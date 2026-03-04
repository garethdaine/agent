<?php

declare(strict_types=1);

namespace Tests\Feature\AgentUi;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GovernanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_sees_all_governance_controls(): void
    {
        $admin = User::factory()->create();
        config()->set('agent.roles.admin_user_ids', [$admin->id]);

        $workflowKey = 'eng.repo-analysis.v1';
        AgentJob::factory()->create([
            'workflow_key' => $workflowKey,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->get('/agent/deployments/'.$workflowKey)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('governance.canPauseResume', true)
                ->where('governance.canManageEscalations', true)
                ->where('governance.canManageReplay', true)
            );
    }

    public function test_central_on_call_can_manage_escalation_controls_but_not_replay_controls(): void
    {
        $onCall = User::factory()->create();
        config()->set('agent.roles.central_on_call_user_ids', [$onCall->id]);

        $workflowKey = 'eng.repo-analysis.v1';
        AgentJob::factory()->create([
            'workflow_key' => $workflowKey,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($onCall)
            ->get('/agent/deployments/'.$workflowKey)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('governance.canPauseResume', true)
                ->where('governance.canManageEscalations', true)
                ->where('governance.canManageReplay', false)
            );
    }

    public function test_non_privileged_user_does_not_see_governance_controls(): void
    {
        $user = User::factory()->create();

        $workflowKey = 'eng.repo-analysis.v1';
        AgentJob::factory()->create([
            'workflow_key' => $workflowKey,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->get('/agent/deployments/'.$workflowKey)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('governance.canPauseResume', false)
                ->where('governance.canManageEscalations', false)
                ->where('governance.canManageReplay', false)
            );
    }
}
