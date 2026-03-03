<?php

declare(strict_types=1);

namespace App\Jobs\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\RepoAnalysis\Analyzers\AnalyzerRegistry;
use App\Support\RepoAnalysis\AiTaskRunner;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\RepoAnalysisExecutionOrchestrator;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use App\Support\RepoAnalysis\SnapshotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\TimeoutExceededException;
use Throwable;

class ExecuteRepoAnalysisTaskJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $sessionId,
        public bool $dispatchNext = true,
    ) {
        $this->timeout = max(60, (int) config('repo_analysis.ai.task_timeout_seconds', 1200));
        $this->onConnection(config('repo_analysis.queue.connection', 'redis'));
        $this->onQueue(config('repo_analysis.queue.name', 'code-analysis'));
    }

    public function handle(
        RepoAnalysisExecutionOrchestrator $orchestrator,
        SessionStateTransitionService $transitions,
        AnalyzerRegistry $analyzers,
        SnapshotBuilder $snapshotBuilder,
        AiTaskRunner $aiTaskRunner,
    ): void {
        $orchestrator->assertQueue($this->queue);

        $session = RepoAnalysisSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        if ((int) $session->phase < 3) {
            return;
        }

        if ((string) $session->status === SessionStateTransitionService::STATUS_PAUSED) {
            $decision = $orchestrator->operatorDecision($session, 'drift_decision');
            if ($decision !== 'continue_old_snapshot') {
                return;
            }

            $transitions->resume($session->id);
            $session->refresh();
        }

        if ((int) $session->phase === 3 && (string) $session->status === SessionStateTransitionService::STATUS_FAILED) {
            $transitions->retry($session->id);
            $session->refresh();
        }

        if ((int) $session->phase !== 3 || (string) $session->status !== SessionStateTransitionService::STATUS_EXECUTING) {
            return;
        }

        if ($this->pauseForDriftIfNeeded($session, $orchestrator, $transitions, $snapshotBuilder)) {
            return;
        }

        $this->recoverStaleRunningTasks($session);

        $nextTask = $this->nextExecutableTask($session);
        if ($nextTask === null) {
            if ($session->tasks()->where('status', 'failed')->exists()) {
                return;
            }

            $transitions->transitionTo($session->id, 4, SessionStateTransitionService::STATUS_VALIDATING);

            if ($this->dispatchNext) {
                $orchestrator->dispatchValidate($session->id);
            }

            return;
        }

        $now = CarbonImmutable::now('UTC');
        $attempt = (int) $nextTask->attempt_count + 1;
        $nextTask->status = 'running';
        $nextTask->attempt_count = $attempt;
        $nextTask->started_at = $nextTask->started_at ?? $now;
        $nextTask->error_code = null;
        $nextTask->error_summary = null;
        $nextTask->save();

        $writer = new EventWriter($session);
        $writer->append('task_started', [
            'task_key' => (string) $nextTask->task_key,
            'analyzer' => (string) $nextTask->analyzer_name,
            'attempt' => $attempt,
        ], [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_EXECUTING,
        ]);

        $directive = $orchestrator->consumeFailureDirective($nextTask);
        if ($directive === 'retryable') {
            $this->handleRetryableFailure($session, $nextTask, $attempt, $transitions, $orchestrator, $writer);

            return;
        }

        if ($directive === 'non_retryable') {
            $this->handleNonRetryableFailure(
                $session,
                $nextTask,
                'Simulated non-retryable failure for deterministic pipeline validation.',
                $transitions,
                $orchestrator,
                $writer,
            );

            return;
        }

        try {
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $snapshot = $orchestrator->snapshot($metadata);
            if ($snapshot === []) {
                throw new \RuntimeException('Snapshot payload missing from session metadata.');
            }

            $taskType = (string) $nextTask->task_type;
            if ($taskType === 'ai_analysis') {
                $result = $aiTaskRunner->run($session, $nextTask, $snapshot, $writer);
            } else {
                $analyzer = $analyzers->get((string) $nextTask->analyzer_name);
                $result = $analyzer->analyze($snapshot);
            }

            $artifact = RepoAnalysisArtifact::query()->updateOrCreate(
                [
                    'repo_analysis_session_id' => $session->id,
                    'artifact_key' => sprintf('%s.%s', $nextTask->task_key, (string) $result['artifact_key']),
                ],
                [
                    'repo_analysis_task_id' => $nextTask->id,
                    'artifact_type' => (string) $result['artifact_type'],
                    'content_hash' => (string) $result['output_hash'],
                    'schema_version' => '1.0.0',
                    'analyzer_version' => (string) $result['analyzer_version'],
                    'payload_json' => [
                        'payload' => $result['payload'] ?? [],
                        'warnings' => $result['warnings'] ?? [],
                        'warning_artifact_path' => $result['warning_artifact_path'] ?? null,
                    ],
                    'metadata_json' => [
                        'task_key' => (string) $nextTask->task_key,
                    ],
                    'error_code' => null,
                    'error_summary' => null,
                ]
            );

            $taskMetadata = is_array($nextTask->metadata_json) ? $nextTask->metadata_json : [];
            unset($taskMetadata['operator_action_required']);

            $nextTask->status = 'completed';
            $nextTask->output_hash = (string) $result['output_hash'];
            $nextTask->artifact_ids_json = [$artifact->id];
            $nextTask->error_code = null;
            $nextTask->error_summary = null;
            $nextTask->finished_at = CarbonImmutable::now('UTC');
            $nextTask->metadata_json = $taskMetadata;
            $nextTask->save();

            if ($taskType === 'ai_analysis' && is_string($result['cli_session_id'] ?? null) && $result['cli_session_id'] !== '') {
                $sessionMetadata = is_array($session->metadata_json) ? $session->metadata_json : [];
                $sessionMetadata['ai_cli_session_id'] = $result['cli_session_id'];
                $session->metadata_json = $sessionMetadata;
                $session->save();
            }

            $writer->append('task_completed', [
                'task_key' => (string) $nextTask->task_key,
                'analyzer' => (string) $nextTask->analyzer_name,
                'task_type' => (string) $nextTask->task_type,
                'attempt' => $attempt,
                'output_hash' => (string) $result['output_hash'],
            ], [
                'phase' => 3,
                'status' => SessionStateTransitionService::STATUS_EXECUTING,
            ]);

            if ($this->dispatchNext) {
                $orchestrator->dispatchExecute($session->id);
            }
        } catch (Throwable $throwable) {
            $this->handleNonRetryableFailure(
                $session,
                $nextTask,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Analyzer execution failed.',
                $transitions,
                $orchestrator,
                $writer,
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $session = RepoAnalysisSession::query()->find($this->sessionId);
        if (! $session instanceof RepoAnalysisSession) {
            return;
        }

        $task = $this->latestRunningTask($session);
        $errorCode = $this->queueFailureCode($exception);
        $errorSummary = $this->queueFailureSummary($exception, $task);

        if ($task instanceof RepoAnalysisTask) {
            $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
            $metadata['queue_failure'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'failed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            $task->status = 'failed';
            $task->attempt_count = max(1, (int) $task->attempt_count);
            $task->error_code = $errorCode;
            $task->error_summary = $errorSummary;
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->metadata_json = $metadata;
            $task->save();
        }

        $orchestrator = app(RepoAnalysisExecutionOrchestrator::class);
        $decisionDetails = [
            'reason' => strtolower($errorCode),
            'exception_class' => $exception::class,
        ];

        if ($task instanceof RepoAnalysisTask) {
            $decisionDetails['task_key'] = (string) $task->task_key;
        }

        $orchestrator->markOperatorDecisionRequired($session, 'task_retry_decision_required', $decisionDetails);
        $session->refresh();

        $transitions = app(SessionStateTransitionService::class);
        $transitioned = false;

        if ((int) $session->phase === 3 && (string) $session->status === SessionStateTransitionService::STATUS_EXECUTING) {
            $transitioned = $transitions->transitionTo($session->id, 3, SessionStateTransitionService::STATUS_FAILED, [
                'error_code' => $errorCode,
                'error_summary' => $errorSummary,
                'metadata_json' => is_array($session->metadata_json) ? $session->metadata_json : [],
            ]);
        }

        if (! $transitioned) {
            RepoAnalysisSession::query()
                ->whereKey($session->id)
                ->update([
                    'error_code' => $errorCode,
                    'error_summary' => $errorSummary,
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]);
        }

        $session->refresh();

        $writer = new EventWriter($session);
        $writer->append('task_failed', [
            'task_key' => $task?->task_key,
            'failure_class' => $exception::class,
            'failure_message' => $exception->getMessage(),
            'timed_out' => $exception instanceof TimeoutExceededException,
        ], [
            'phase' => (int) $session->phase,
            'status' => (string) $session->status,
            'error_code' => $errorCode,
            'error_summary' => $errorSummary,
        ]);
    }

    private function pauseForDriftIfNeeded(
        RepoAnalysisSession $session,
        RepoAnalysisExecutionOrchestrator $orchestrator,
        SessionStateTransitionService $transitions,
        SnapshotBuilder $snapshotBuilder,
    ): bool {
        $snapshotHash = (string) $session->snapshot_hash;
        if ($snapshotHash === '') {
            return false;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $decision = $orchestrator->operatorDecision($session, 'drift_decision');
        if ($decision === 'continue_old_snapshot') {
            return false;
        }

        $currentSnapshot = $snapshotBuilder->build((string) $session->project_directory);
        $currentHash = (string) $currentSnapshot['snapshot_hash'];

        if ($currentHash === $snapshotHash) {
            return false;
        }

        $metadata['operator_action_required'] = 'drift_decision_required';
        $metadata['operator_action_details'] = [
            'session_snapshot_hash' => $snapshotHash,
            'current_snapshot_hash' => $currentHash,
            'choices' => ['continue_old_snapshot', 'restart'],
        ];

        $transitions->pause($session->id, [
            'metadata_json' => $metadata,
            'error_code' => 'SNAPSHOT_DRIFT_DETECTED',
            'error_summary' => 'Repository changed after snapshot. Operator decision is required.',
        ]);

        $session->refresh();

        $writer = new EventWriter($session);
        $writer->append('snapshot_drift_detected', [
            'session_snapshot_hash' => $snapshotHash,
            'current_snapshot_hash' => $currentHash,
            'operator_action_required' => 'drift_decision_required',
        ], [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_PAUSED,
            'error_code' => 'SNAPSHOT_DRIFT_DETECTED',
            'error_summary' => 'Repository changed after snapshot. Operator decision is required.',
        ]);

        return true;
    }

    private function recoverStaleRunningTasks(RepoAnalysisSession $session): void
    {
        $staleThreshold = CarbonImmutable::now('UTC')->subMinutes(5);

        $session->tasks()
            ->where('status', 'running')
            ->where('started_at', '<=', $staleThreshold)
            ->get()
            ->each(function (RepoAnalysisTask $task): void {
                $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
                $metadata['stale_recovered_at'] = CarbonImmutable::now('UTC')->toIso8601String();

                $task->status = 'pending';
                $task->metadata_json = $metadata;
                $task->save();
            });
    }

    private function nextExecutableTask(RepoAnalysisSession $session): ?RepoAnalysisTask
    {
        $pendingTasks = $session->tasks()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        if ($pendingTasks->isEmpty()) {
            return null;
        }

        $tasksByAnalyzer = $session->tasks()->get()->keyBy('analyzer_name');

        return $pendingTasks->first(function (RepoAnalysisTask $task) use ($tasksByAnalyzer): bool {
            $dependencies = is_array($task->depends_on_json) ? $task->depends_on_json : [];

            foreach ($dependencies as $dependency) {
                if (! is_string($dependency) || $dependency === '') {
                    continue;
                }

                $dependencyTask = $tasksByAnalyzer->get($dependency);
                if (! $dependencyTask instanceof RepoAnalysisTask) {
                    return false;
                }

                if ((string) $dependencyTask->status !== 'completed') {
                    return false;
                }
            }

            return true;
        });
    }

    private function handleRetryableFailure(
        RepoAnalysisSession $session,
        RepoAnalysisTask $task,
        int $attempt,
        SessionStateTransitionService $transitions,
        RepoAnalysisExecutionOrchestrator $orchestrator,
        EventWriter $writer,
    ): void {
        if ($attempt < min((int) $task->max_attempts, 2)) {
            $task->status = 'pending';
            $task->error_code = 'retryable_failure';
            $task->error_summary = 'Retryable analyzer failure. Auto-retrying once by policy.';
            $task->save();

            $writer->append('task_retry_scheduled', [
                'task_key' => (string) $task->task_key,
                'attempt' => $attempt,
            ], [
                'phase' => 3,
                'status' => SessionStateTransitionService::STATUS_EXECUTING,
            ]);

            if ($this->dispatchNext) {
                $orchestrator->dispatchExecute($session->id);
            }

            return;
        }

        $task->status = 'failed';
        $task->error_code = 'retryable_failure';
        $task->error_summary = 'Retryable analyzer failure exceeded single auto-retry policy.';
        $task->finished_at = CarbonImmutable::now('UTC');
        $task->save();

        $orchestrator->markOperatorDecisionRequired($session, 'task_retry_decision_required', [
            'task_key' => (string) $task->task_key,
            'reason' => 'retry_exhausted',
        ]);

        $session->refresh();
        $transitions->pause($session->id, [
            'error_code' => 'EXECUTE_TASK_RETRY_EXHAUSTED',
            'error_summary' => 'Retryable task failed after one auto-retry. Operator action required.',
            'metadata_json' => $session->metadata_json,
        ]);

        $session->refresh();

        $writer = new EventWriter($session);
        $writer->append('task_failed', [
            'task_key' => (string) $task->task_key,
            'attempt' => $attempt,
            'failure_class' => 'retryable_exhausted',
        ], [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_PAUSED,
            'error_code' => 'EXECUTE_TASK_RETRY_EXHAUSTED',
        ]);
    }

    private function handleNonRetryableFailure(
        RepoAnalysisSession $session,
        RepoAnalysisTask $task,
        string $message,
        SessionStateTransitionService $transitions,
        RepoAnalysisExecutionOrchestrator $orchestrator,
        EventWriter $writer,
    ): void {
        $task->status = 'failed';
        $task->error_code = 'non_retryable_failure';
        $task->error_summary = $message;
        $task->finished_at = CarbonImmutable::now('UTC');
        $task->save();

        $orchestrator->markOperatorDecisionRequired($session, 'task_retry_decision_required', [
            'task_key' => (string) $task->task_key,
            'reason' => 'non_retryable_failure',
        ]);

        $session->refresh();

        $transitions->pause($session->id, [
            'error_code' => 'EXECUTE_TASK_NON_RETRYABLE',
            'error_summary' => 'Non-retryable task failure paused execution.',
            'metadata_json' => $session->metadata_json,
        ]);

        $session->refresh();

        $writer = new EventWriter($session);
        $writer->append('task_failed', [
            'task_key' => (string) $task->task_key,
            'failure_class' => 'non_retryable',
        ], [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_PAUSED,
            'error_code' => 'EXECUTE_TASK_NON_RETRYABLE',
            'error_summary' => 'Non-retryable task failure paused execution.',
        ]);
    }

    private function latestRunningTask(RepoAnalysisSession $session): ?RepoAnalysisTask
    {
        return RepoAnalysisTask::query()
            ->where('repo_analysis_session_id', $session->id)
            ->where('status', 'running')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    private function queueFailureCode(Throwable $exception): string
    {
        if ($exception instanceof TimeoutExceededException) {
            return 'EXECUTE_TASK_TIMEOUT';
        }

        return 'EXECUTE_TASK_JOB_FAILED';
    }

    private function queueFailureSummary(Throwable $exception, ?RepoAnalysisTask $task): string
    {
        if ($exception instanceof TimeoutExceededException) {
            $taskLabel = $task instanceof RepoAnalysisTask
                ? sprintf('Task %s timed out', (string) $task->task_key)
                : 'Task execution timed out';

            return sprintf('%s after %d seconds. Retry the failed task to continue.', $taskLabel, $this->timeout);
        }

        $message = trim($exception->getMessage());
        if ($message !== '') {
            return $message;
        }

        return 'Code analysis task execution failed due to an unexpected queue worker error.';
    }
}
