<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\RepoAnalysis\CreateRepoAnalysisSessionAction;
use App\Actions\RepoAnalysis\DeleteRepoAnalysisSessionAction;
use App\Actions\RepoAnalysis\FindRepoAnalysisSessionAction;
use App\Actions\RepoAnalysis\GetLatestEventSequenceAction;
use App\Actions\RepoAnalysis\ListRepoAnalysisArtifactsAction;
use App\Actions\RepoAnalysis\ListRepoAnalysisReportsAction;
use App\Actions\RepoAnalysis\ListRepoAnalysisSessionsAction;
use App\Actions\RepoAnalysis\ListRepoAnalysisTasksAction;
use App\Actions\RepoAnalysis\PurgeRepoAnalysisSessionAction;
use App\Actions\RepoAnalysis\RestoreRepoAnalysisSessionAction;
use App\Actions\RepoAnalysis\UpdateRepoAnalysisSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\RepoAnalysis\RepoAnalysisEventsRequest;
use App\Http\Requests\Agent\RepoAnalysis\RetryRepoAnalysisTaskRequest;
use App\Http\Requests\Agent\RepoAnalysis\StoreRepoAnalysisSessionRequest;
use App\Http\Requests\Agent\RepoAnalysis\UpdateRepoAnalysisSessionRequest;
use App\Models\RepoAnalysisSession;
use App\Services\RepoAnalysis\RepoAnalysisReportService;
use App\Services\RepoAnalysis\RepoAnalysisWorkflowService;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RepoAnalysisSessionController extends Controller
{
    public function __construct(
        private RepoAnalysisWorkflowService $workflow,
        private RepoAnalysisReportService $reportService,
        private FindRepoAnalysisSessionAction $findSession,
    ) {}

    public function index(Request $request, ListRepoAnalysisSessionsAction $listSessions): JsonResponse
    {
        $sessions = $listSessions->execute([
            'user_id' => (int) $request->user()->id,
            'is_admin' => $request->user()?->hasRole('admin'),
            'deleted' => $request->string('deleted')->toString(),
            'status' => trim($request->string('status')->toString()),
            'per_page' => (int) $request->integer('per_page', 25),
        ]);

        return response()->json([
            'data' => collect($sessions->items())
                ->map(fn (RepoAnalysisSession $session): array => $this->reportService->transformSession($session))
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
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        return response()->json([
            'data' => $this->reportService->transformSession($session),
        ]);
    }

    public function store(
        StoreRepoAnalysisSessionRequest $request,
        CreateRepoAnalysisSessionAction $createSession,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $createSession->execute(
            (int) $request->user()->id,
            $request->validated(),
        );

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
            'data' => $this->reportService->transformSession($session),
        ], 202);
    }

    public function update(
        UpdateRepoAnalysisSessionRequest $request,
        int $id,
        UpdateRepoAnalysisSessionAction $updateSession,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $result = $updateSession->execute($session, $request->validated());

        if ($result['changed_fields'] !== []) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'repo_analysis.session.update',
                targetType: 'repo_analysis_session',
                targetId: (int) $result['session']->id,
                ownerUserId: (int) $result['session']->user_id,
                changedFields: $result['changed_fields'],
                before: $result['before'],
                after: $result['session']->only(['name', 'project_directory', 'analyzer_profile', 'runner_type']),
            );
        }

        return response()->json([
            'data' => $this->reportService->transformSession($result['session']),
        ]);
    }

    public function destroy(
        Request $request,
        int $id,
        DeleteRepoAnalysisSessionAction $deleteSession,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'delete', $session)) {
            return $forbidden;
        }

        $deleteSession->execute($session);

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

    public function purge(
        Request $request,
        int $id,
        PurgeRepoAnalysisSessionAction $purgeSession,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'forceDelete', $session)) {
            return $forbidden;
        }

        $projectRoot = $this->reportService->resolvedProjectRoot((string) $session->project_directory);
        $storageRoot = realpath(storage_path()) ?: storage_path();
        $allowedRoots = array_values(array_filter([$projectRoot, $storageRoot], static fn (?string $path): bool => is_string($path) && $path !== ''));

        $pathsToDelete = $this->reportService->collectSessionFilePaths($session);
        $deletedFileCount = 0;
        $missingFileCount = 0;

        foreach ($pathsToDelete as $path) {
            $deleted = $this->reportService->deletePathIfAllowed($path, $allowedRoots);
            if ($deleted) {
                $deletedFileCount++;

                continue;
            }

            if ($path !== '' && ! file_exists($path)) {
                $missingFileCount++;
            }
        }

        $before = [
            'deleted_at' => optional($session->deleted_at)?->toIso8601String(),
            'phase' => (int) $session->phase,
            'status' => (string) $session->status,
            'counts' => [
                'events' => $session->events()->count(),
                'tasks' => $session->tasks()->count(),
                'artifacts' => $session->artifacts()->count(),
                'reports' => $session->reports()->count(),
            ],
        ];

        $purgeSession->execute($session);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'repo_analysis.session.purge',
            targetType: 'repo_analysis_session',
            targetId: (int) $id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['force_deleted', 'related_records', 'export_files'],
            before: $before,
            after: [
                'force_deleted' => true,
                'deleted_files' => $deletedFileCount,
                'missing_files' => $missingFileCount,
            ],
        );

        return response()->json([
            'data' => [
                'id' => (int) $id,
                'purged' => true,
                'deleted_files' => $deletedFileCount,
                'missing_files' => $missingFileCount,
            ],
        ]);
    }

    public function restore(
        Request $request,
        int $id,
        RestoreRepoAnalysisSessionAction $restoreSession,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'restore', $session)) {
            return $forbidden;
        }

        $restoreSession->execute($session);
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
            'data' => $this->reportService->transformSession($session),
        ]);
    }

    public function startSnapshot(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ($error = $this->workflow->startSnapshot($session)) {
            return $error;
        }

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ($error = $this->workflow->plan($session)) {
            return $error;
        }

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ($error = $this->workflow->execute($session)) {
            return $error;
        }

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
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $taskId = (int) $request->validated('task_id');
        $result = $this->workflow->retryTask($session, $taskId, $transitions);

        if ($result instanceof JsonResponse) {
            return $result;
        }

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ($error = $this->reportService->validateCoverage($session)) {
            return $error;
        }

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        if ($error = $this->reportService->generateReport($session)) {
            return $error;
        }

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];

        if ($error = $this->workflow->pause($session, $transitions)) {
            return $error;
        }

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
            'data' => $this->reportService->transformSession($session),
        ]);
    }

    public function resume(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];
        $result = $this->workflow->resume($session, $transitions);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $queued = (bool) $result;

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];
        $result = $this->workflow->retry($session, $transitions);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $queued = (bool) $result;

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
        $session = $this->findSession->execute($id);
        if ($forbidden = $this->forbiddenIfCannot($request, 'update', $session)) {
            return $forbidden;
        }

        $before = ['phase' => (int) $session->phase, 'status' => (string) $session->status];

        if ($error = $this->workflow->restartFromBeginning($session)) {
            return $error;
        }

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

    public function events(
        RepoAnalysisEventsRequest $request,
        int $id,
        GetLatestEventSequenceAction $getLatestSequence,
    ): JsonResponse {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        $sinceSequence = (int) ($request->validated()['since_sequence'] ?? 0);
        $limit = (int) ($request->validated()['limit'] ?? 100);

        $events = EventWriter::readSinceSequence((int) $session->id, $sinceSequence, $limit);
        $latestSequence = $getLatestSequence->execute((int) $session->id);

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

    public function tasks(Request $request, int $id, ListRepoAnalysisTasksAction $listTasks): JsonResponse
    {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        $this->reportService->reconcileStaleRunningTasks($session);

        $limit = (int) $request->integer('limit', 100);
        $tasks = $listTasks->execute((int) $session->id, $limit);

        return response()->json([
            'data' => $tasks->values(),
            'meta' => [
                'returned' => $tasks->count(),
                'limit' => min(200, max(1, $limit)),
            ],
        ]);
    }

    public function artifacts(Request $request, int $id, ListRepoAnalysisArtifactsAction $listArtifacts): JsonResponse
    {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        $limit = (int) $request->integer('limit', 100);
        $artifacts = $listArtifacts->execute((int) $session->id, $limit);

        return response()->json([
            'data' => $artifacts->values(),
            'meta' => [
                'returned' => $artifacts->count(),
                'limit' => min(200, max(1, $limit)),
            ],
        ]);
    }

    public function reports(Request $request, int $id, ListRepoAnalysisReportsAction $listReports): JsonResponse
    {
        $session = $this->findSession->execute($id, true);
        if ($forbidden = $this->forbiddenIfCannot($request, 'view', $session)) {
            return $forbidden;
        }

        $limit = (int) $request->integer('limit', 100);
        $reports = $listReports->execute((int) $session->id, $limit);

        return response()->json([
            'data' => $reports->values(),
            'meta' => [
                'returned' => $reports->count(),
                'limit' => min(200, max(1, $limit)),
            ],
        ]);
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
