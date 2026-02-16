<?php

namespace Tests\Unit;

use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\BuildTaskGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInterrogationBuildTasksJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_persists_generated_build_tasks_and_sets_ready_status(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build generation',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => ['plan_markdown' => 'Plan content'],
            'metadata_json' => [
                'build' => [
                    'status' => 'generating_tasks',
                ],
            ],
        ]);

        $generator = $this->mock(BuildTaskGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andReturn([
                'tasks' => [
                    [
                        'sequence' => 1,
                        'title' => 'Create controller actions',
                        'description' => 'Add missing build endpoints.',
                        'instructions_markdown' => 'Implement controller endpoints and validations.',
                    ],
                    [
                        'sequence' => 2,
                        'title' => 'Wire frontend panel',
                        'description' => 'Add build panel in planning phase.',
                        'instructions_markdown' => 'Render task list and active run status.',
                    ],
                ],
            ]);

        $job = new GenerateInterrogationBuildTasksJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame('ready', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(2, data_get($session->metadata_json, 'build.task_count'));

        $tasks = InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(2, $tasks);
        $this->assertSame(InterrogationBuildTask::STATUS_PENDING, (string) $tasks[0]->status);
        $this->assertSame('Create controller actions', (string) $tasks[0]->title);
    }
}
