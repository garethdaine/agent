<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Models\AgentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSkillModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_return_correct_types(): void
    {
        $casts = (new AgentSkill)->getCasts();

        $this->assertSame('array', $casts['industries']);
        $this->assertSame('array', $casts['memory_blocks']);
        $this->assertSame('array', $casts['mcp_dependencies']);
        $this->assertSame('array', $casts['tools_required']);
        $this->assertSame('array', $casts['trigger_keywords']);
        $this->assertSame('array', $casts['run_after']);
        $this->assertSame('array', $casts['file_hashes']);
        $this->assertSame('array', $casts['validation_result']);
        $this->assertSame('boolean', $casts['requires_approval']);
        $this->assertSame('boolean', $casts['is_global']);
        $this->assertSame('datetime', $casts['installed_at']);
        $this->assertSame('datetime', $casts['paused_at']);
        $this->assertSame('datetime', $casts['last_invoked_at']);
        $this->assertSame('integer', $casts['invocation_count']);
    }

    public function test_scope_active_returns_only_active_records(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        AgentSkill::factory()->for($team)->create(['status' => AgentSkill::STATUS_ACTIVE]);
        AgentSkill::factory()->for($team)->create(['status' => AgentSkill::STATUS_PAUSED]);
        AgentSkill::factory()->for($team)->create(['status' => AgentSkill::STATUS_ACTIVE]);

        $activeSkills = AgentSkill::active()->get();

        $this->assertCount(2, $activeSkills);
        $activeSkills->each(fn ($skill) => $this->assertSame(AgentSkill::STATUS_ACTIVE, $skill->status));
    }

    public function test_scope_for_team_filters_by_team_id(): void
    {
        $user1 = User::factory()->withPersonalTeam()->create();
        $user2 = User::factory()->withPersonalTeam()->create();
        $team1 = $user1->currentTeam;
        $team2 = $user2->currentTeam;

        AgentSkill::factory()->for($team1)->count(2)->create();
        AgentSkill::factory()->for($team2)->count(3)->create();

        $this->assertCount(2, AgentSkill::forTeam($team1->id)->get());
        $this->assertCount(3, AgentSkill::forTeam($team2->id)->get());
    }

    public function test_scope_global_returns_only_global_records(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        AgentSkill::factory()->for($team)->create(['is_global' => true]);
        AgentSkill::factory()->for($team)->create(['is_global' => false]);
        AgentSkill::factory()->for($team)->create(['is_global' => true]);

        $globalSkills = AgentSkill::global()->get();

        $this->assertCount(2, $globalSkills);
        $globalSkills->each(fn ($skill) => $this->assertTrue($skill->is_global));
    }

    public function test_scope_by_risk_level_filters_correctly(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        AgentSkill::factory()->for($team)->create(['risk_level' => AgentSkill::RISK_LOW]);
        AgentSkill::factory()->for($team)->create(['risk_level' => AgentSkill::RISK_ELEVATED]);
        AgentSkill::factory()->for($team)->create(['risk_level' => AgentSkill::RISK_ELEVATED]);

        $this->assertCount(1, AgentSkill::byRiskLevel(AgentSkill::RISK_LOW)->get());
        $this->assertCount(2, AgentSkill::byRiskLevel(AgentSkill::RISK_ELEVATED)->get());
    }

    public function test_belongs_to_team_relationship(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $skill = AgentSkill::factory()->for($team)->create();

        $this->assertTrue($skill->team->is($team));
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('active', AgentSkill::STATUS_ACTIVE);
        $this->assertSame('paused', AgentSkill::STATUS_PAUSED);
        $this->assertSame('failed_validation', AgentSkill::STATUS_FAILED_VALIDATION);
        $this->assertSame('pending_review', AgentSkill::STATUS_PENDING_REVIEW);
    }

    public function test_risk_level_constants_are_defined(): void
    {
        $this->assertSame('low', AgentSkill::RISK_LOW);
        $this->assertSame('standard', AgentSkill::RISK_STANDARD);
        $this->assertSame('elevated', AgentSkill::RISK_ELEVATED);
        $this->assertSame('critical', AgentSkill::RISK_CRITICAL);
    }

    public function test_factory_creates_valid_model(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $skill = AgentSkill::factory()->for($user->currentTeam)->create();

        $this->assertNotNull($skill->id);
        $this->assertNotNull($skill->team_id);
        $this->assertNotNull($skill->name);
        $this->assertNotNull($skill->slug);
    }
}
