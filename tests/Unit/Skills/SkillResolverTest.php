<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Models\AgentSkill;
use App\Models\DelegateeProfile;
use App\Services\Skills\DTOs\ResolvedSkill;
use App\Services\Skills\SkillResolver;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SkillResolverTest extends TestCase
{
    use RefreshDatabase;

    private SkillResolver $resolver;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(SkillResolver::class);
        $user = \App\Models\User::factory()->withPersonalTeam()->create();
        $this->teamId = $user->currentTeam->id;
    }

    public function test_resolves_skills_matching_keywords(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'compliance-checker',
            'trigger_keywords' => ['compliance', 'regulation', 'audit'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'code-reviewer',
            'trigger_keywords' => ['code', 'review', 'quality'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $result = $this->resolver->resolve(
            'Run a compliance audit on the financial reports',
            $this->teamId,
        );

        $this->assertNotEmpty($result);
        $this->assertContainsOnlyInstancesOf(ResolvedSkill::class, $result);

        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);
        $this->assertContains('compliance-checker', $names);
    }

    public function test_filters_by_active_status(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'active-skill',
            'trigger_keywords' => ['deploy', 'release'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        AgentSkill::factory()->paused()->create([
            'team_id' => $this->teamId,
            'name' => 'paused-skill',
            'trigger_keywords' => ['deploy', 'release'],
        ]);

        $result = $this->resolver->resolve('Deploy the release', $this->teamId);
        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);

        $this->assertContains('active-skill', $names);
        $this->assertNotContains('paused-skill', $names);
    }

    public function test_filters_by_trust_score_threshold(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'elevated-risk-skill',
            'trigger_keywords' => ['financial', 'transaction'],
            'risk_level' => AgentSkill::RISK_ELEVATED,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $lowTrustProfile = new DelegateeProfile;
        $lowTrustProfile->trust_score = 0.3;

        $result = $this->resolver->resolve(
            'Process financial transaction',
            $this->teamId,
            $lowTrustProfile,
        );

        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);
        $this->assertNotContains('elevated-risk-skill', $names);
    }

    public function test_includes_global_skills(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'team-skill',
            'trigger_keywords' => ['analysis', 'data'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $otherUser = \App\Models\User::factory()->withPersonalTeam()->create();
        AgentSkill::factory()->global()->create([
            'team_id' => $otherUser->currentTeam->id,
            'name' => 'global-skill',
            'trigger_keywords' => ['analysis', 'report'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $result = $this->resolver->resolve('Run data analysis report', $this->teamId);
        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);

        $this->assertContains('team-skill', $names);
        $this->assertContains('global-skill', $names);
    }

    public function test_respects_max_skills_per_invocation(): void
    {
        config(['agent.skills.max_skills_per_invocation' => 5]);

        for ($i = 0; $i < 10; $i++) {
            AgentSkill::factory()->create([
                'team_id' => $this->teamId,
                'name' => "skill-{$i}",
                'trigger_keywords' => ['common', 'keyword'],
                'status' => AgentSkill::STATUS_ACTIVE,
            ]);
        }

        $result = $this->resolver->resolve('Use common keyword tasks', $this->teamId);

        $this->assertCount(5, $result);
    }

    public function test_orders_by_run_after_dag(): void
    {
        $skillA = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-a',
            'slug' => 'skill-a',
            'trigger_keywords' => ['pipeline', 'process'],
            'run_after' => [],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $skillB = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-b',
            'slug' => 'skill-b',
            'trigger_keywords' => ['pipeline', 'process'],
            'run_after' => ['skill-a'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $skillC = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-c',
            'slug' => 'skill-c',
            'trigger_keywords' => ['pipeline', 'process'],
            'run_after' => ['skill-b'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $result = $this->resolver->resolve('Run the pipeline process', $this->teamId);
        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);

        $aIndex = array_search('skill-a', $names);
        $bIndex = array_search('skill-b', $names);
        $cIndex = array_search('skill-c', $names);

        $this->assertLessThan($bIndex, $aIndex);
        $this->assertLessThan($cIndex, $bIndex);
    }

    public function test_handles_circular_run_after_gracefully(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();

        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'cycle-a',
            'slug' => 'cycle-a',
            'trigger_keywords' => ['cycle', 'test'],
            'run_after' => ['cycle-b'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'cycle-b',
            'slug' => 'cycle-b',
            'trigger_keywords' => ['cycle', 'test'],
            'run_after' => ['cycle-a'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'safe-skill',
            'slug' => 'safe-skill',
            'trigger_keywords' => ['cycle', 'test'],
            'run_after' => [],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $result = $this->resolver->resolve('Run cycle test', $this->teamId);
        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);

        $this->assertContains('safe-skill', $names);
        $this->assertNotContains('cycle-a', $names);
        $this->assertNotContains('cycle-b', $names);
    }

    public function test_returns_empty_when_no_matches(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'specific-skill',
            'trigger_keywords' => ['blockchain', 'crypto'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $result = $this->resolver->resolve('Write a unit test for the user model', $this->teamId);

        $this->assertEmpty($result);
    }

    public function test_falls_back_to_keyword_scores_when_llm_unavailable(): void
    {
        config(['agent.features' => [
            FeatureFlagManager::SKILLS_AUTO_RESOLVE => true,
        ]]);

        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'fallback-skill',
            'trigger_keywords' => ['testing', 'validation'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        // Even with auto_resolve enabled, if LLM is unavailable, should still resolve via keywords
        $result = $this->resolver->resolve('Run testing and validation', $this->teamId);
        $names = array_map(fn (ResolvedSkill $s) => $s->name, $result);

        $this->assertContains('fallback-skill', $names);
    }
}
