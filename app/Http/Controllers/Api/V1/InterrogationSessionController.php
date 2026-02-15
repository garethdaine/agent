<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\RequestPlanRevisionRequest;
use App\Http\Requests\Interrogation\StoreInterrogationSessionRequest;
use App\Http\Requests\Interrogation\SubmitAnswerRequest;
use App\Http\Requests\Interrogation\UpdateAnnotationRequest;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Models\InterrogationSession;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Interrogation\ExportService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\SessionStateTransitionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterrogationSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->interrogationSessions()->newQuery();

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

        $type = trim($request->string('type')->toString());
        if ($type !== '') {
            $query->where('interrogation_type', $type);
        }

        $runner = trim($request->string('runner')->toString());
        if ($runner !== '') {
            $query->where('runner_type', $runner);
        }

        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('feature_brief', 'like', "%{$search}%")
                    ->orWhere('project_directory', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $sessions = $query->latest()->paginate($perPage)->withQueryString();

        $data = collect($sessions->items())
            ->map(fn (InterrogationSession $session): array => $this->transformSession($session, false))
            ->values();

        return response()->json([
            'data' => $data,
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
            'filters' => [
                'status' => $status,
                'type' => $type,
                'runner' => $runner,
                'q' => $search,
                'deleted' => $deleted,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->withTrashed()->findOrFail($id);
        $includeEvents = $request->boolean('include_events', false);

        $data = $this->transformSession($session, true);

        if ($includeEvents) {
            $data['events'] = $session->events()
                ->orderByDesc('sequence')
                ->limit(100)
                ->get()
                ->sortBy('sequence')
                ->values()
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'sequence' => $event->sequence,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'event_ts' => $this->toRfc3339Millis($event->event_ts),
                    'created_at' => $this->toRfc3339Millis($event->created_at),
                ]);
        }

        return response()->json(['data' => $data]);
    }

    public function store(
        StoreInterrogationSessionRequest $request,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $validated = $request->validated();

        $session = $request->user()->interrogationSessions()->create([
            'name' => $validated['name'] ?? null,
            'runner_type' => $validated['runner_type'],
            'project_directory' => $validated['project_directory'],
            'interrogation_type' => $validated['interrogation_type'],
            'feature_brief' => $validated['feature_brief'] ?? null,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
            'metadata_json' => [
                'source' => 'ui',
            ],
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.create',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['name', 'runner_type', 'project_directory', 'interrogation_type', 'feature_brief', 'status', 'phase'],
            before: null,
            after: $session->only(['id', 'name', 'runner_type', 'project_directory', 'interrogation_type', 'status', 'phase']),
        );

        ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id);

        return response()->json([
            'data' => $this->transformSession($session, false),
        ], 202);
    }

    public function submitAnswer(
        SubmitAnswerRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is terminal and cannot accept answers.', 409);
        }

        $validated = $request->validated();
        $message = $this->buildAnswerMessage($validated);

        ExecuteInterrogationRoundJob::dispatch((int) $session->id, $message);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.answer',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['answer'],
            before: null,
            after: [
                'question_id' => $validated['question_id'] ?? null,
                'answer_type' => $validated['answer_type'],
            ],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
            ],
        ], 202);
    }

    public function editAnswer(
        SubmitAnswerRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        $validated = $request->validated();
        $questionId = (string) ($validated['question_id'] ?? 'unknown');
        $message = 'Edited answer for question '.$questionId.'. '.$this->buildAnswerMessage($validated);

        $metadata = (array) ($session->metadata_json ?? []);
        $metadata['stale_from_question_id'] = $questionId;
        $session->metadata_json = $metadata;
        $session->save();

        $writer = new InterrogationEventWriter($session);
        $writer->appendAnnotation([
            'type' => 'stale_mark',
            'question_id' => $questionId,
            'message' => 'Answer edited; downstream questions may be stale.',
        ]);

        ExecuteInterrogationRoundJob::dispatch((int) $session->id, $message);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.answer_edit',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['stale_from_question_id' => $questionId],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
            ],
        ], 202);
    }

    public function confirmSummary(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (! is_array($session->summary_json) || $session->summary_json === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary is not ready to confirm.', 409);
        }

        if ($session->phase === InterrogationSession::PHASE_PLANNING && $session->status === InterrogationSession::STATUS_PLANNING) {
            return response()->json(['data' => ['confirmed' => true, 'session_id' => $session->id]]);
        }

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::STATUS_PLANNING,
            [InterrogationSession::STATUS_SUMMARIZING],
        );

        if (! $moved) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while confirming summary.', 409);
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_PLANNING,
            (string) $session->status,
            ['at' => CarbonImmutable::now('UTC')->toIso8601String(), 'confirmed_by_user_id' => $request->user()->id],
        );

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.confirm_summary',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => InterrogationSession::PHASE_SUMMARY, 'status' => InterrogationSession::STATUS_SUMMARIZING],
            after: ['phase' => $session->phase, 'status' => $session->status],
        );

        return response()->json(['data' => ['confirmed' => true, 'session_id' => $session->id]]);
    }

    public function generatePlan(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (! in_array($session->status, [InterrogationSession::STATUS_PLANNING, InterrogationSession::STATUS_COMPLETED], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not ready to generate a plan.', 409);
        }

        ExecuteInterrogationPlanJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.generate_plan',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['status'],
            before: ['status' => $session->status],
            after: ['status' => $session->status],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
            ],
        ], 202);
    }

    public function requestRevision(
        RequestPlanRevisionRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        $validated = $request->validated();

        $prompt = sprintf(
            'Revise the implementation plan. Action: %s. Section: %s. Notes: %s',
            (string) $validated['action'],
            (string) ($validated['section'] ?? 'entire_plan'),
            (string) ($validated['notes'] ?? 'none')
        );

        ExecuteInterrogationPlanJob::dispatch((int) $session->id, $prompt);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.request_revision',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['revision_prompt'],
            before: null,
            after: $validated,
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
            ],
        ], 202);
    }

    public function updateAnnotation(
        UpdateAnnotationRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        $validated = $request->validated();

        $annotations = (array) ($session->annotations_json ?? []);
        $annotations[(string) $validated['key']] = $validated['value'] ?? null;
        $session->annotations_json = $annotations;
        $session->save();

        $writer = new InterrogationEventWriter($session);
        $writer->appendAnnotation([
            'key' => (string) $validated['key'],
            'value' => $validated['value'] ?? null,
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.annotation',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['annotations_json'],
            before: null,
            after: ['key' => $validated['key']],
        );

        return response()->json([
            'data' => $this->transformSession($session, true),
        ]);
    }

    public function exportSummary(Request $request, int $id, ExportService $exportService): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (! is_array($session->summary_json) || $session->summary_json === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary is not available for export.', 409);
        }

        $path = $exportService->exportSummary($session);

        return response()->json([
            'data' => [
                'path' => $path,
            ],
        ]);
    }

    public function exportPlan(Request $request, int $id, ExportService $exportService): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (! is_array($session->plan_json) || $session->plan_json === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan is not available for export.', 409);
        }

        $path = $exportService->exportPlan($session);

        return response()->json([
            'data' => [
                'path' => $path,
            ],
        ]);
    }

    public function pause(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        $transitioned = $transitions->transition(
            (int) $session->id,
            InterrogationSession::ACTIVE_STATUSES,
            InterrogationSession::STATUS_PAUSED,
        );

        if (! $transitioned) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be paused from its current state.', 409);
        }

        $session->refresh();

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'status' => $session->status,
            ],
        ]);
    }

    public function resume(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (! in_array($session->status, InterrogationSession::RESUMABLE_STATUSES, true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be resumed from its current state.', 409);
        }

        $nextStatus = match ((int) $session->phase) {
            InterrogationSession::PHASE_DISCOVERY => InterrogationSession::STATUS_DISCOVERING,
            InterrogationSession::PHASE_INTERROGATION => InterrogationSession::STATUS_INTERROGATING,
            InterrogationSession::PHASE_SUMMARY => InterrogationSession::STATUS_SUMMARIZING,
            InterrogationSession::PHASE_PLANNING => InterrogationSession::STATUS_PLANNING,
            default => InterrogationSession::STATUS_SETUP,
        };

        $transitioned = $transitions->transition(
            (int) $session->id,
            InterrogationSession::RESUMABLE_STATUSES,
            $nextStatus,
        );

        if (! $transitioned) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while resuming.', 409);
        }

        $session->refresh();

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'status' => $session->status,
                'phase' => $session->phase,
            ],
        ]);
    }

    public function retry(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->withTrashed()->findOrFail($id);

        if ($session->trashed()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is deleted. Restore it before retrying.', 409);
        }

        if ($session->status === InterrogationSession::STATUS_COMPLETED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is already completed. Use plan revision for updates.', 409);
        }

        $targetStatus = match ((int) $session->phase) {
            InterrogationSession::PHASE_DISCOVERY => InterrogationSession::STATUS_DISCOVERING,
            InterrogationSession::PHASE_INTERROGATION => InterrogationSession::STATUS_INTERROGATING,
            InterrogationSession::PHASE_SUMMARY => InterrogationSession::STATUS_SUMMARIZING,
            InterrogationSession::PHASE_PLANNING => InterrogationSession::STATUS_PLANNING,
            default => InterrogationSession::STATUS_SETUP,
        };

        $allowedFromStatuses = [
            InterrogationSession::STATUS_FAILED,
            InterrogationSession::STATUS_PAUSED,
            InterrogationSession::STATUS_SETUP,
        ];

        $transitioned = $transitions->transition(
            (int) $session->id,
            $allowedFromStatuses,
            $targetStatus,
            [
                'error_code' => null,
                'error_summary' => null,
                'finished_at' => null,
            ]
        );

        if (! $transitioned) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be retried from its current state.', 409);
        }

        match ((int) $session->phase) {
            InterrogationSession::PHASE_SETUP, InterrogationSession::PHASE_DISCOVERY =>
                ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_INTERROGATION =>
                ExecuteInterrogationRoundJob::dispatch((int) $session->id, 'Retry current interrogation phase and continue with the next best question.'),
            InterrogationSession::PHASE_SUMMARY =>
                ExecuteInterrogationSummaryJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_PLANNING =>
                ExecuteInterrogationPlanJob::dispatch((int) $session->id),
            default =>
                ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id),
        };

        $session->refresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.retry',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['status', 'error_code', 'error_summary', 'finished_at'],
            before: null,
            after: [
                'status' => $session->status,
                'phase' => $session->phase,
                'error_code' => $session->error_code,
                'error_summary' => $session->error_summary,
                'finished_at' => $this->toRfc3339Millis($session->finished_at),
            ],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
                'status' => $session->status,
                'phase' => $session->phase,
            ],
        ], 202);
    }

    public function destroy(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        $session->delete();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.delete',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['deleted_at'],
            before: ['deleted_at' => null],
            after: ['deleted_at' => $this->toRfc3339Millis($session->deleted_at)],
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function restore(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->withTrashed()->findOrFail($id);
        $session->restore();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.restore',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['deleted_at'],
            before: ['deleted_at' => 'set'],
            after: ['deleted_at' => null],
        );

        return response()->json(['data' => ['restored' => true]]);
    }

    public function events(Request $request, int $id): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        $after = max(0, (int) $request->integer('after_sequence', 0));
        $limit = min(500, max(1, (int) $request->integer('limit', 100)));

        $events = $session->events()
            ->where('sequence', '>', $after)
            ->orderBy('sequence')
            ->limit($limit + 1)
            ->get();

        $hasMore = $events->count() > $limit;
        $returned = $events->take($limit)->values();
        $nextAfter = $returned->last()?->sequence ?? $after;

        return response()->json([
            'data' => $returned->map(fn ($event): array => [
                'id' => $event->id,
                'session_id' => $event->interrogation_session_id,
                'sequence' => $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'event_ts' => $this->toRfc3339Millis($event->event_ts),
                'created_at' => $this->toRfc3339Millis($event->created_at),
            ]),
            'meta' => [
                'after_sequence' => $after,
                'returned' => $returned->count(),
                'has_more' => $hasMore,
                'next_after_sequence' => $nextAfter,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformSession(InterrogationSession $session, bool $includeLargePayloads): array
    {
        return [
            'id' => $session->id,
            'user_id' => $session->user_id,
            'name' => $session->name,
            'runner_type' => $session->runner_type,
            'project_directory' => $session->project_directory,
            'interrogation_type' => $session->interrogation_type,
            'feature_brief' => $includeLargePayloads ? $session->feature_brief : null,
            'status' => $session->status,
            'phase' => $session->phase,
            'cli_session_id' => $session->cli_session_id,
            'summary_json' => $session->summary_json,
            'plan_json' => $session->plan_json,
            'annotations_json' => $session->annotations_json,
            'metadata_json' => $session->metadata_json,
            'error_code' => $session->error_code,
            'error_summary' => $session->error_summary,
            'started_at' => $this->toRfc3339Millis($session->started_at),
            'finished_at' => $this->toRfc3339Millis($session->finished_at),
            'created_at' => $this->toRfc3339Millis($session->created_at),
            'updated_at' => $this->toRfc3339Millis($session->updated_at),
            'deleted_at' => $this->toRfc3339Millis($session->deleted_at),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildAnswerMessage(array $validated): string
    {
        $questionId = (string) ($validated['question_id'] ?? 'unknown');
        $answerType = (string) $validated['answer_type'];

        if ($answerType === 'choice') {
            return sprintf('Question %s answered with choice: %s', $questionId, (string) ($validated['selected_option'] ?? ''));
        }

        if ($answerType === 'skip') {
            return sprintf('Question %s skipped. Reason: %s', $questionId, (string) ($validated['skip_reason'] ?? ''));
        }

        return sprintf('Question %s answered: %s', $questionId, (string) ($validated['answer_text'] ?? ''));
    }

    private function toRfc3339Millis(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->format('Y-m-d\TH:i:s.v\Z');
        } catch (\Throwable) {
            return null;
        }
    }
}
