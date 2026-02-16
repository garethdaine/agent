<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\RequestPlanRevisionRequest;
use App\Http\Requests\Interrogation\StoreInterrogationSessionRequest;
use App\Http\Requests\Interrogation\SubmitAnswerRequest;
use App\Http\Requests\Interrogation\UpdateAnnotationRequest;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Interrogation\ExportService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SummaryPayloadNormalizer;
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

        ExecuteInterrogationRoundJob::dispatch((int) $session->id, $message, false, $validated);

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

        ExecuteInterrogationRoundJob::dispatch((int) $session->id, $message, false, $validated);

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
        $summary = $this->normalizedSummaryJson($session, true);

        if ($summary === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary is not ready to confirm.', 409);
        }

        $openQuestions = is_array($summary['open_questions'] ?? null) ? array_values($summary['open_questions']) : [];
        if ($openQuestions !== []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Open questions remain. Continue interrogation or revise summary first.', 409);
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

    public function reviseSummary(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in summary phase.', 409);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:6000'],
        ]);

        if ($session->status === InterrogationSession::STATUS_PAUSED) {
            $resumed = $transitions->transition(
                (int) $session->id,
                [InterrogationSession::STATUS_PAUSED],
                InterrogationSession::STATUS_SUMMARIZING
            );

            if (! $resumed) {
                return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while requesting summary revision.', 409);
            }

            $session->refresh();
        }

        if ($session->status !== InterrogationSession::STATUS_SUMMARIZING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary can only be revised while summarizing.', 409);
        }

        $notes = trim((string) ($validated['notes'] ?? ''));

        ExecuteInterrogationSummaryJob::dispatch((int) $session->id, $notes !== '' ? $notes : null);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.revise_summary',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['summary_revision_notes'],
            before: null,
            after: ['notes' => $notes],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
            ],
        ], 202);
    }

    public function continueInterrogation(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in summary phase.', 409);
        }

        $validated = $request->validate([
            'focus' => ['nullable', 'string', 'max:4000'],
        ]);

        $focus = trim((string) ($validated['focus'] ?? ''));
        $summary = $this->normalizedSummaryJson($session, true);
        $openQuestions = is_array($summary['open_questions'] ?? null) ? array_values($summary['open_questions']) : [];

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_INTERROGATION,
            InterrogationSession::STATUS_INTERROGATING,
            [InterrogationSession::STATUS_SUMMARIZING, InterrogationSession::STATUS_PAUSED],
        );

        if (! $moved) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while reopening interrogation.', 409);
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_INTERROGATION,
            (string) $session->status,
            ['at' => CarbonImmutable::now('UTC')->toIso8601String(), 'reopened_by_user_id' => $request->user()->id],
        );

        $prompt = 'Continue interrogation from summary. Ask the NEXT single unresolved question only. '
            .'Do not batch multiple questions into one response.';

        if ($openQuestions !== []) {
            $prompt .= "\n\nPrior summary open questions:\n";
            foreach ($openQuestions as $index => $question) {
                if (! is_string($question) || trim($question) === '') {
                    continue;
                }
                $prompt .= ($index + 1).'. '.trim($question)."\n";
            }
        }

        if ($focus !== '') {
            $prompt .= "\nAdditional user focus:\n".$focus;
        }

        ExecuteInterrogationRoundJob::dispatch((int) $session->id, $prompt, true);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.continue_interrogation',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => InterrogationSession::PHASE_SUMMARY, 'status' => InterrogationSession::STATUS_SUMMARIZING],
            after: ['phase' => $session->phase, 'status' => $session->status],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
            ],
        ], 202);
    }

    public function generatePlan(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase < InterrogationSession::PHASE_PLANNING || in_array($session->status, [InterrogationSession::STATUS_FAILED], true)) {
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

    public function approvePlan(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase > InterrogationSession::PHASE_PLANNING && $session->approved_at !== null) {
            return response()->json([
                'data' => [
                    'approved' => true,
                    'session_id' => $session->id,
                    'approved_at' => $this->toRfc3339Millis($session->approved_at),
                ],
            ]);
        }

        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING || $session->status !== InterrogationSession::STATUS_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan can only be approved from planning phase.', 409);
        }

        if (! $this->hasMeaningfulPlan($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan is not available to approve.', 409);
        }

        $approvedAt = CarbonImmutable::now('UTC');
        $session->approved_at = $approvedAt;

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['plan'] = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'approved_at' => $approvedAt->toIso8601String(),
                'approved_by_user_id' => (int) $request->user()->id,
            ],
        );
        $session->metadata_json = $metadata;
        $session->save();

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::PHASE_BUILD_TASKS,
            InterrogationSession::STATUS_BUILD_TASKS,
            [InterrogationSession::STATUS_PLANNING],
        );

        if (! $moved) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while approving plan.', 409);
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::PHASE_BUILD_TASKS,
            (string) $session->status,
            ['at' => CarbonImmutable::now('UTC')->toIso8601String(), 'approved_by_user_id' => $request->user()->id],
        );

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.approve_plan',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['approved_at', 'phase', 'status'],
            before: ['approved_at' => null, 'phase' => InterrogationSession::PHASE_PLANNING, 'status' => InterrogationSession::STATUS_PLANNING],
            after: ['approved_at' => $this->toRfc3339Millis($session->approved_at), 'phase' => $session->phase, 'status' => $session->status],
        );

        return response()->json([
            'data' => [
                'approved' => true,
                'session_id' => $session->id,
                'approved_at' => $this->toRfc3339Millis($session->approved_at),
                'phase' => $session->phase,
                'status' => $session->status,
            ],
        ]);
    }

    public function requestRevision(
        RequestPlanRevisionRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan revision is only available during planning phase.', 409);
        }

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

    public function generateBuildTasks(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (
            (int) $session->phase !== InterrogationSession::PHASE_BUILD_TASKS
            || ! in_array($session->status, [InterrogationSession::STATUS_BUILD_TASKS, InterrogationSession::STATUS_PAUSED], true)
        ) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in build tasks phase.', 409);
        }

        if ($session->approved_at === null || ! $this->hasMeaningfulPlan($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not ready to generate build tasks.', 409);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:6000'],
        ]);

        $build = $this->buildMetadata($session);
        $build['status'] = 'generating_tasks';
        $build['notes'] = trim((string) ($validated['notes'] ?? ''));
        $build['task_count'] = 0;
        $build['error'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $this->saveBuildMetadata($session, $build);

        GenerateInterrogationBuildTasksJob::dispatch(
            (int) $session->id,
            $build['notes'] !== '' ? $build['notes'] : null,
        );

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.generate_build_tasks',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['build_status' => 'generating_tasks'],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
                'build_status' => 'generating_tasks',
            ],
        ], 202);
    }

    public function startBuild(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if (! in_array((int) $session->phase, [InterrogationSession::PHASE_BUILD_TASKS, InterrogationSession::PHASE_BUILD_EXECUTION], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build can only start from build phases.', 409);
        }

        if ($session->approved_at === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan must be approved before starting build.', 409);
        }

        $validated = $request->validate([
            'restart_failed' => ['nullable', 'boolean'],
        ]);

        $tasks = $session->buildTasks()->ordered()->get();
        if ($tasks->isEmpty()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'No build tasks are available. Generate build tasks first.', 409);
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks are still being generated.', 409);
        }

        $currentBuildStatus = $this->normalizedBuildStatus($build);
        $restartFailed = (bool) ($validated['restart_failed'] ?? false);

        if ($currentBuildStatus === 'completed' && ! $restartFailed) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build is already completed. Use restart_failed to re-run.', 409);
        }

        if ($restartFailed) {
            foreach ($tasks as $task) {
                if (
                    $task->status === InterrogationBuildTask::STATUS_FAILED
                    || $task->status === InterrogationBuildTask::STATUS_BLOCKED
                    || $task->status === InterrogationBuildTask::STATUS_IN_PROGRESS
                    || ($task->status === InterrogationBuildTask::STATUS_COMPLETED && $currentBuildStatus === 'completed')
                ) {
                    $task->status = InterrogationBuildTask::STATUS_PENDING;
                    $task->agent_job_run_id = null;
                    $task->last_error = null;
                    $task->started_at = null;
                    $task->finished_at = null;
                    $task->save();
                }
            }
        }

        $build['status'] = 'running';
        $build['error'] = null;
        $build['pause_reason'] = null;
        $build['paused_at'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['started_at'] = $build['started_at'] ?? CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['approval_required'] = false;
        $build['rate_limit_detected'] = false;
        $build['rate_limit_reset_at'] = null;

        $this->saveBuildMetadata($session, $build);

        if ((int) $session->phase === InterrogationSession::PHASE_BUILD_TASKS) {
            $moved = $transitions->transitionPhase(
                (int) $session->id,
                InterrogationSession::PHASE_BUILD_TASKS,
                InterrogationSession::PHASE_BUILD_EXECUTION,
                InterrogationSession::STATUS_BUILD_EXECUTING,
                [InterrogationSession::STATUS_BUILD_TASKS, InterrogationSession::STATUS_PAUSED],
            );

            if (! $moved) {
                return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while starting build execution.', 409);
            }

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendPhaseTransition(
                InterrogationSession::PHASE_BUILD_TASKS,
                InterrogationSession::PHASE_BUILD_EXECUTION,
                (string) $session->status,
                ['at' => CarbonImmutable::now('UTC')->toIso8601String()],
            );
        }

        ExecuteInterrogationBuildJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.start_build',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['build_status' => 'running', 'restart_failed' => $restartFailed],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
                'build_status' => 'running',
            ],
        ], 202);
    }

    public function pauseBuild(
        Request $request,
        int $id,
        RunStateTransitionService $runTransitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build execution is not active.', 409);
        }

        $build = $this->buildMetadata($session);

        if (($build['status'] ?? null) === 'paused') {
            return response()->json([
                'data' => [
                    'accepted' => true,
                    'session_id' => $session->id,
                    'build_status' => 'paused',
                    'already_paused' => true,
                ],
            ]);
        }

        if (($build['status'] ?? null) !== 'running') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build is not running.', 409);
        }

        $build['status'] = 'paused';
        $build['pause_reason'] = 'user';
        $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $activeTask = $session->buildTasks()
            ->with('run')
            ->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)
            ->orderBy('sequence')
            ->first();

        if ($activeTask?->run !== null && in_array((string) $activeTask->run->status, [
            AgentJobRun::STATUS_QUEUED,
            AgentJobRun::STATUS_STARTING,
            AgentJobRun::STATUS_RUNNING,
        ], true)) {
            $runTransitions->transition(
                (int) $activeTask->run->id,
                [AgentJobRun::STATUS_QUEUED, AgentJobRun::STATUS_STARTING, AgentJobRun::STATUS_RUNNING],
                AgentJobRun::STATUS_STOPPING,
            );

            if (is_int($activeTask->run->pid) && $activeTask->run->pid > 0 && function_exists('posix_kill')) {
                @posix_kill((int) $activeTask->run->pid, SIGTERM);
            }
        }

        $this->saveBuildMetadata($session, $build);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.pause_build',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['build_status' => 'paused'],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'session_id' => $session->id,
                'build_status' => 'paused',
                'active_task_id' => $activeTask?->id,
                'active_run_id' => $activeTask?->agent_job_run_id,
            ],
        ], 202);
    }

    public function resumeBuild(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build execution is not active.', 409);
        }

        $build = $this->buildMetadata($session);

        if (($build['status'] ?? null) !== 'paused') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build is not paused.', 409);
        }

        $tasks = $session->buildTasks()->with('run')->ordered()->get();
        if ($tasks->isEmpty()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'No build tasks are available to resume.', 409);
        }

        foreach ($tasks as $task) {
            if ($task->status === InterrogationBuildTask::STATUS_BLOCKED) {
                $task->status = InterrogationBuildTask::STATUS_PENDING;
                $task->agent_job_run_id = null;
                $task->last_error = null;
                $task->started_at = null;
                $task->finished_at = null;
                $task->save();

                continue;
            }

            if ($task->status === InterrogationBuildTask::STATUS_IN_PROGRESS) {
                $run = $task->run;
                if ($run === null || in_array((string) $run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
                    $task->status = InterrogationBuildTask::STATUS_PENDING;
                    $task->agent_job_run_id = null;
                    $task->last_error = null;
                    $task->started_at = null;
                    $task->finished_at = null;
                    $task->save();
                }
            }
        }

        $build['status'] = 'running';
        $build['pause_reason'] = null;
        $build['paused_at'] = null;
        $build['error'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $this->saveBuildMetadata($session, $build);

        $transitions->transition(
            (int) $session->id,
            [InterrogationSession::STATUS_PAUSED, InterrogationSession::STATUS_BUILD_EXECUTING],
            InterrogationSession::STATUS_BUILD_EXECUTING,
        );

        ExecuteInterrogationBuildJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.resume_build',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['build_status' => 'running'],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
                'build_status' => 'running',
            ],
        ], 202);
    }

    public function clarifyBuild(
        Request $request,
        int $id,
        RunStateTransitionService $runTransitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build clarification is only available during build execution.', 409);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:6000'],
            'task_id' => ['nullable', 'integer'],
        ]);

        $message = trim((string) $validated['message']);

        $targetTaskId = isset($validated['task_id']) ? (int) $validated['task_id'] : null;
        $targetTask = $targetTaskId !== null
            ? $session->buildTasks()->with('run')->whereKey($targetTaskId)->first()
            : $session->buildTasks()
                ->with('run')
                ->whereIn('status', [InterrogationBuildTask::STATUS_IN_PROGRESS, InterrogationBuildTask::STATUS_BLOCKED, InterrogationBuildTask::STATUS_PENDING])
                ->orderByRaw("case when status = 'in_progress' then 0 when status = 'blocked' then 1 else 2 end")
                ->orderBy('sequence')
                ->first();

        if ($targetTask === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'No build task is available to clarify.', 409);
        }

        $taskMetadata = is_array($targetTask->metadata_json) ? $targetTask->metadata_json : [];
        $clarifications = is_array($taskMetadata['clarifications'] ?? null) ? $taskMetadata['clarifications'] : [];
        $clarifications[] = [
            'message' => $message,
            'by_user_id' => (int) $request->user()->id,
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $taskMetadata['clarifications'] = array_values($clarifications);
        $targetTask->metadata_json = $taskMetadata;

        $build = $this->buildMetadata($session);
        $build['last_clarification_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        if ($targetTask->status === InterrogationBuildTask::STATUS_IN_PROGRESS) {
            $targetTask->status = InterrogationBuildTask::STATUS_BLOCKED;
            $targetTask->last_error = 'Clarification submitted. Resume build to retry this task with the new context.';
            $targetTask->finished_at = CarbonImmutable::now('UTC');

            if ($targetTask->run !== null && in_array((string) $targetTask->run->status, [
                AgentJobRun::STATUS_QUEUED,
                AgentJobRun::STATUS_STARTING,
                AgentJobRun::STATUS_RUNNING,
            ], true)) {
                $runTransitions->transition(
                    (int) $targetTask->run->id,
                    [AgentJobRun::STATUS_QUEUED, AgentJobRun::STATUS_STARTING, AgentJobRun::STATUS_RUNNING],
                    AgentJobRun::STATUS_STOPPING,
                );

                if (is_int($targetTask->run->pid) && $targetTask->run->pid > 0 && function_exists('posix_kill')) {
                    @posix_kill((int) $targetTask->run->pid, SIGTERM);
                }
            }

            $build['status'] = 'paused';
            $build['pause_reason'] = 'clarification';
            $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        }

        $targetTask->save();
        $this->saveBuildMetadata($session, $build);

        $writer = new InterrogationEventWriter($session);
        $writer->appendAnnotation([
            'type' => 'build_clarification',
            'task_id' => (int) $targetTask->id,
            'message' => $message,
        ]);

        if (($build['status'] ?? null) === 'running') {
            ExecuteInterrogationBuildJob::dispatch((int) $session->id);
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_clarify',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['task_id' => (int) $targetTask->id],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'session_id' => $session->id,
                'task_id' => (int) $targetTask->id,
                'build_status' => (string) ($build['status'] ?? 'ready'),
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
        $summary = $this->normalizedSummaryJson($session, true);
        if ($summary === []) {
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
            InterrogationSession::PHASE_BUILD_TASKS => InterrogationSession::STATUS_BUILD_TASKS,
            InterrogationSession::PHASE_BUILD_EXECUTION => InterrogationSession::STATUS_BUILD_EXECUTING,
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
            InterrogationSession::PHASE_SETUP, InterrogationSession::PHASE_DISCOVERY => InterrogationSession::STATUS_SETUP,
            InterrogationSession::PHASE_INTERROGATION => InterrogationSession::STATUS_INTERROGATING,
            InterrogationSession::PHASE_SUMMARY => InterrogationSession::STATUS_SUMMARIZING,
            InterrogationSession::PHASE_PLANNING => InterrogationSession::STATUS_PLANNING,
            InterrogationSession::PHASE_BUILD_TASKS => InterrogationSession::STATUS_BUILD_TASKS,
            InterrogationSession::PHASE_BUILD_EXECUTION => InterrogationSession::STATUS_BUILD_EXECUTING,
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

        if ((int) $session->phase === InterrogationSession::PHASE_INTERROGATION) {
            $session->cli_session_id = null;
            $session->save();
        }

        $pendingQuestionId = null;
        if ((int) $session->phase === InterrogationSession::PHASE_INTERROGATION) {
            $pendingQuestionId = $this->latestUnansweredQuestionId($session);
        }

        match ((int) $session->phase) {
            InterrogationSession::PHASE_SETUP, InterrogationSession::PHASE_DISCOVERY => ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_INTERROGATION => $pendingQuestionId !== null
                    ? null
                    : ExecuteInterrogationRoundJob::dispatch(
                        (int) $session->id,
                        'Retry current interrogation phase. Resume from the latest unanswered question before asking anything new.',
                        true
                    ),
            InterrogationSession::PHASE_SUMMARY => ExecuteInterrogationSummaryJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_PLANNING => ExecuteInterrogationPlanJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_BUILD_TASKS => GenerateInterrogationBuildTasksJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_BUILD_EXECUTION => ExecuteInterrogationBuildJob::dispatch((int) $session->id),
            default => ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id),
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
                'queued' => $pendingQuestionId === null,
                'pending_question_id' => $pendingQuestionId,
                'session_id' => $session->id,
                'status' => $session->status,
                'phase' => $session->phase,
            ],
        ], 202);
    }

    private function latestUnansweredQuestionId(InterrogationSession $session): ?string
    {
        $questions = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_QUESTION)
            ->orderByDesc('sequence')
            ->get();

        foreach ($questions as $questionEvent) {
            $payload = is_array($questionEvent->payload) ? $questionEvent->payload : [];
            if (! $this->isAnswerableQuestionPayload($payload)) {
                continue;
            }

            $questionId = trim((string) data_get($payload, 'question_id', ''));
            if ($questionId === '') {
                continue;
            }

            $answered = $session->events()
                ->where('event_type', InterrogationEvent::TYPE_ANSWER)
                ->whereRaw("payload->>'question_id' = ?", [$questionId])
                ->exists();

            if (! $answered) {
                return $questionId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isAnswerableQuestionPayload(array $payload): bool
    {
        if ((bool) ($payload['is_complete'] ?? false) === true) {
            return false;
        }

        if (((int) ($payload['progress_estimate'] ?? 0)) >= 100) {
            return false;
        }

        $text = strtolower(trim((string) ($payload['question_text'] ?? '')));

        if ($text === '') {
            return true;
        }

        if (preg_match('/\b(?:requirements\s+)?interrogation\s+is\s+now\s+complete\b/', $text) === 1) {
            return false;
        }

        return preg_match('/\binterrogation\s+completed\b/', $text) !== 1;
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
            'summary_json' => $this->normalizedSummaryJson($session, $includeLargePayloads),
            'plan_json' => $session->plan_json,
            'build' => $this->transformBuildState($session, $includeLargePayloads),
            'approved_at' => $this->toRfc3339Millis($session->approved_at),
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
     * @return array<string, mixed>
     */
    private function transformBuildState(InterrogationSession $session, bool $includeDetails): array
    {
        $build = $this->buildMetadata($session);
        $status = $this->normalizedBuildStatus($build);

        if (! $includeDetails) {
            return [
                'status' => $status,
                'summary' => [
                    'total' => (int) ($build['task_count'] ?? 0),
                    'pending' => null,
                    'in_progress' => null,
                    'blocked' => null,
                    'completed' => null,
                    'failed' => null,
                    'skipped' => null,
                ],
                'active_task_id' => isset($build['active_task_id']) ? (int) $build['active_task_id'] : null,
                'active_run_id' => isset($build['active_run_id']) ? (int) $build['active_run_id'] : null,
                'updated_at' => $build['updated_at'] ?? null,
            ];
        }

        $tasks = $session->buildTasks()->with('run')->ordered()->get();

        $summary = [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', InterrogationBuildTask::STATUS_PENDING)->count(),
            'in_progress' => $tasks->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)->count(),
            'blocked' => $tasks->where('status', InterrogationBuildTask::STATUS_BLOCKED)->count(),
            'completed' => $tasks->where('status', InterrogationBuildTask::STATUS_COMPLETED)->count(),
            'failed' => $tasks->where('status', InterrogationBuildTask::STATUS_FAILED)->count(),
            'skipped' => $tasks->where('status', InterrogationBuildTask::STATUS_SKIPPED)->count(),
        ];

        /** @var InterrogationBuildTask|null $activeTask */
        $activeTask = $tasks->firstWhere('status', InterrogationBuildTask::STATUS_IN_PROGRESS);
        if ($activeTask === null && isset($build['active_task_id'])) {
            $activeTask = $tasks->firstWhere('id', (int) $build['active_task_id']);
        }

        $activeRun = $activeTask?->run;
        if ($activeRun === null && isset($build['active_run_id'])) {
            $activeRun = AgentJobRun::query()->find((int) $build['active_run_id']);
        }

        $runMetadata = is_array($activeRun?->metadata_json) ? $activeRun->metadata_json : [];

        $flags = [
            'approval_required' => (bool) (($runMetadata['approval_required'] ?? null) ?? ($build['approval_required'] ?? false)),
            'approval_excerpt' => is_string($runMetadata['approval_excerpt'] ?? null)
                ? $runMetadata['approval_excerpt']
                : null,
            'rate_limit_detected' => (bool) (($runMetadata['rate_limit_detected'] ?? null) ?? ($build['rate_limit_detected'] ?? false)),
            'rate_limit_reset_at' => (string) ($runMetadata['rate_limit_reset_at'] ?? $runMetadata['rate_limit_hold_until'] ?? $build['rate_limit_reset_at'] ?? ''),
            'rate_limit_excerpt' => is_string($runMetadata['rate_limit_excerpt'] ?? null)
                ? $runMetadata['rate_limit_excerpt']
                : (is_string($build['rate_limit_excerpt'] ?? null) ? $build['rate_limit_excerpt'] : null),
        ];

        if ($flags['rate_limit_reset_at'] === '') {
            $flags['rate_limit_reset_at'] = null;
        }

        return [
            'status' => $status,
            'summary' => $summary,
            'tasks' => $tasks->map(fn (InterrogationBuildTask $task): array => $this->transformBuildTask($task))->values(),
            'active_task' => $activeTask !== null ? $this->transformBuildTask($activeTask) : null,
            'active_run' => $activeRun !== null ? $this->transformBuildRun($activeRun) : null,
            'flags' => $flags,
            'pause_reason' => isset($build['pause_reason']) ? (string) $build['pause_reason'] : null,
            'error' => isset($build['error']) ? (string) $build['error'] : null,
            'completion_summary' => isset($build['completion_summary']) ? (string) $build['completion_summary'] : null,
            'started_at' => isset($build['started_at']) ? (string) $build['started_at'] : null,
            'paused_at' => isset($build['paused_at']) ? (string) $build['paused_at'] : null,
            'finished_at' => isset($build['finished_at']) ? (string) $build['finished_at'] : null,
            'updated_at' => isset($build['updated_at']) ? (string) $build['updated_at'] : null,
            'active_task_id' => $activeTask?->id,
            'active_run_id' => $activeRun?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBuildTask(InterrogationBuildTask $task): array
    {
        return [
            'id' => $task->id,
            'sequence' => $task->sequence,
            'title' => $task->title,
            'description' => $task->description,
            'instructions_markdown' => $task->instructions_markdown,
            'status' => $task->status,
            'attempt_count' => $task->attempt_count,
            'agent_job_run_id' => $task->agent_job_run_id,
            'last_error' => $task->last_error,
            'metadata_json' => $task->metadata_json,
            'started_at' => $this->toRfc3339Millis($task->started_at),
            'finished_at' => $this->toRfc3339Millis($task->finished_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBuildRun(AgentJobRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'agent_job_id' => $run->agent_job_id,
            'trigger_type' => $run->trigger_type,
            'started_at' => $this->toRfc3339Millis($run->started_at),
            'finished_at' => $this->toRfc3339Millis($run->finished_at),
            'error_code' => $run->error_code,
            'error_summary' => $run->error_summary,
            'metadata_json' => $run->metadata_json,
            'log_tail' => $this->runLogTail($run),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runLogTail(AgentJobRun $run): array
    {
        return AgentRunEvent::query()
            ->where('agent_job_run_id', $run->id)
            ->orderByDesc('sequence')
            ->limit(60)
            ->get()
            ->sortBy('sequence')
            ->values()
            ->map(function (AgentRunEvent $event): array {
                return [
                    'sequence' => $event->sequence,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'event_ts' => $this->toRfc3339Millis($event->event_ts),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        return is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function saveBuildMetadata(InterrogationSession $session, array $build): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['build'] = $build;
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function normalizedBuildStatus(array $build): string
    {
        $status = trim((string) ($build['status'] ?? ''));

        if ($status !== '') {
            return $status;
        }

        return 'idle';
    }

    private function hasMeaningfulPlan(InterrogationSession $session): bool
    {
        if (! is_array($session->plan_json)) {
            return false;
        }

        $plan = $session->plan_json;
        $markdown = trim((string) ($plan['plan_markdown'] ?? ''));
        if ($markdown !== '' && preg_match('/^plan not generated yet\.?$/i', $markdown) !== 1) {
            return true;
        }

        return (is_array($plan['sections'] ?? null) && $plan['sections'] !== [])
            || (is_array($plan['risks'] ?? null) && $plan['risks'] !== [])
            || (is_array($plan['assumptions'] ?? null) && $plan['assumptions'] !== []);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildAnswerMessage(array $validated): string
    {
        $questionId = (string) ($validated['question_id'] ?? 'unknown');
        $answerType = (string) $validated['answer_type'];

        if ($answerType === 'choice') {
            $multiple = array_values(array_filter(
                (array) ($validated['selected_options'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            ));

            if ($multiple !== []) {
                return sprintf('Question %s answered with choices: %s', $questionId, implode('; ', $multiple));
            }

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

    /**
     * @return array<string, mixed>
     */
    private function normalizedSummaryJson(InterrogationSession $session, bool $persist): array
    {
        if (! is_array($session->summary_json) || $session->summary_json === []) {
            return [];
        }

        $normalizer = new SummaryPayloadNormalizer;
        $normalized = $normalizer->normalize($session->summary_json);
        $normalized = $this->reconcileOpenQuestionsWithAnsweredEvents($session, $normalized, $persist);

        if ($persist && $normalized !== $session->summary_json) {
            $session->summary_json = $normalized;
            $session->save();
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function reconcileOpenQuestionsWithAnsweredEvents(InterrogationSession $session, array $summary, bool $eager): array
    {
        $openQuestions = is_array($summary['open_questions'] ?? null) ? array_values($summary['open_questions']) : [];
        if ($openQuestions === []) {
            return $summary;
        }

        // Keep index/list payloads cheap; perform event-based reconciliation on detail/sensitive flows only.
        if (! $eager) {
            return $summary;
        }

        $events = $session->events()->orderBy('sequence')->get();
        if ($events->isEmpty()) {
            return $summary;
        }

        $questionsById = [];
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_QUESTION) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $questionText = trim((string) ($payload['question_text'] ?? ''));
            if ($questionText === '') {
                continue;
            }

            $questionsById[$questionId] = $questionText;
        }

        if ($questionsById === []) {
            return $summary;
        }

        $resolvedFingerprints = [];
        $resolvedOpenQuestionIndexes = [];
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_ANSWER) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
            $answerType = strtolower(trim((string) ($payload['answer_type'] ?? '')));
            if ($answerType === 'skip') {
                continue;
            }

            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '' || ! isset($questionsById[$questionId])) {
                continue;
            }

            $fingerprint = $this->questionFingerprint($questionsById[$questionId]);
            if ($fingerprint !== '') {
                $resolvedFingerprints[] = $fingerprint;
            }

            $ordinal = $this->extractOpenQuestionOrdinal($questionsById[$questionId]);
            if ($ordinal !== null) {
                $resolvedOpenQuestionIndexes[] = $ordinal - 1;
            }
        }

        if ($resolvedFingerprints === [] && $resolvedOpenQuestionIndexes === []) {
            return $summary;
        }

        $resolvedFingerprints = array_values(array_unique($resolvedFingerprints));
        $resolvedOpenQuestionIndexes = array_values(array_unique(array_filter(
            $resolvedOpenQuestionIndexes,
            static fn (int $index): bool => $index >= 0
        )));
        $resolvedOpenQuestionIndexLookup = array_fill_keys($resolvedOpenQuestionIndexes, true);

        $remaining = [];
        foreach ($openQuestions as $openIndex => $openQuestion) {
            if (! is_string($openQuestion) || trim($openQuestion) === '') {
                continue;
            }

            if (isset($resolvedOpenQuestionIndexLookup[$openIndex])) {
                continue;
            }

            $openFingerprint = $this->questionFingerprint($openQuestion);
            if ($openFingerprint === '') {
                $remaining[] = $openQuestion;

                continue;
            }

            $isResolved = false;
            foreach ($resolvedFingerprints as $resolved) {
                if ($resolved === $openFingerprint || str_contains($resolved, $openFingerprint) || str_contains($openFingerprint, $resolved)) {
                    $isResolved = true;
                    break;
                }
            }

            if (! $isResolved) {
                $remaining[] = $openQuestion;
            }
        }

        $summary['open_questions'] = array_values(array_unique($remaining));

        return $summary;
    }

    private function questionFingerprint(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\r\n|\r/', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/^\*+\s*/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^open\s+question\s+\d+\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9 ]/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return '';
        }

        $parts = explode('?', $normalized, 2);

        return trim($parts[0]);
    }

    private function extractOpenQuestionOrdinal(string $value): ?int
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/open\s+question\s+(\d+)\s*:/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) ($matches[1] ?? 0);

        return $ordinal > 0 ? $ordinal : null;
    }
}
