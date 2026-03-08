<?php

declare(strict_types=1);

namespace Tests\Integration\RepoAnalysis;

use App\Jobs\RepoAnalysis\ExecuteRepoAnalysisTaskJob;
use App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob;
use App\Jobs\RepoAnalysis\PlanRepoAnalysisTasksJob;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\User;
use App\Support\RepoAnalysis\AiTaskRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RepoAnalysisExecutionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = storage_path('framework/testing/code-analysis-pipeline-'.(string) str()->uuid());
        File::deleteDirectory($this->repoRoot);
        File::ensureDirectoryExists($this->repoRoot);

        $this->seedRepositoryFixture($this->repoRoot);
    }

    public function test_pipeline_progresses_to_completion_with_report_and_phase_updates(): void
    {
        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id);

        $session->refresh();

        $this->assertSame(6, (int) $session->phase);
        $this->assertSame('completed', (string) $session->status);
        $this->assertNull($session->error_code);

        $this->assertGreaterThan(0, $session->tasks()->count());
        $this->assertSame(0, $session->tasks()->where('status', '!=', 'completed')->count());

        $this->assertTrue($session->reports()->exists());
        $report = $session->reports()->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertSame('generated', (string) $report->status);
        $this->assertNotEmpty((string) $report->report_hash);
    }

    public function test_retryable_failure_is_retried_once_then_session_pauses_on_second_failure(): void
    {
        $session = $this->createSession([
            'test_failure_scripts' => [
                'dependency_manifest' => ['retryable', 'retryable'],
            ],
        ]);

        GenerateRepoSnapshotJob::dispatchSync($session->id);

        $session->refresh();
        $task = $session->tasks()->where('analyzer_name', 'dependency_manifest')->firstOrFail();

        $this->assertSame(3, (int) $session->phase);
        $this->assertSame('paused', (string) $session->status);
        $this->assertSame('EXECUTE_TASK_RETRY_EXHAUSTED', (string) $session->error_code);

        $this->assertSame('failed', (string) $task->status);
        $this->assertSame(2, (int) $task->attempt_count);

        $this->assertGreaterThanOrEqual(
            1,
            $session->events()->where('event_type', 'task_retry_scheduled')->count()
        );
    }

    public function test_non_retryable_failure_short_circuits_without_auto_retry(): void
    {
        $session = $this->createSession([
            'test_failure_scripts' => [
                'filesystem_manifest' => ['non_retryable'],
            ],
        ]);

        GenerateRepoSnapshotJob::dispatchSync($session->id);

        $session->refresh();
        $task = $session->tasks()->where('analyzer_name', 'filesystem_manifest')->firstOrFail();

        $this->assertSame(3, (int) $session->phase);
        $this->assertSame('paused', (string) $session->status);
        $this->assertSame('EXECUTE_TASK_NON_RETRYABLE', (string) $session->error_code);

        $this->assertSame('failed', (string) $task->status);
        $this->assertSame(1, (int) $task->attempt_count);
        $this->assertSame(0, $session->events()->where('event_type', 'task_retry_scheduled')->count());
    }

    public function test_resume_reuses_only_matching_completed_outputs_by_input_hash_and_analyzer_version(): void
    {
        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id, false);
        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        $matchingTask = $session->tasks()->where('analyzer_name', 'filesystem_manifest')->firstOrFail();
        $mismatchTask = $session->tasks()->where('analyzer_name', 'dependency_manifest')->firstOrFail();

        $matchingTask->update([
            'status' => 'completed',
            'attempt_count' => 1,
            'output_hash' => hash('sha256', 'existing-output-match'),
            'finished_at' => CarbonImmutable::now('UTC'),
        ]);

        $mismatchTask->update([
            'status' => 'completed',
            'attempt_count' => 1,
            'input_hash' => hash('sha256', 'stale-input-hash'),
            'output_hash' => hash('sha256', 'stale-output-hash'),
            'finished_at' => CarbonImmutable::now('UTC'),
        ]);

        $session->update([
            'phase' => 3,
            'status' => 'paused',
        ]);

        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        $matchingTask->refresh();
        $mismatchTask->refresh();

        $this->assertSame('completed', (string) $matchingTask->status);
        $this->assertTrue((bool) data_get($matchingTask->metadata_json, 'reused_output'));

        $this->assertSame('pending', (string) $mismatchTask->status);
        $this->assertSame(0, (int) $mismatchTask->attempt_count);
        $this->assertNull($mismatchTask->output_hash);
        $this->assertFalse((bool) data_get($mismatchTask->metadata_json, 'reused_output'));
    }

    public function test_snapshot_drift_pauses_execution_and_requires_operator_decision(): void
    {
        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id, false);
        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        File::put($this->repoRoot.'/app/Jobs/NewDriftedJob.php', "<?php\n");

        ExecuteRepoAnalysisTaskJob::dispatchSync($session->id);

        $session->refresh();

        $this->assertSame(3, (int) $session->phase);
        $this->assertSame('paused', (string) $session->status);
        $this->assertSame('SNAPSHOT_DRIFT_DETECTED', (string) $session->error_code);
        $this->assertSame('drift_decision_required', data_get($session->metadata_json, 'operator_action_required'));
    }

    public function test_snapshot_drift_is_ignored_when_changes_are_limited_to_tolerated_paths(): void
    {
        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id, false);
        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        File::ensureDirectoryExists($this->repoRoot.'/tasks');
        File::put($this->repoRoot.'/tasks/runtime-note.md', "# Generated during run\n");
        File::ensureDirectoryExists($this->repoRoot.'/docs');
        File::put($this->repoRoot.'/docs/runtime-output.md', "# Generated report artifact\n");

        ExecuteRepoAnalysisTaskJob::dispatchSync($session->id, false);

        $session->refresh();

        $this->assertNotSame('SNAPSHOT_DRIFT_DETECTED', (string) $session->error_code);
        $this->assertNotSame('drift_decision_required', data_get($session->metadata_json, 'operator_action_required'));
        $this->assertNotSame('paused', (string) $session->status);
    }

    public function test_replay_is_idempotent_stale_task_state_is_recovered_and_queue_misrouting_is_rejected(): void
    {
        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id, false);
        GenerateRepoSnapshotJob::dispatchSync($session->id, false);

        $this->assertSame(
            1,
            RepoAnalysisArtifact::query()
                ->where('repo_analysis_session_id', $session->id)
                ->where('artifact_key', 'snapshot.manifest.json')
                ->count()
        );

        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        $staleTask = $session->tasks()->where('status', 'pending')->orderBy('id')->firstOrFail();
        $staleTask->update([
            'status' => 'running',
            'started_at' => CarbonImmutable::now('UTC')->subMinutes(20),
            'attempt_count' => 1,
        ]);

        ExecuteRepoAnalysisTaskJob::dispatchSync($session->id);

        $staleTask->refresh();
        $this->assertNotSame('running', (string) $staleTask->status);

        $misroutedSession = $this->createSession(['force_queue_name' => 'default']);
        $misroutedJob = new GenerateRepoSnapshotJob($misroutedSession->id, false);
        $misroutedJob->onQueue('default');

        $this->expectException(\RuntimeException::class);
        app()->call([$misroutedJob, 'handle']);
    }

    public function test_timed_out_execute_job_marks_session_and_task_failed_and_emits_failure_event(): void
    {
        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id, false);
        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        $task = $session->tasks()->where('status', 'pending')->orderBy('id')->firstOrFail();
        $task->update([
            'status' => 'running',
            'attempt_count' => 1,
            'started_at' => CarbonImmutable::now('UTC')->subMinutes(30),
        ]);

        $session->update([
            'phase' => 3,
            'status' => 'executing',
            'error_code' => null,
            'error_summary' => null,
        ]);

        $job = new ExecuteRepoAnalysisTaskJob($session->id, false);
        $job->failed(new TimeoutExceededException('ExecuteRepoAnalysisTaskJob has timed out.'));

        $session->refresh();
        $task->refresh();

        $this->assertSame('failed', (string) $session->status);
        $this->assertSame('EXECUTE_TASK_TIMEOUT', (string) $session->error_code);
        $this->assertStringContainsString('timed out', (string) $session->error_summary);
        $this->assertSame('task_retry_decision_required', data_get($session->metadata_json, 'operator_action_required'));

        $this->assertSame('failed', (string) $task->status);
        $this->assertSame('EXECUTE_TASK_TIMEOUT', (string) $task->error_code);
        $this->assertStringContainsString((string) $task->task_key, (string) $task->error_summary);
        $this->assertNotNull($task->finished_at);

        $failureEvent = $session->events()->where('event_type', 'task_failed')->latest('id')->first();
        $this->assertNotNull($failureEvent);
        $this->assertSame('EXECUTE_TASK_TIMEOUT', (string) $failureEvent->error_code);
        $this->assertSame('failed', (string) $failureEvent->status);
        $this->assertTrue((bool) data_get($failureEvent->payload_json, 'timed_out'));
    }

    public function test_execute_job_timeout_uses_queue_timeout_with_ai_buffer_floor(): void
    {
        config()->set('repo_analysis.ai.task_timeout_seconds', 1800);
        config()->set('repo_analysis.ai.queue_timeout_buffer_seconds', 120);
        config()->set('repo_analysis.queue.supervisor.timeout_seconds', 0);

        $jobUsingBuffer = new ExecuteRepoAnalysisTaskJob(123, false);
        $this->assertSame(1920, $jobUsingBuffer->timeout);

        config()->set('repo_analysis.queue.supervisor.timeout_seconds', 2400);

        $jobUsingSupervisorTimeout = new ExecuteRepoAnalysisTaskJob(123, false);
        $this->assertSame(2400, $jobUsingSupervisorTimeout->timeout);
    }

    public function test_process_level_timeout_is_mapped_to_execute_task_timeout_state(): void
    {
        config()->set('repo_analysis.ai.enabled', true);

        $session = $this->createSession();

        GenerateRepoSnapshotJob::dispatchSync($session->id, false);
        PlanRepoAnalysisTasksJob::dispatchSync($session->id, false);

        $session->tasks()->where('task_type', '!=', 'ai_analysis')->update([
            'status' => 'completed',
            'attempt_count' => 1,
            'finished_at' => CarbonImmutable::now('UTC'),
        ]);

        $aiTask = $session->tasks()->where('task_type', 'ai_analysis')->orderBy('id')->firstOrFail();
        $aiTask->update([
            'status' => 'pending',
            'attempt_count' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error_code' => null,
            'error_summary' => null,
        ]);

        $mockRunner = \Mockery::mock(AiTaskRunner::class);
        $mockRunner->shouldReceive('run')
            ->once()
            ->andThrow(new ProcessTimedOutException(new Process(['php', '-r', 'echo "timeout";']), ProcessTimedOutException::TYPE_GENERAL));
        $this->app->instance(AiTaskRunner::class, $mockRunner);

        ExecuteRepoAnalysisTaskJob::dispatchSync($session->id, false);

        $session->refresh();
        $aiTask->refresh();

        $this->assertSame('failed', (string) $session->status);
        $this->assertSame('EXECUTE_TASK_TIMEOUT', (string) $session->error_code);
        $this->assertStringContainsString('timed out', (string) $session->error_summary);

        $this->assertSame('failed', (string) $aiTask->status);
        $this->assertSame('EXECUTE_TASK_TIMEOUT', (string) $aiTask->error_code);
        $this->assertNotNull($aiTask->finished_at);
    }

    private function createSession(array $metadata = []): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Code Analysis Pipeline Test',
            'project_directory' => $this->repoRoot,
            'analyzer_profile' => 'default',
            'phase' => 0,
            'status' => 'setup',
            'metadata_json' => $metadata,
        ]);
    }

    private function seedRepositoryFixture(string $root): void
    {
        File::put($root.'/artisan', "#!/usr/bin/env php\n");
        File::put($root.'/composer.json', '{"name":"acme/code-analysis"}');
        File::put($root.'/package.json', '{"name":"code-analysis-app"}');

        File::ensureDirectoryExists($root.'/routes');
        File::put($root.'/routes/web.php', "<?php\n");

        File::ensureDirectoryExists($root.'/app/Models');
        File::put($root.'/app/Models/User.php', "<?php\n");

        File::ensureDirectoryExists($root.'/app/Jobs');
        File::put($root.'/app/Jobs/ExampleJob.php', "<?php\n");

        File::ensureDirectoryExists($root.'/app/Events');
        File::put($root.'/app/Events/ExampleEvent.php', "<?php\n");

        File::ensureDirectoryExists($root.'/database/migrations');
        File::put($root.'/database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n");

        File::ensureDirectoryExists($root.'/resources/js');
        File::put($root.'/resources/js/app.js', 'export {}');

        File::ensureDirectoryExists($root.'/tests/Feature');
        File::put($root.'/tests/Feature/SmokeTest.php', "<?php\n");
    }
}
