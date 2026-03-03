<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\RepoAnalysis\RepoAnalysisEventsRequest;
use App\Http\Requests\Agent\RepoAnalysis\RetryRepoAnalysisTaskRequest;
use App\Http\Requests\Agent\RepoAnalysis\StoreRepoAnalysisSessionRequest;
use App\Http\Requests\Agent\RepoAnalysis\UpdateRepoAnalysisSessionRequest;
use App\Jobs\RepoAnalysis\ExecuteRepoAnalysisTaskJob;
use App\Jobs\RepoAnalysis\GenerateRepoAnalysisReportJob;
use App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob;
use App\Jobs\RepoAnalysis\PlanRepoAnalysisTasksJob;
use App\Jobs\RepoAnalysis\ValidateRepoAnalysisCoverageJob;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisEvent;
use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RepoAnalysisSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RepoAnalysisSession::query();

        if (! $request->user()?->hasRole('admin')) {
            $query->where('user_id', (int) $request->user()->id);
        }

        $deleted = $request->string('deleted')->toString();
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed();
        } elseif ($deleted === 'all') {
            $query->withTrashed();
        }

        $status = trim($request->string('status')->toString());
        if ($status !== '') {
            $query->where('status', $status);
        }

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $sessions = $query->latest()->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($sessions->items())
                ->map(fn (RepoAnalysisSession $session): array => $this->transformSession($session))
                ->values(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
            'links' => [
                'first' => $sessions->url(1),
                'last' => $sessions->url($sessions->lastPage()),
                'prev' => $sessions->previousPageUrl(),
                'next' => $sessions->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = $this->resolveSession($request, $id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        return response()->json([
            'data' => $this->transformSession($session),
        ]);
    }

    public function store(StoreRepoAnalysisSessionRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validated();

        $session = RepoAnalysisSession::query()->create([
            'user_id' => (int) $request->user()->id,
            'name' => array_key_exists('name', $validated) ? ($validated['name'] ?: null) : null,
            'project_directory' => (string) $validated['project_directory'],
            'analyzer_profile' => (string) ($validated['analyzer_profile'] ?? 'default'),
            'runner_type' => (string) ($validated['runner_type'] ?? 'claude'),
            'status' => SessionStateTransitionService::STATUS_SETUP,
            'phase' => 0,
            'metadata_json' => [
                'source' => 'api',
                'auto_start_on_open' => true,
            ],
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.create',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['name', 'project_directory', 'analyzer_profile', 'runner_type', 'status', 'phase'],
            before: null,
            after: $session->only(['id', 'name', 'project_directory', 'analyzer_profile', 'runner_type', 'status', 'phase']),
        );

        return response()->json([
            'data' => $this->transformSession($session),
        ], 202);
    }

    public function update(
        UpdateRepoAnalysisSessionRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->resolveSession($request, $id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $validated = $request->validated();
        $before = $session->only(['name', 'project_directory', 'analyzer_profile', 'runner_type']);

        if (array_key_exists('name', $validated)) {
            $session->name = $validated['name'] ?: null;
        }

        if (array_key_exists('project_directory', $validated)) {
            $session->project_directory = (string) $validated['project_directory'];
        }

        if (array_key_exists('analyzer_profile', $validated)) {
            $session->analyzer_profile = $validated['analyzer_profile'] ?: 'default';
        }

        if (array_key_exists('runner_type', $validated)) {
            $session->runner_type = in_array($validated['runner_type'], ['claude', 'codex'], true)
                ? $validated['runner_type']
                : 'claude';
        }

        $session->save();

        $changedFields = [];
        foreach (['name', 'project_directory', 'analyzer_profile', 'runner_type'] as $field) {
            if (data_get($before, $field) !== data_get($session, $field)) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'repo_analysis.session.update',
                targetType: 'repo_analysis_session',
                targetId: (int) $session->id,
                ownerUserId: (int) $session->user_id,
                changedFields: $changedFields,
                before: $before,
                after: $session->only(['name', 'project_directory', 'analyzer_profile', 'runner_type']),
            );
        }

        return response()->json([
            'data' => $this->transformSession($session),
        ]);
    }

    public function destroy(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'delete', $session)) {
            return $forbidden;
        }

        $session->delete();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.delete',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['deleted_at'],
            before: ['deleted_at' => null],
            after: ['deleted_at' => optional($session->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => [
                'id' => (int) $session->id,
                'deleted' => true,
            ],
        ]);
    }

    public function restore(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = RepoAnalysisSession::query()->withTrashed()->findOrFail($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'restore', $session)) {
            return $forbidden;
        }

        $session->restore();
        $session->refresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.restore',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['deleted_at'],
            before: ['deleted_at' => 'soft-deleted'],
            after: ['deleted_at' => null],
        );

        return response()->json([
            'data' => $this->transformSession($session),
        ]);
    }

    public function startSnapshot(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

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

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.start_snapshot',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => (int) $session->phase, 'status' => (string) $session->status],
            after: ['queued' => true],
        );

        return response()->json([
            'data' => [
                'session_id' => (int) $session->id,
                'queued' => true,
            ],
        ], 202);
    }

    public function plan(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ((int) $session->phase !== 2) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Planning is only available in phase 2.', 409);
        }

        PlanRepoAnalysisTasksJob::dispatch($session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.plan',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => (int) $session->phase, 'status' => (string) $session->status],
            after: ['queued' => true],
        );

        return response()->json([
            'data' => ['session_id' => (int) $session->id, 'queued' => true],
        ], 202);
    }

    public function execute(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ((int) $session->phase !== 3) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Execution is only available in phase 3.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        ExecuteRepoAnalysisTaskJob::dispatch($session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.execute',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => (int) $session->phase, 'status' => (string) $session->status],
            after: ['queued' => true],
        );

        return response()->json([
            'data' => ['session_id' => (int) $session->id, 'queued' => true],
        ], 202);
    }

    public function retryTask(
        RetryRepoAnalysisTaskRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $taskId = (int) $request->validated('task_id');
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

        ExecuteRepoAnalysisTaskJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.retry_task',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['task_status'],
            before: ['task_id' => $taskId, 'task_status' => 'failed'],
            after: ['task_id' => $taskId, 'task_status' => 'pending', 'queued' => true],
        );

        return response()->json([
            'data' => [
                'session_id' => (int) $session->id,
                'task_id' => $taskId,
                'queued' => true,
            ],
        ], 202);
    }

    public function validateCoverage(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ((int) $session->phase !== 4) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Coverage validation is only available in phase 4.', 409);
        }

        ValidateRepoAnalysisCoverageJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.validate_coverage',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => (int) $session->phase, 'status' => (string) $session->status],
            after: ['queued' => true],
        );

        return response()->json([
            'data' => ['session_id' => (int) $session->id, 'queued' => true],
        ], 202);
    }

    public function generateReport(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ((int) $session->phase !== 5) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Report generation is only available in phase 5.', 409);
        }

        GenerateRepoAnalysisReportJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.generate_report',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => (int) $session->phase, 'status' => (string) $session->status],
            after: ['queued' => true],
        );

        return response()->json([
            'data' => ['session_id' => (int) $session->id, 'queued' => true],
        ], 202);
    }

    public function pause(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];
        $paused = $transitions->pause($session->id);

        if (! $paused) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be paused from its current state.', 409);
        }

        $session->refresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.pause',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['status'],
            before: $before,
            after: ['phase' => (int) $session->phase, 'status' => (string) $session->status],
        );

        return response()->json([
            'data' => $this->transformSession($session),
        ]);
    }

    public function resume(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ((string) $session->status !== SessionStateTransitionService::STATUS_PAUSED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be resumed from its current state.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];
        $resumed = $transitions->resume($session->id, [
            'error_code' => null,
            'error_summary' => null,
        ]);

        if (! $resumed) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while resuming.', 409);
        }

        $session->refresh();
        $queued = $this->dispatchByPhase((int) $session->phase, (int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.resume',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['status'],
            before: $before,
            after: ['phase' => (int) $session->phase, 'status' => (string) $session->status, 'queued' => $queued],
        );

        return response()->json([
            'data' => [
                'session_id' => (int) $session->id,
                'queued' => $queued,
            ],
        ], 202);
    }

    public function retry(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ((string) $session->status !== SessionStateTransitionService::STATUS_FAILED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be retried from its current state.', 409);
        }

        if ($limitError = $this->activeSessionLimitEnvelope((int) $session->user_id, (int) $session->id)) {
            return $limitError;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];
        $retried = $transitions->retry($session->id, [
            'error_code' => null,
            'error_summary' => null,
        ]);

        if (! $retried) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be retried from its current state.', 409);
        }

        $session->refresh();
        $queued = $this->dispatchByPhase((int) $session->phase, (int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.retry',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['status'],
            before: $before,
            after: ['phase' => (int) $session->phase, 'status' => (string) $session->status, 'queued' => $queued],
        );

        return response()->json([
            'data' => [
                'session_id' => (int) $session->id,
                'queued' => $queued,
            ],
        ], 202);
    }

    public function restartFromBeginning(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->resolveSession($request, $id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

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

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];
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

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.restart',
            targetType: 'repo_analysis_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status', 'snapshot_hash'],
            before: $before,
            after: ['phase' => 0, 'status' => SessionStateTransitionService::STATUS_SETUP, 'queued' => true],
        );

        return response()->json([
            'data' => [
                'session_id' => (int) $session->id,
                'queued' => true,
            ],
        ], 202);
    }

    public function events(RepoAnalysisEventsRequest $request, int $id): JsonResponse
    {
        $session = $this->resolveSession($request, $id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        $sinceSequence = (int) ($request->validated()['since_sequence'] ?? 0);
        $limit = (int) ($request->validated()['limit'] ?? 100);

        $events = EventWriter::readSinceSequence((int) $session->id, $sinceSequence, $limit);
        $latestSequence = (int) (RepoAnalysisEvent::query()
            ->where('repo_analysis_session_id', $session->id)
            ->max('sequence') ?? 0);

        return response()->json([
            'data' => $events->map(fn ($event): array => [
                'id' => (int) $event->id,
                'sequence' => (int) $event->sequence,
                'event_type' => (string) $event->event_type,
                'payload' => $event->payload_json ?? [],
                'phase' => $event->phase !== null ? (int) $event->phase : null,
                'status' => $event->status,
                'error_code' => $event->error_code,
                'error_summary' => $event->error_summary,
                'event_ts' => optional($event->event_ts)?->toIso8601String(),
            ])->values(),
            'meta' => [
                'returned' => $events->count(),
                'since_sequence' => $sinceSequence,
                'latest_sequence' => $latestSequence,
            ],
        ]);
    }

    public function tasks(Request $request, int $id): JsonResponse
    {
        $session = $this->resolveSession($request, $id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }
        $limit = min(200, max(1, (int) $request->integer('limit', 100)));

        $tasks = RepoAnalysisTask::query()
            ->where('repo_analysis_session_id', $session->id)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $tasks->values(),
            'meta' => [
                'returned' => $tasks->count(),
                'limit' => $limit,
            ],
        ]);
    }

    public function artifacts(Request $request, int $id): JsonResponse
    {
        $session = $this->resolveSession($request, $id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }
        $limit = min(200, max(1, (int) $request->integer('limit', 100)));

        $artifacts = RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $artifacts->values(),
            'meta' => [
                'returned' => $artifacts->count(),
                'limit' => $limit,
            ],
        ]);
    }

    public function reports(Request $request, int $id): JsonResponse
    {
        $session = $this->resolveSession($request, $id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }
        $limit = min(200, max(1, (int) $request->integer('limit', 100)));

        $reports = RepoAnalysisReport::query()
            ->where('repo_analysis_session_id', $session->id)
            ->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $reports->values(),
            'meta' => [
                'returned' => $reports->count(),
                'limit' => $limit,
            ],
        ]);
    }

    private function resolveSession(Request $request, int $id, bool $withTrashed = false): RepoAnalysisSession
    {
        $query = RepoAnalysisSession::query();
        if ($withTrashed) {
            $query->withTrashed();
        }

        /** @var RepoAnalysisSession $session */
        $session = $query->findOrFail($id);

        return $session;
    }

    private function transformSession(RepoAnalysisSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'user_id' => (int) $session->user_id,
            'name' => $session->name,
            'project_directory' => $session->project_directory,
            'analyzer_profile' => $session->analyzer_profile,
            'runner_type' => $session->runner_type,
            'phase' => (int) $session->phase,
            'status' => (string) $session->status,
            'snapshot_hash' => $session->snapshot_hash,
            'error_code' => $session->error_code,
            'error_summary' => $session->error_summary,
            'manifest_stats' => $session->manifest_stats_json ?? [],
            'report_summary' => $session->report_summary_json ?? [],
            'metadata' => $session->metadata_json ?? [],
            'started_at' => optional($session->started_at)?->toIso8601String(),
            'finished_at' => optional($session->finished_at)?->toIso8601String(),
            'created_at' => optional($session->created_at)?->toIso8601String(),
            'updated_at' => optional($session->updated_at)?->toIso8601String(),
            'deleted_at' => optional($session->deleted_at)?->toIso8601String(),
        ];
    }

    private function activeSessionLimitEnvelope(int $ownerUserId, int $excludeSessionId): ?JsonResponse
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

    private function dispatchByPhase(int $phase, int $sessionId): bool
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

    private function forbiddenIfCannot(Request $request, string $ability, RepoAnalysisSession $session): ?JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return ErrorEnvelope::make('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        if (! Gate::forUser($user)->allows($ability, $session)) {
            return ErrorEnvelope::make('FORBIDDEN', 'Access denied.', 403);
        }

        return null;
    }
}
