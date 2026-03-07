<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Api;

use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SkillLibraryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    private string $tempManifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;

        $this->enableSkillsFlag();
        $this->setupTestManifest();
    }

    protected function tearDown(): void
    {
        if (isset($this->tempManifestPath) && File::exists($this->tempManifestPath)) {
            File::delete($this->tempManifestPath);
        }

        parent::tearDown();
    }

    public function test_browse_library(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/library');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'compliance-check')
            ->assertJsonPath('data.1.slug', 'pii-detector');
    }

    public function test_browse_library_filters_by_category(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/library?category=compliance');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'compliance');
    }

    public function test_install_from_library_unknown_slug(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/agent/api/v1/skills/library/nonexistent/install');

        $response->assertStatus(404);
    }

    public function test_library_requires_feature_flag(): void
    {
        $flagManager = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)
            ->andReturn(false);
        $flagManager->shouldReceive('enabled')
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/library');

        $response->assertStatus(403);
    }

    public function test_install_from_library(): void
    {
        // Point the manifest to the real fixture .skill file
        $manifest = [
            'version' => '1.0.0',
            'skills' => [
                [
                    'slug' => 'valid-test-skill',
                    'name' => 'Valid Test Skill',
                    'description' => 'A valid test skill that meets minimum requirements length',
                    'version' => '1.0.0',
                    'author' => 'agentops',
                    'category' => 'testing',
                    'industries' => [],
                    'risk_level' => 'standard',
                    'file' => 'tests/Fixtures/Skills/valid-skill.skill',
                    'checksum' => 'sha256:test',
                ],
            ],
        ];
        File::put($this->tempManifestPath, json_encode($manifest));
        $this->app->singleton(\App\Services\Skills\SkillLibrary::class, fn () => new \App\Services\Skills\SkillLibrary(
            $this->tempManifestPath,
        ));

        $response = $this->actingAs($this->user)
            ->postJson('/agent/api/v1/skills/library/valid-test-skill/install');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'status'],
            ]);

        // Clean up installed skill files
        $skillsPath = config('agent.skills.storage_path');
        if (File::isDirectory($skillsPath.'/'.$this->teamId)) {
            File::deleteDirectory($skillsPath.'/'.$this->teamId);
        }
    }

    private function setupTestManifest(): void
    {
        $this->tempManifestPath = storage_path('app/test-skill-manifest-'.uniqid().'.json');

        $manifest = [
            'version' => '1.0.0',
            'skills' => [
                [
                    'slug' => 'compliance-check',
                    'name' => 'Compliance Check',
                    'description' => 'Validates outputs against compliance requirements',
                    'version' => '1.0.0',
                    'author' => 'agentops',
                    'category' => 'compliance',
                    'industries' => ['financial-services'],
                    'risk_level' => 'elevated',
                    'file' => 'skills/compliance-check.skill',
                    'checksum' => 'sha256:test1',
                ],
                [
                    'slug' => 'pii-detector',
                    'name' => 'PII Detector',
                    'description' => 'Detects and redacts personally identifiable information',
                    'version' => '1.0.0',
                    'author' => 'agentops',
                    'category' => 'utilities',
                    'industries' => [],
                    'risk_level' => 'standard',
                    'file' => 'skills/pii-detector.skill',
                    'checksum' => 'sha256:test2',
                ],
            ],
        ];

        File::put($this->tempManifestPath, json_encode($manifest));
        config()->set('agent.skills.library_manifest_path', $this->tempManifestPath);

        // Re-bind SkillLibrary with the test manifest path (it's a singleton bound at boot time)
        $this->app->singleton(\App\Services\Skills\SkillLibrary::class, fn () => new \App\Services\Skills\SkillLibrary(
            $this->tempManifestPath,
        ));
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
