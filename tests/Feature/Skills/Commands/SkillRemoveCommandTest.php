<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use App\Models\AgentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillRemoveCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;
    }

    public function test_removes_installed_skill_with_confirmation(): void
    {
        $skillPath = storage_path('app/skills/'.$this->teamId.'/removable-skill');
        File::ensureDirectoryExists($skillPath);
        File::put($skillPath.'/SKILL.md', 'test');

        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'removable-skill',
            'slug' => 'removable-skill',
            'skill_path' => $skillPath,
        ]);

        $this->artisan('skill:remove', [
            'name' => 'removable-skill',
            '--team' => $this->teamId,
        ])
            ->expectsConfirmation("Are you sure you want to remove skill 'removable-skill'?", 'yes')
            ->assertSuccessful()
            ->expectsOutputToContain('removed');

        $this->assertDatabaseMissing('agent_skills', ['id' => $skill->id]);
    }

    public function test_remove_with_force_skips_confirmation(): void
    {
        $skillPath = storage_path('app/skills/'.$this->teamId.'/force-removable');
        File::ensureDirectoryExists($skillPath);
        File::put($skillPath.'/SKILL.md', 'test');

        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'force-removable',
            'slug' => 'force-removable',
            'skill_path' => $skillPath,
        ]);

        $this->artisan('skill:remove', [
            'name' => 'force-removable',
            '--team' => $this->teamId,
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('removed');

        $this->assertDatabaseMissing('agent_skills', ['id' => $skill->id]);
    }

    public function test_remove_nonexistent_shows_error(): void
    {
        $this->artisan('skill:remove', [
            'name' => 'nonexistent-skill',
            '--team' => $this->teamId,
        ])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    protected function tearDown(): void
    {
        $skillsPath = config('agent.skills.storage_path');
        if (File::isDirectory($skillsPath.'/'.$this->teamId)) {
            File::deleteDirectory($skillsPath.'/'.$this->teamId);
        }

        parent::tearDown();
    }
}
