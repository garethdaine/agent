<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Services\Interrogation\InterrogationBuildService;
use App\Support\Interrogation\SessionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskReviewPauseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private InterrogationBuildService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['agent.interrogation.max_active_sessions' => 50]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->service = app(InterrogationBuildService::class);
    }

    public function test_transform_build_state_includes_task_review_fields(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => [
                    'build' => [
                        'status' => 'paused',
                        'pause_reason' => 'task_review',
                        'task_review_summary' => '## Task 1 Completed: Test Task',
                        'task_review_task_id' => 42,
                    ],
                ],
            ]);

        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1, 'title' => 'Test Task']);

        $buildState = $this->service->transformBuildState($session, true);

        $this->assertSame('paused', $buildState['status']);
        $this->assertSame('task_review', $buildState['pause_reason']);
        $this->assertSame('## Task 1 Completed: Test Task', $buildState['task_review_summary']);
        $this->assertSame(42, $buildState['task_review_task_id']);
    }

    public function test_transform_build_state_defaults_task_review_fields_to_null(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => [
                    'build' => [
                        'status' => 'running',
                    ],
                ],
            ]);

        $buildState = $this->service->transformBuildState($session, true);

        $this->assertNull($buildState['task_review_summary']);
        $this->assertNull($buildState['task_review_task_id']);
    }

    public function test_resume_build_clears_task_review_data(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withBuildSettings(['auto_advance_tasks' => false])
            ->create([
                'status' => InterrogationSession::STATUS_PAUSED,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => array_merge(
                    InterrogationSession::factory()->make()->metadata_json ?? [],
                    [
                        'build' => [
                            'status' => 'paused',
                            'pause_reason' => 'task_review',
                            'task_review_summary' => '## Task 1 Completed: Test Task',
                            'task_review_task_id' => 1,
                        ],
                        'build_settings' => [
                            'auto_advance_tasks' => false,
                        ],
                    ]
                ),
            ]);

        // Create a pending task so the build has work to do
        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 2, 'status' => InterrogationBuildTask::STATUS_PENDING]);

        $this->service->resumeBuild($session, app(SessionStateTransitionService::class));

        $session->refresh();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];

        $this->assertNull($build['task_review_summary']);
        $this->assertNull($build['task_review_task_id']);
        $this->assertNull($build['pause_reason']);
        $this->assertSame('running', $build['status']);
    }

    public function test_build_settings_auto_advance_true_does_not_affect_normal_resume(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withBuildSettings(['auto_advance_tasks' => true])
            ->create([
                'status' => InterrogationSession::STATUS_PAUSED,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => array_merge(
                    InterrogationSession::factory()->make()->metadata_json ?? [],
                    [
                        'build' => [
                            'status' => 'paused',
                            'pause_reason' => 'approval',
                        ],
                        'build_settings' => [
                            'auto_advance_tasks' => true,
                        ],
                    ]
                ),
            ]);

        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 1, 'status' => InterrogationBuildTask::STATUS_PENDING]);

        $this->service->resumeBuild($session, app(SessionStateTransitionService::class));

        $session->refresh();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];

        $this->assertSame('running', $build['status']);
    }

    public function test_transform_build_state_aggregates_artefacts_from_tasks(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => [
                    'build' => ['status' => 'running'],
                ],
            ]);

        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 1,
                'title' => 'Task A',
                'metadata_json' => [
                    'artefacts' => [
                        ['name' => 'README.md', 'type' => 'document', 'path' => 'docs/README.md', 'detected_at' => now()->toIso8601String()],
                    ],
                ],
            ]);

        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 2,
                'title' => 'Task B',
                'metadata_json' => [
                    'artefacts' => [
                        ['name' => 'config.json', 'type' => 'config', 'path' => 'output/config.json', 'detected_at' => now()->toIso8601String()],
                    ],
                ],
            ]);

        $buildState = $this->service->transformBuildState($session, true);

        $this->assertCount(2, $buildState['artefacts']);
        $this->assertSame('README.md', $buildState['artefacts'][0]['name']);
        $this->assertSame('document', $buildState['artefacts'][0]['type']);
        $this->assertSame('Task A', $buildState['artefacts'][0]['task_title']);
        $this->assertSame('config.json', $buildState['artefacts'][1]['name']);
        $this->assertSame('config', $buildState['artefacts'][1]['type']);
        $this->assertSame('Task B', $buildState['artefacts'][1]['task_title']);
    }

    public function test_transform_build_state_returns_empty_artefacts_when_none_exist(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => [
                    'build' => ['status' => 'running'],
                ],
            ]);

        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1, 'metadata_json' => null]);

        $buildState = $this->service->transformBuildState($session, true);

        $this->assertIsArray($buildState['artefacts']);
        $this->assertEmpty($buildState['artefacts']);
    }
}
