<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Services\Interrogation\InterrogationBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InterrogationBuildServiceTest extends TestCase
{
    use RefreshDatabase;

    private InterrogationBuildService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new InterrogationBuildService;
        $this->user = User::factory()->create();
    }

    public function test_build_metadata_returns_empty_array_when_no_build_metadata(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['metadata_json' => []]);

        $result = $this->service->buildMetadata($session);

        $this->assertSame([], $result);
    }

    public function test_build_metadata_returns_existing_build_data(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['metadata_json' => ['build' => ['status' => 'running', 'task_count' => 3]]]);

        $result = $this->service->buildMetadata($session);

        $this->assertSame('running', $result['status']);
        $this->assertSame(3, $result['task_count']);
    }

    public function test_save_build_metadata_persists_build_data(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['metadata_json' => ['existing_key' => 'existing_value']]);

        $this->service->saveBuildMetadata($session, ['status' => 'paused', 'task_count' => 5]);

        $session->refresh();
        $metadata = $session->metadata_json;

        $this->assertSame('existing_value', $metadata['existing_key']);
        $this->assertSame('paused', $metadata['build']['status']);
        $this->assertSame(5, $metadata['build']['task_count']);
    }

    public function test_store_build_task_creates_task_with_correct_attributes(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            ]);

        $task = $this->service->storeBuildTask($session, [
            'title' => 'Implement authentication',
            'description' => 'Add JWT auth to API',
            'instructions_markdown' => '# Steps\n1. Install package',
        ]);

        $this->assertInstanceOf(InterrogationBuildTask::class, $task);
        $this->assertSame('Implement authentication', $task->title);
        $this->assertSame('Add JWT auth to API', $task->description);
        $this->assertSame(InterrogationBuildTask::STATUS_PENDING, $task->status);
        $this->assertSame(1, $task->sequence);
        $this->assertSame(0, $task->attempt_count);
    }

    public function test_store_build_task_increments_sequence(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            ]);

        $task1 = $this->service->storeBuildTask($session, ['title' => 'Task 1']);
        $task2 = $this->service->storeBuildTask($session, ['title' => 'Task 2']);

        $this->assertSame(1, $task1->sequence);
        $this->assertSame(2, $task2->sequence);
    }

    public function test_store_build_task_rejects_empty_title(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->storeBuildTask($session, ['title' => '  ']);
    }

    public function test_build_task_editing_conflict_returns_null_for_valid_state(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
                'metadata_json' => ['build' => ['status' => 'ready']],
            ]);

        $result = $this->service->buildTaskEditingConflict($session);

        $this->assertNull($result);
    }

    public function test_build_task_editing_conflict_returns_error_for_wrong_phase(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_PLANNING,
                'phase' => InterrogationSession::PHASE_PLANNING,
            ]);

        $result = $this->service->buildTaskEditingConflict($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('build tasks phase', $result);
    }

    public function test_build_task_editing_conflict_returns_error_while_generating(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
                'metadata_json' => ['build' => ['status' => 'generating_tasks']],
            ]);

        $result = $this->service->buildTaskEditingConflict($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('still being generated', $result);
    }

    public function test_invalidate_build_task_approval_resets_approval_state(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'metadata_json' => [
                    'build' => [
                        'status' => 'ready',
                        'tasks_approved_at' => '2026-01-01T00:00:00Z',
                        'tasks_approved_by_user_id' => 1,
                    ],
                ],
            ]);

        $this->service->invalidateBuildTaskApproval($session);

        $session->refresh();
        $build = $this->service->buildMetadata($session);

        $this->assertSame('ready', $build['status']);
        $this->assertNull($build['tasks_approved_at']);
        $this->assertNull($build['tasks_approved_by_user_id']);
    }

    public function test_resequence_build_tasks_fills_gaps(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task A',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);
        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 5,
            'title' => 'Task B',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);
        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 10,
            'title' => 'Task C',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $this->service->resequenceBuildTasks($session);

        $tasks = $session->buildTasks()->ordered()->get();
        $this->assertSame(1, (int) $tasks[0]->sequence);
        $this->assertSame(2, (int) $tasks[1]->sequence);
        $this->assertSame(3, (int) $tasks[2]->sequence);
    }

    public function test_normalized_build_status_returns_idle_for_empty(): void
    {
        $this->assertSame('idle', $this->service->normalizedBuildStatus([]));
        $this->assertSame('idle', $this->service->normalizedBuildStatus(['status' => '']));
        $this->assertSame('running', $this->service->normalizedBuildStatus(['status' => 'running']));
    }

    public function test_transform_build_task_returns_expected_structure(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create();

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'description' => 'Test description',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'metadata_json' => ['source' => 'manual'],
        ]);

        $result = $this->service->transformBuildTask($task);

        $this->assertSame($task->id, $result['id']);
        $this->assertSame('Test Task', $result['title']);
        $this->assertSame('Test description', $result['description']);
        $this->assertSame('pending', $result['status']);
        $this->assertArrayHasKey('started_at', $result);
        $this->assertArrayHasKey('finished_at', $result);
    }

    public function test_normalize_clarification_excerpt_returns_null_for_non_string(): void
    {
        $this->assertNull($this->service->normalizeClarificationExcerpt(null));
        $this->assertNull($this->service->normalizeClarificationExcerpt(123));
        $this->assertNull($this->service->normalizeClarificationExcerpt(''));
    }

    public function test_normalize_clarification_excerpt_extracts_text_from_json(): void
    {
        $json = json_encode(['message' => 'Please clarify the database schema']);
        $result = $this->service->normalizeClarificationExcerpt($json);

        $this->assertSame('Please clarify the database schema', $result);
    }

    public function test_normalized_nullable_text_trims_and_nullifies(): void
    {
        $this->assertNull($this->service->normalizedNullableText(null));
        $this->assertNull($this->service->normalizedNullableText(''));
        $this->assertNull($this->service->normalizedNullableText('   '));
        $this->assertSame('hello', $this->service->normalizedNullableText('  hello  '));
    }
}
