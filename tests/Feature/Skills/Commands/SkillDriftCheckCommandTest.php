<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use App\Models\AgentSkill;
use App\Models\User;
use App\Services\Skills\DTOs\DriftCheckResult;
use App\Services\Skills\SkillDriftDetector;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SkillDriftCheckCommandTest extends TestCase
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

    public function test_detects_drift_and_updates_status(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'drifted-skill',
            'slug' => 'drifted-skill',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $detector = Mockery::mock(SkillDriftDetector::class);
        $detector->shouldReceive('checkAll')
            ->once()
            ->with($this->teamId)
            ->andReturn(collect([
                new DriftCheckResult(
                    skillId: $skill->id,
                    skillName: 'drifted-skill',
                    hasDrift: true,
                    changedFiles: ['SKILL.md'],
                ),
            ]));
        $this->app->instance(SkillDriftDetector::class, $detector);

        $this->artisan('skill:drift-check', ['--team' => $this->teamId])
            ->assertSuccessful();

        $skill->refresh();
        $this->assertEquals(AgentSkill::STATUS_PENDING_REVIEW, $skill->status);
    }

    public function test_clean_check_shows_no_drift(): void
    {
        AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'clean-skill',
            'slug' => 'clean-skill',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $detector = Mockery::mock(SkillDriftDetector::class);
        $detector->shouldReceive('checkAll')
            ->once()
            ->andReturn(collect([
                new DriftCheckResult(
                    skillId: 'some-id',
                    skillName: 'clean-skill',
                    hasDrift: false,
                ),
            ]));
        $this->app->instance(SkillDriftDetector::class, $detector);

        $this->artisan('skill:drift-check', ['--team' => $this->teamId])
            ->assertSuccessful()
            ->expectsOutputToContain('No drift detected');
    }

    public function test_scheduled_daily(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $driftEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'skill:drift-check'));

        $this->assertNotNull($driftEvent, 'skill:drift-check should be registered in the scheduler');
        $this->assertEquals('0 0 * * *', $driftEvent->expression, 'skill:drift-check should be scheduled daily');
    }
}
