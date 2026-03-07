<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Models\AgentSkill;
use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\OrgRitualTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\Skills\SkillOrgIntegrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillOrgIntegratorTest extends TestCase
{
    use RefreshDatabase;

    private SkillOrgIntegrator $integrator;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integrator = new SkillOrgIntegrator;
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_get_accessible_skills_filters_by_profile(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-a',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-b',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-c',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $profile = OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'skill_access_profile' => [
                'allowed_skills' => ['skill-a', 'skill-b'],
            ],
        ]);

        $accessible = $this->integrator->getAccessibleSkills($profile, $this->team->id);

        $this->assertCount(2, $accessible);
        $slugs = $accessible->pluck('slug')->toArray();
        $this->assertContains('skill-a', $slugs);
        $this->assertContains('skill-b', $slugs);
        $this->assertNotContains('skill-c', $slugs);
    }

    public function test_get_accessible_skills_respects_max_risk_level(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'low-risk',
            'risk_level' => AgentSkill::RISK_LOW,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'standard-risk',
            'risk_level' => AgentSkill::RISK_STANDARD,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'elevated-risk',
            'risk_level' => AgentSkill::RISK_ELEVATED,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'critical-risk',
            'risk_level' => AgentSkill::RISK_CRITICAL,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $profile = OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'skill_access_profile' => [
                'max_risk_level' => 'standard',
            ],
        ]);

        $accessible = $this->integrator->getAccessibleSkills($profile, $this->team->id);
        $slugs = $accessible->pluck('slug')->toArray();

        $this->assertContains('low-risk', $slugs);
        $this->assertContains('standard-risk', $slugs);
        $this->assertNotContains('elevated-risk', $slugs);
        $this->assertNotContains('critical-risk', $slugs);
    }

    public function test_get_accessible_skills_respects_denied_skills(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-a',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-b',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $profile = OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'skill_access_profile' => [
                'denied_skills' => ['skill-b'],
            ],
        ]);

        $accessible = $this->integrator->getAccessibleSkills($profile, $this->team->id);
        $slugs = $accessible->pluck('slug')->toArray();

        $this->assertContains('skill-a', $slugs);
        $this->assertNotContains('skill-b', $slugs);
    }

    public function test_validate_ritual_skill_requirements_passes_when_all_installed(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-a',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-b',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $ritual = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
            'required_skills' => ['skill-a', 'skill-b'],
        ]);

        $errors = $this->integrator->validateRitualSkillRequirements($ritual, $this->team->id);

        $this->assertEmpty($errors);
    }

    public function test_validate_ritual_skill_requirements_fails_when_missing(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-a',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $ritual = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
            'required_skills' => ['skill-a', 'skill-missing'],
        ]);

        $errors = $this->integrator->validateRitualSkillRequirements($ritual, $this->team->id);

        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('skill-missing', $errors[0]);
    }

    public function test_validate_ritual_skill_requirements_fails_when_paused(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-a',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->paused()->create([
            'team_id' => $this->team->id,
            'slug' => 'skill-paused',
        ]);

        $ritual = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
            'required_skills' => ['skill-a', 'skill-paused'],
        ]);

        $errors = $this->integrator->validateRitualSkillRequirements($ritual, $this->team->id);

        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('skill-paused', $errors[0]);
        $this->assertStringContainsString('paused', strtolower($errors[0]));
    }
}
