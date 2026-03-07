<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use App\Models\AgentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillListCommandTest extends TestCase
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

    public function test_lists_installed_skills(): void
    {
        AgentSkill::factory()->count(3)->create([
            'team_id' => $this->teamId,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $this->artisan('skill:list', ['--team' => $this->teamId])
            ->assertSuccessful();
    }

    public function test_list_filters_by_status(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'active-skill',
            'slug' => 'active-skill',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'paused-skill',
            'slug' => 'paused-skill',
            'status' => AgentSkill::STATUS_PAUSED,
        ]);

        $this->artisan('skill:list', [
            '--team' => $this->teamId,
            '--status' => 'paused',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('paused-skill')
            ->doesntExpectOutputToContain('active-skill');
    }

    public function test_list_shows_empty_message(): void
    {
        $this->artisan('skill:list', ['--team' => $this->teamId])
            ->assertSuccessful()
            ->expectsOutputToContain('No skills installed');
    }
}
