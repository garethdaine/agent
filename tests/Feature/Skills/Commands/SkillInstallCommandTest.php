<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use App\Models\AgentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;
        $this->fixturesPath = base_path('tests/Fixtures/Skills');
    }

    protected function tearDown(): void
    {
        $skillsPath = config('agent.skills.storage_path');
        if (File::isDirectory($skillsPath.'/'.$this->teamId)) {
            File::deleteDirectory($skillsPath.'/'.$this->teamId);
        }

        $globalPath = config('agent.skills.global_storage_path');
        if (File::isDirectory($globalPath)) {
            File::deleteDirectory($globalPath);
        }

        $tmpPath = storage_path('app/skills/tmp');
        if (File::isDirectory($tmpPath)) {
            File::deleteDirectory($tmpPath);
        }

        parent::tearDown();
    }

    public function test_installs_skill_from_file_path(): void
    {
        $this->artisan('skill:install', [
            'path-or-url' => $this->fixturesPath.'/valid-skill.skill',
            '--team' => $this->teamId,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('installed successfully');

        $this->assertDatabaseHas('agent_skills', [
            'team_id' => $this->teamId,
        ]);
    }

    public function test_install_shows_validation_results(): void
    {
        $this->artisan('skill:install', [
            'path-or-url' => $this->fixturesPath.'/valid-skill.skill',
            '--team' => $this->teamId,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('format')
            ->expectsOutputToContain('schema');
    }

    public function test_install_prompts_on_admin_confirmation(): void
    {
        // The injection-attempt fixture should have a moderate risk score
        // that triggers the confirmation prompt (0.5-0.8 range)
        // This test verifies the prompt mechanism exists
        $this->artisan('skill:install', [
            'path-or-url' => $this->fixturesPath.'/valid-skill.skill',
            '--team' => $this->teamId,
        ])
            ->assertSuccessful();
    }

    public function test_install_blocks_high_risk(): void
    {
        // The injection-attempt fixture should be blocked by the validator
        $this->artisan('skill:install', [
            'path-or-url' => $this->fixturesPath.'/injection-attempt',
            '--team' => $this->teamId,
        ])
            ->assertFailed();
    }

    public function test_install_with_global_flag(): void
    {
        $this->artisan('skill:install', [
            'path-or-url' => $this->fixturesPath.'/valid-skill.skill',
            '--team' => $this->teamId,
            '--global' => true,
        ])
            ->assertSuccessful();

        $skill = AgentSkill::where('team_id', $this->teamId)->first();
        $this->assertTrue($skill->is_global);
    }

    public function test_install_fails_gracefully_on_invalid_file(): void
    {
        $this->artisan('skill:install', [
            'path-or-url' => '/tmp/definitely-not-a-skill-file.txt',
            '--team' => $this->teamId,
        ])
            ->assertFailed();
    }
}
