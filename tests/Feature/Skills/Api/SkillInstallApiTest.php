<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Api;

use App\Models\AgentSkill;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SkillInstallApiTest extends TestCase
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

    protected function tearDown(): void
    {
        // Clean up any installed skill files
        $skillsPath = config('agent.skills.storage_path');
        if (File::isDirectory($skillsPath.'/'.$this->teamId)) {
            File::deleteDirectory($skillsPath.'/'.$this->teamId);
        }

        parent::tearDown();
    }

    public function test_install_skill_via_file_upload(): void
    {
        $fixturePath = base_path('tests/Fixtures/Skills/valid-skill.skill');
        $file = new UploadedFile($fixturePath, 'valid-skill.skill', 'application/zip', null, true);

        $response = $this->actingAs($this->user)
            ->postJson('/agent/api/v1/skills/install', [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'slug', 'version', 'status', 'risk_level'],
            ])
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('agent_skills', [
            'team_id' => $this->teamId,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
    }

    public function test_install_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('test-skill.skill', 100, 'application/zip');

        $response = $this->postJson('/agent/api/v1/skills/install', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_install_requires_team_admin(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $this->enableSkillsFlag();

        $file = UploadedFile::fake()->create('test-skill.skill', 100, 'application/zip');

        $response = $this->actingAs($member)
            ->postJson('/agent/api/v1/skills/install', [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }

    public function test_install_validates_file_required(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/agent/api/v1/skills/install', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['file']]]);
    }

    public function test_install_returns_validation_errors(): void
    {
        // Upload a non-ZIP file that will fail extraction
        $file = UploadedFile::fake()->create('bad-skill.skill', 100, 'text/plain');

        $response = $this->actingAs($this->user)
            ->postJson('/agent/api/v1/skills/install', [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SKILL_VALIDATION_FAILED');
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
