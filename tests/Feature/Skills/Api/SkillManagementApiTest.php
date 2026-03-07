<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Api;

use App\Models\AgentSkill;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SkillManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;

        $this->enableSkillsFlag();
    }

    public function test_list_skills(): void
    {
        AgentSkill::factory()->count(3)->create(['team_id' => $this->teamId]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'version', 'status', 'risk_level']],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_list_includes_global_skills(): void
    {
        $otherUser = User::factory()->withPersonalTeam()->create();
        AgentSkill::factory()->create(['team_id' => $this->teamId]);
        AgentSkill::factory()->global()->create(['team_id' => $otherUser->currentTeam->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_skill_detail(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'installed_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/agent/api/v1/skills/{$skill->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', $skill->name)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'slug', 'description', 'version', 'status', 'risk_level', 'validation_result'],
            ]);
    }

    public function test_pause_skill(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/agent/api/v1/skills/{$skill->id}", [
                'status' => 'paused',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $this->assertDatabaseHas('agent_skills', [
            'id' => $skill->id,
            'status' => AgentSkill::STATUS_PAUSED,
        ]);
    }

    public function test_resume_skill(): void
    {
        $skill = AgentSkill::factory()->paused()->create([
            'team_id' => $this->teamId,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/agent/api/v1/skills/{$skill->id}", [
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('agent_skills', [
            'id' => $skill->id,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
    }

    public function test_delete_skill(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'skill_path' => '/tmp/nonexistent-skill-path-'.uniqid(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/agent/api/v1/skills/{$skill->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('agent_skills', ['id' => $skill->id]);
    }

    public function test_delete_requires_admin(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $this->enableSkillsFlag();

        $skill = AgentSkill::factory()->create([
            'team_id' => $owner->currentTeam->id,
        ]);

        $response = $this->actingAs($member)
            ->deleteJson("/agent/api/v1/skills/{$skill->id}");

        $response->assertStatus(403);
    }

    public function test_revalidate_skill(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'skill_path' => base_path('tests/Fixtures/Skills/valid-skill'),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/agent/api/v1/skills/{$skill->id}/validate");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'status', 'validation_result'],
            ]);
    }

    public function test_show_nonexistent_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    private function enableSkillsFlag(): void
    {
        $flagManager = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);
    }
}
