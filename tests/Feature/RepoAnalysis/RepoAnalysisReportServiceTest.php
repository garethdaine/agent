<?php

declare(strict_types=1);

namespace Tests\Feature\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Models\User;
use App\Services\RepoAnalysis\RepoAnalysisReportService;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepoAnalysisReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private RepoAnalysisReportService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
        $this->service = new RepoAnalysisReportService;
        $this->user = User::factory()->create();
    }

    public function test_validate_coverage_succeeds_in_phase_4(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 4]);
        $this->assertNull($this->service->validateCoverage($session));
    }

    public function test_validate_coverage_rejects_wrong_phase(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 3]);
        $result = $this->service->validateCoverage($session);
        $this->assertNotNull($result);
        $this->assertSame(409, $result->getStatusCode());
    }

    public function test_generate_report_succeeds_in_phase_5(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 5]);
        $this->assertNull($this->service->generateReport($session));
    }

    public function test_generate_report_rejects_wrong_phase(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 4]);
        $result = $this->service->generateReport($session);
        $this->assertNotNull($result);
    }

    public function test_is_path_within_roots(): void
    {
        $this->assertTrue($this->service->isPathWithinRoots('/home/user/project/file.txt', ['/home/user/project']));
        $this->assertFalse($this->service->isPathWithinRoots('/etc/passwd', ['/home/user/project']));
    }

    public function test_is_path_within_roots_exact_root(): void
    {
        $this->assertTrue($this->service->isPathWithinRoots('/home/user/project', ['/home/user/project']));
    }

    public function test_is_path_within_roots_empty_root_skipped(): void
    {
        $this->assertFalse($this->service->isPathWithinRoots('/home/user/file.txt', ['']));
    }

    public function test_resolved_project_root_returns_null_for_empty(): void
    {
        $this->assertNull($this->service->resolvedProjectRoot(''));
    }

    public function test_resolved_project_root_returns_null_for_relative(): void
    {
        $this->assertNull($this->service->resolvedProjectRoot('relative/path'));
    }

    public function test_resolved_project_root_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->service->resolvedProjectRoot('/definitely/does/not/exist/path'));
    }

    public function test_resolved_project_root_resolves_valid_directory(): void
    {
        $result = $this->service->resolvedProjectRoot('/tmp');
        $this->assertNotNull($result);
        $this->assertStringStartsWith('/', $result);
    }

    public function test_transform_session(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'phase' => 3,
            'status' => 'executing',
        ]);

        $result = $this->service->transformSession($session);
        $this->assertSame((int) $session->id, $result['id']);
        $this->assertSame(3, $result['phase']);
        $this->assertSame('executing', $result['status']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('manifest_stats', $result);
    }

    public function test_reconcile_stale_running_tasks_ignores_active_sessions(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'status' => SessionStateTransitionService::STATUS_EXECUTING,
        ]);

        RepoAnalysisTask::factory()->for($session, 'session')->create(['status' => 'running']);

        $this->service->reconcileStaleRunningTasks($session);

        $this->assertSame('running', RepoAnalysisTask::query()->first()->status);
    }

    public function test_reconcile_stale_running_tasks_marks_failed_on_completed_session(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'status' => SessionStateTransitionService::STATUS_COMPLETED,
            'phase' => 3,
        ]);

        $task = RepoAnalysisTask::factory()->for($session, 'session')->create([
            'status' => 'running',
            'task_key' => 'test-task',
        ]);

        $this->service->reconcileStaleRunningTasks($session);

        $task->refresh();
        $this->assertSame('failed', (string) $task->status);
        $this->assertNotNull($task->error_code);
    }

    public function test_delete_path_if_allowed_rejects_empty(): void
    {
        $this->assertFalse($this->service->deletePathIfAllowed('', ['/tmp']));
    }

    public function test_delete_path_if_allowed_rejects_relative(): void
    {
        $this->assertFalse($this->service->deletePathIfAllowed('relative/path', ['/tmp']));
    }

    public function test_delete_path_if_allowed_rejects_outside_roots(): void
    {
        $this->assertFalse($this->service->deletePathIfAllowed('/etc/passwd', ['/tmp']));
    }

    public function test_delete_path_if_allowed_rejects_root_itself(): void
    {
        $this->assertFalse($this->service->deletePathIfAllowed('/tmp', ['/tmp']));
    }

    public function test_collect_session_file_paths_empty_session(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create();
        $paths = $this->service->collectSessionFilePaths($session);
        $this->assertIsArray($paths);
    }
}
