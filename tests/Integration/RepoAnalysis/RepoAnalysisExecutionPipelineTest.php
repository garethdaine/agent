<?php

declare(strict_types=1);

namespace Tests\Integration\RepoAnalysis;

use App\Jobs\RepoAnalysis\ExecuteRepoAnalysisTaskJob;
use App\Jobs\RepoAnalysis\GenerateRepoAnalysisReportJob;
use App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob;
use App\Jobs\RepoAnalysis\PlanRepoAnalysisTasksJob;
use App\Jobs\RepoAnalysis\ValidateRepoAnalysisCoverageJob;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RepoAnalysisExecutionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = storage_path('framework/testing/repo-analysis-pipeline-'.(string) str()->uuid());
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

    private function createSession(array $metadata = []): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Repo Analysis Pipeline Test',
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
        File::put($root.'/composer.json', '{"name":"acme/repo-analysis"}');
        File::put($root.'/package.json', '{"name":"repo-analysis-app"}');

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
