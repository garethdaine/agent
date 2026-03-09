<?php

declare(strict_types=1);

namespace App\Services\RepoAnalysis;

use App\Jobs\RepoAnalysis\ExecuteRepoAnalysisTaskJob;
use App\Jobs\RepoAnalysis\GenerateRepoAnalysisReportJob;
use App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob;
use App\Jobs\RepoAnalysis\PlanRepoAnalysisTasksJob;
use App\Jobs\RepoAnalysis\ValidateRepoAnalysisCoverageJob;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\Agent\ErrorEnvelope;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Http\JsonResponse;

class RepoAnalysisWorkflowService
{
    public function startSnapshot(RepoAnalysisSession $session): ?JsonResponse
    {
        if ((int) $session->phase !== 0 || (string) $session->status !== SessionStateTransitionService::STATUS_SETUP) {
            return ErrorEnvelope::make(
                'RUN_TRANSITION_CONFLICT',
                'Snapshot can only be started from setup phase.',
                409
            );
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        unset($metadata['auto_start_on_open']);
        $session->metadata_json = $metadata;
        $session->save();

        GenerateRepoSnapshotJob::dispatch($session->id);

        return null;
    }

    public function plan(RepoAnalysisSession $session): ?JsonResponse
    {
        if ((int) $session->phase !== 2) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Planning is only available in phase 2.', 409);
        }

        PlanRepoAnalysisTasksJob::dispatch($session->id);

        return null;
    }

    public function execute(RepoAnalysisSession $session): ?JsonResponse
    {
        if ((int) $session->phase !== 3) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Execution is only available in phase 3.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        ExecuteRepoAnalysisTaskJob::dispatch($session->id);

        return null;
    }

    public function retryTask(
        RepoAnalysisSession $session,
        int $taskId,
        SessionStateTransitionService $transitions,
    ): JsonResponse|RepoAnalysisTask {
        $task = RepoAnalysisTask::query()
            ->where('repo_analysis_session_id', $session->id)
            ->findOrFail($taskId);

        if ((string) $task->status !== 'failed') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Only failed tasks can be retried.', 409);
        }

        $task->update([
            'status' => 'pending',
            'error_code' => null,
            'error_summary' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        if ((int) $session->phase === 3) {
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            if (($metadata['operator_action_required'] ?? null) === 'task_retry_decision_required') {
                unset($metadata['operator_action_required'], $metadata['operator_action_details']);
            }

            $transitionPayload = [
                'error_code' => null,
                'error_summary' => null,
                'metadata_json' => $metadata,
            ];

            if ((string) $session->status === SessionStateTransitionService::STATUS_PAUSED) {
                $resumed = $transitions->resume($session->id, $transitionPayload);
                if (! $resumed) {
                    return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot resume from its current state.', 409);
                }
                $session->refresh();
            } elseif ((string) $session->status === SessionStateTransitionService::STATUS_FAILED) {
                $retried = $transitions->retry($session->id, $transitionPayload);
                if (! $retried) {
                    return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot retry from its current state.', 409);
                }
                $session->refresh();
            }
        }

        ExecuteRepoAnalysisTaskJob::dispatch((int) $session->id);

        return $task;
    }

    public function pause(RepoAnalysisSession $session, SessionStateTransitionService $transitions): ?JsonResponse
    {
        $paused = $transitions->pause($session->id);

        if (! $paused) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be paused from its current state.', 409);
        }

        $session->refresh();

        return null;
    }

    public function resume(RepoAnalysisSession $session, SessionStateTransitionService $transitions): JsonResponse|bool
    {
        if ((string) $session->status !== SessionStateTransitionService::STATUS_PAUSED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be resumed from its current state.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        $transitionPayload = [
            'error_code' => null,
            'error_summary' => null,
        ];

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $requiresDriftDecision = (($metadata['operator_action_required'] ?? null) === 'drift_decision_required')
            || ((string) $session->error_code === 'SNAPSHOT_DRIFT_DETECTED');

        if ($requiresDriftDecision) {
            $metadata['drift_decision'] = 'continue_old_snapshot';
            unset($metadata['operator_action_required'], $metadata['operator_action_details']);
            $transitionPayload['metadata_json'] = $metadata;
        }

        $resumed = $transitions->resume($session->id, $transitionPayload);

        if (! $resumed) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while resuming.', 409);
        }

        $session->refresh();

        return $this->dispatchByPhase((int) $session->phase, (int) $session->id);
    }

    public function retry(RepoAnalysisSession $session, SessionStateTransitionService $transitions): JsonResponse|bool
    {
        if ((string) $session->status !== SessionStateTransitionService::STATUS_FAILED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be retried from its current state.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        $retried = $transitions->retry($session->id, [
            'error_code' => null,
            'error_summary' => null,
        ]);

        if (! $retried) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be retried from its current state.', 409);
        }

        $session->refresh();

        return $this->dispatchByPhase((int) $session->phase, (int) $session->id);
    }

    public function restartFromBeginning(RepoAnalysisSession $session): ?JsonResponse
    {
        if (in_array((string) $session->status, [
            SessionStateTransitionService::STATUS_SNAPSHOTTING,
            SessionStateTransitionService::STATUS_PLANNING,
            SessionStateTransitionService::STATUS_EXECUTING,
            SessionStateTransitionService::STATUS_VALIDATING,
            SessionStateTransitionService::STATUS_REPORTING,
        ], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot restart while running.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['restart_count'] = ((int) ($metadata['restart_count'] ?? 0)) + 1;
        unset($metadata['operator_action_required'], $metadata['operator_action_details'], $metadata['drift_decision']);

        $session->events()->delete();
        $session->tasks()->delete();
        $session->artifacts()->delete();
        $session->reports()->delete();

        $session->update([
            'phase' => 0,
            'status' => SessionStateTransitionService::STATUS_SETUP,
            'snapshot_hash' => null,
            'manifest_stats_json' => [],
            'report_summary_json' => [],
            'error_code' => null,
            'error_summary' => null,
            'started_at' => null,
            'finished_at' => null,
            'metadata_json' => $metadata,
        ]);

        GenerateRepoSnapshotJob::dispatch((int) $session->id);

        return null;
    }

    public function dispatchByPhase(int $phase, int $sessionId): bool
    {
        return match ($phase) {
            1 => (bool) GenerateRepoSnapshotJob::dispatch($sessionId),
            2 => (bool) PlanRepoAnalysisTasksJob::dispatch($sessionId),
            3 => (bool) ExecuteRepoAnalysisTaskJob::dispatch($sessionId),
            4 => (bool) ValidateRepoAnalysisCoverageJob::dispatch($sessionId),
            5 => (bool) GenerateRepoAnalysisReportJob::dispatch($sessionId),
            default => false,
        };
    }

    public function activeSessionLimitEnvelope(int $ownerUserId, int $excludeSessionId): ?JsonResponse
    {
        $maxActiveSessions = max(1, (int) config('repo_analysis.user.max_active_sessions_per_user', 2));

        $activeCount = RepoAnalysisSession::query()
            ->where('user_id', $ownerUserId)
            ->where('id', '!=', $excludeSessionId)
            ->whereNotIn('status', [
                SessionStateTransitionService::STATUS_COMPLETED,
                SessionStateTransitionService::STATUS_FAILED,
            ])
            ->count();

        if ($activeCount < $maxActiveSessions) {
            return null;
        }

        return ErrorEnvelope::make(
            'ACTIVE_SESSION_LIMIT_REACHED',
            sprintf('You already have %d active code analysis sessions.', $maxActiveSessions),
            409
        );
    }
}
