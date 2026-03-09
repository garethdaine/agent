<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\GitOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class TaskDiffEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['agent.interrogation.max_active_sessions' => 50]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_task_diff_returns_unavailable_when_no_baseline_head(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1, 'metadata_json' => null]);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/diff")
            ->assertOk();

        $this->assertFalse($response->json('data.available'));
        $this->assertEmpty($response->json('data.files'));
    }

    public function test_task_diff_returns_unavailable_when_baseline_head_is_empty(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1, 'metadata_json' => ['git_baseline_head' => '']]);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/diff")
            ->assertOk();

        $this->assertFalse($response->json('data.available'));
        $this->assertEmpty($response->json('data.files'));
    }

    public function test_task_diff_calls_git_service_when_baseline_exists(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 1,
                'metadata_json' => ['git_baseline_head' => 'abc123'],
            ]);

        $mockDiff = [
            'available' => true,
            'files' => [
                [
                    'path' => 'src/Example.php',
                    'status' => 'modified',
                    'additions' => 5,
                    'deletions' => 2,
                    'hunks' => [],
                ],
            ],
        ];

        $gitService = Mockery::mock(GitOperationsService::class);
        $gitService->shouldReceive('getTaskDiff')
            ->once()
            ->with(base_path(), 'abc123', null)
            ->andReturn($mockDiff);

        $this->app->instance(GitOperationsService::class, $gitService);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/diff")
            ->assertOk();

        $this->assertTrue($response->json('data.available'));
        $this->assertCount(1, $response->json('data.files'));
        $this->assertSame('src/Example.php', $response->json('data.files.0.path'));
        $this->assertSame(5, $response->json('data.files.0.additions'));
        $this->assertSame(2, $response->json('data.files.0.deletions'));
    }

    public function test_task_diff_passes_worktree_path_when_present(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 1,
                'metadata_json' => [
                    'git_baseline_head' => 'abc123',
                    'git_worktree_path' => '/tmp/worktree',
                ],
            ]);

        $gitService = Mockery::mock(GitOperationsService::class);
        $gitService->shouldReceive('getTaskDiff')
            ->once()
            ->with(base_path(), 'abc123', '/tmp/worktree')
            ->andReturn(['available' => true, 'files' => []]);

        $this->app->instance(GitOperationsService::class, $gitService);

        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/diff")
            ->assertOk();
    }

    public function test_task_diff_returns_404_for_nonexistent_task(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            ]);

        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/99999/diff")
            ->assertStatus(404);
    }

    public function test_task_diff_returns_404_for_other_users_session(): void
    {
        $otherUser = User::factory()->create();
        $session = InterrogationSession::factory()
            ->for($otherUser)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 1]);

        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/diff")
            ->assertStatus(404);
    }

    public function test_artefact_content_returns_file_content(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1]);

        // Use a known file within the project
        $response = $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path=composer.json"
        )->assertOk();

        $this->assertNotEmpty($response->json('data.content'));
        $this->assertSame('composer.json', $response->json('data.path'));
        $this->assertFalse($response->json('data.truncated'));
        $this->assertGreaterThan(0, $response->json('data.size'));
    }

    public function test_artefact_content_returns_422_when_path_missing(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 1]);

        $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content"
        )->assertStatus(422);
    }

    public function test_artefact_content_rejects_path_traversal(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 1]);

        $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path=../../etc/passwd"
        )->assertStatus(422);
    }

    public function test_artefact_content_returns_error_for_nonexistent_file(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 1]);

        // realpath() returns false for nonexistent paths, so the endpoint
        // reports it as outside the project directory (422)
        $response = $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path=nonexistent_file_that_does_not_exist.txt"
        );

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_artefact_content_returns_404_for_other_users_session(): void
    {
        $otherUser = User::factory()->create();
        $session = InterrogationSession::factory()
            ->for($otherUser)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->create(['sequence' => 1]);

        $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path=composer.json"
        )->assertStatus(404);
    }
}
