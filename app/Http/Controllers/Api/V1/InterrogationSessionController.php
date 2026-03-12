<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Interrogation\CreateInterrogationSessionAction;
use App\Actions\Interrogation\FindInterrogationSessionAction;
use App\Actions\Interrogation\ListInterrogationEventsAction;
use App\Actions\Interrogation\ListInterrogationSessionsAction;
use App\Actions\Interrogation\PruneInvalidQuestionEventsAction;
use App\Actions\Interrogation\UpdateInterrogationSessionAction;
use App\Actions\Interrogation\UpdateSessionAnnotationAction;
use App\Actions\Interrogation\UpdateSessionStaleMarkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\RequestPlanRevisionRequest;
use App\Http\Requests\Interrogation\StoreInterrogationSessionRequest;
use App\Http\Requests\Interrogation\SubmitAnswerRequest;
use App\Http\Requests\Interrogation\UpdateAnnotationRequest;
use App\Http\Requests\Interrogation\UpdateInterrogationSessionRequest;
use App\Http\Resources\ComplianceSummaryResource;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Services\Interrogation\InterrogationApprovalService;
use App\Services\Interrogation\InterrogationBuildService;
use App\Services\Interrogation\InterrogationExportService;
use App\Services\Interrogation\InterrogationPlanService;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Interrogation\GitOperationsService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SummaryOpenQuestionQueueService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class InterrogationSessionController extends Controller
{
    public function __construct(
        private readonly InterrogationBuildService $buildService,
        private readonly InterrogationPlanService $planService,
        private readonly InterrogationExportService $exportService,
        private readonly InterrogationApprovalService $approvalService,
        private readonly FindInterrogationSessionAction $findSession,
        private readonly ListInterrogationSessionsAction $listSessions,
        private readonly CreateInterrogationSessionAction $createSession,
        private readonly UpdateInterrogationSessionAction $updateSession,
        private readonly UpdateSessionAnnotationAction $updateAnnotation,
        private readonly UpdateSessionStaleMarkAction $updateStaleMark,
        private readonly PruneInvalidQuestionEventsAction $pruneInvalidQuestions,
        private readonly ListInterrogationEventsAction $listEvents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $deleted = $request->string('deleted')->toString();
        $status = trim($request->string('status')->toString());
        $type = trim($request->string('type')->toString());
        $runner = trim($request->string('runner')->toString());
        $search = trim($request->string('q')->toString());
        $perPage = (int) $request->integer('per_page', 25);

        $sessions = $this->listSessions->execute(
            userId: $request->user()->id,
            deleted: $deleted,
            status: $status,
            type: $type,
            runner: $runner,
            search: $search,
            perPage: $perPage,
        );

        /** @var \Illuminate\Support\Collection<int, InterrogationSession> $items */
        $items = collect(array_values($sessions->items()));
        $data = $items
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
        $session = $this->findSession->execute($request->user()->id, $id, withTrashed: true);
        $includeEvents = $request->boolean('include_events', false);

        $data = $this->transformSession($session, true);

        if ($includeEvents) {
            $data['events'] = $session->events()
                ->orderByDesc('sequence')
                ->limit(100)
                ->get()
                ->sortBy('sequence')
                ->values()
                ->map(fn (InterrogationEvent $event): array => [
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

        $metadata = ['source' => 'ui'];
        $gitInput = $validated['git'] ?? null;
        if (is_array($gitInput) && $gitInput !== []) {
            $metadata['git'] = $this->normalizeGitSettings($gitInput);
        }
        $buildSettingsInput = $validated['build_settings'] ?? null;
        if (is_array($buildSettingsInput) && $buildSettingsInput !== []) {
            $metadata['build_settings'] = $this->normalizeBuildSettings($buildSettingsInput);
        }

        $session = $this->createSession->execute($request->user()->id, $validated, $metadata);

        $afterSnapshot = $session->only(['id', 'name', 'runner_type', 'model', 'project_directory', 'interrogation_type', 'status', 'phase']);
        $changedFields = ['name', 'runner_type', 'model', 'project_directory', 'interrogation_type', 'feature_brief', 'status', 'phase'];
        if (isset($metadata['git'])) {
            $changedFields[] = 'git_settings';
            $afterSnapshot['git_settings'] = $session->gitSettings();
        }
        if (isset($metadata['build_settings'])) {
            $changedFields[] = 'build_settings';
            $afterSnapshot['build_settings'] = $session->buildSettings();
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.create',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: $changedFields,
            before: null,
            after: $afterSnapshot,
        );

        return response()->json([
            'data' => $this->transformSession($session, false),
        ], 202);
    }

    public function update(
        UpdateInterrogationSessionRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id, withTrashed: true);
        $validated = $request->validated();
        $before = [
            'name' => $session->name,
            'feature_brief' => $session->feature_brief,
            'model' => $session->model,
            'git_settings' => $session->gitSettings(),
            'build_settings' => $session->buildSettings(),
        ];

        $session = $this->updateSession->execute($session, $validated);

        $changedFields = [];
        if ($before['name'] !== $session->name) {
            $changedFields[] = 'name';
        }
        if ($before['feature_brief'] !== $session->feature_brief) {
            $changedFields[] = 'feature_brief';
        }
        if ($before['model'] !== $session->model) {
            $changedFields[] = 'model';
        }
        if ($before['git_settings'] !== $session->gitSettings()) {
            $changedFields[] = 'git_settings';
        }
        if ($before['build_settings'] !== $session->buildSettings()) {
            $changedFields[] = 'build_settings';
        }

        if ($changedFields !== []) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'interrogation.session.update',
                targetType: 'interrogation_session',
                targetId: (int) $session->id,
                ownerUserId: (int) $session->user_id,
                changedFields: $changedFields,
                before: $before,
                after: [
                    'name' => $session->name,
                    'feature_brief' => $session->feature_brief,
                    'model' => $session->model,
                    'git_settings' => $session->gitSettings(),
                    'build_settings' => $session->buildSettings(),
                ],
            );
        }

        return response()->json([
            'data' => $this->transformSession($session, false),
        ]);
    }

    public function gitBranchesPreview(Request $request): JsonResponse
    {
        $projectDirectory = (string) ($request->query('project_directory') ?? '');

        return $this->resolveGitBranches($projectDirectory);
    }

    public function gitBranches(Request $request, int $id): JsonResponse
    {
        $session = $this->findSession->execute($request->user()->id, $id);
        $projectDirectory = (string) $session->project_directory;

        return $this->resolveGitBranches($projectDirectory);
    }

    private function resolveGitBranches(string $projectDirectory): JsonResponse
    {
        if ($projectDirectory === '' || ! is_dir($projectDirectory)) {
            return ErrorEnvelope::make('INVALID_DIRECTORY', 'Project directory does not exist.', 422);
        }

        $gitDir = rtrim($projectDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.git';
        if (! is_dir($gitDir) && ! is_file($gitDir)) {
            return ErrorEnvelope::make('NOT_A_GIT_REPO', 'Project directory is not a git repository.', 422);
        }

        $process = new Process(
            ['git', 'branch', '--list', '--format=%(refname:short)'],
            $projectDirectory
        );
        $process->setTimeout(10);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return ErrorEnvelope::make('GIT_COMMAND_FAILED', 'Failed to list git branches: '.$e->getMessage(), 500);
        }

        if (! $process->isSuccessful()) {
            return ErrorEnvelope::make('GIT_COMMAND_FAILED', 'Failed to list git branches.', 500);
        }

        $output = trim($process->getOutput());
        $branches = $output !== '' ? array_values(array_filter(
            array_map('trim', explode("\n", $output)),
            fn (string $b): bool => $b !== ''
        )) : [];

        $headProcess = new Process(
            ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            $projectDirectory
        );
        $headProcess->setTimeout(5);
        $headProcess->run();
        $currentBranch = trim($headProcess->getOutput());

        return response()->json([
            'data' => [
                'branches' => $branches,
                'current_branch' => $currentBranch !== '' ? $currentBranch : null,
                'project_directory' => $projectDirectory,
            ],
        ]);
    }

    public function advancePreDiscovery(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if ((int) $session->phase === InterrogationSession::PHASE_TECH_STACK_SETUP) {
            return $this->startDiscovery($request, $id, $transitions, $auditLogger);
        }

        if ((int) $session->phase > InterrogationSession::PHASE_TECH_STACK_SETUP) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Pre-discovery setup is already complete.', 409);
        }

        $fromPhase = (int) $session->phase;
        $toPhase = match ($fromPhase) {
            InterrogationSession::PHASE_SETUP => InterrogationSession::PHASE_TECH_STACK_SETUP,
            InterrogationSession::PHASE_PROVIDER_SETUP => InterrogationSession::PHASE_TECH_STACK_SETUP,
            default => InterrogationSession::PHASE_TECH_STACK_SETUP,
        };

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            $fromPhase,
            $toPhase,
            InterrogationSession::STATUS_SETUP,
            [InterrogationSession::STATUS_SETUP, InterrogationSession::STATUS_PAUSED],
        );

        if (! $moved) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while advancing pre-discovery setup.', 409);
        }

        $session->refresh();

        (new InterrogationEventWriter($session))->appendPhaseTransition(
            $fromPhase,
            $toPhase,
            (string) $session->status,
            [
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'source' => 'pre_discovery_setup',
            ],
        );

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.advance_pre_discovery',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase'],
            before: ['phase' => $fromPhase],
            after: ['phase' => $toPhase],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'session_id' => $session->id,
                'phase' => $session->phase,
                'status' => $session->status,
            ],
        ]);
    }

    public function startDiscovery(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if ((int) $session->phase < InterrogationSession::PHASE_TECH_STACK_SETUP) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Pre-discovery setup must reach tech stack step before starting discovery.', 409);
        }

        if ((int) $session->phase >= InterrogationSession::PHASE_DISCOVERY) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Discovery has already started for this session.', 409);
        }

        $fromPhase = (int) $session->phase;

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            $fromPhase,
            InterrogationSession::PHASE_DISCOVERY,
            InterrogationSession::STATUS_SETUP,
            [InterrogationSession::STATUS_SETUP, InterrogationSession::STATUS_PAUSED],
        );

        if (! $moved) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while starting discovery.', 409);
        }

        $session->refresh();

        (new InterrogationEventWriter($session))->appendPhaseTransition(
            $fromPhase,
            InterrogationSession::PHASE_DISCOVERY,
            (string) $session->status,
            [
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'source' => 'pre_discovery_start_discovery',
            ],
        );

        ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.start_discovery',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['phase', 'status'],
            before: ['phase' => $fromPhase, 'status' => InterrogationSession::STATUS_SETUP],
            after: ['phase' => InterrogationSession::PHASE_DISCOVERY, 'status' => InterrogationSession::STATUS_SETUP],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
                'phase' => $session->phase,
                'status' => $session->status,
            ],
        ], 202);
    }

    public function submitAnswer(
        SubmitAnswerRequest $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true)) {
            if ($this->shouldAutoRecoverInterruptedFailedSession($session)) {
                $recovered = $transitions->transition(
                    (int) $session->id,
                    [InterrogationSession::STATUS_FAILED],
                    InterrogationSession::STATUS_INTERROGATING,
                    [
                        'phase' => InterrogationSession::PHASE_INTERROGATION,
                        'error_code' => null,
                        'error_summary' => null,
                        'finished_at' => null,
                    ],
                );

                if (! $recovered) {
                    return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while recovering from interruption.', 409);
                }

                $session->refresh();
            } else {
                return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is terminal and cannot accept answers.', 409);
            }
        }

        $validated = $request->validated();
        $queueHandled = $this->handleSummaryOpenQuestionQueueAnswer($session, $validated, $transitions);

        if (! $queueHandled) {
            $message = $this->buildAnswerMessage($validated);
            ExecuteInterrogationRoundJob::dispatch((int) $session->id, $message, false, $validated);
        }

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
        $session = $this->findSession->execute($request->user()->id, $id);

        $validated = $request->validated();
        $questionId = (string) ($validated['question_id'] ?? 'unknown');
        $message = 'Edited answer for question '.$questionId.'. '.$this->buildAnswerMessage($validated);

        $this->updateStaleMark->execute($session, $questionId);

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
        $session = $this->findSession->execute($request->user()->id, $id);
        $summary = $this->planService->normalizedSummaryJson($session, true);

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

        $confirmed = $this->planService->confirmSummary($session, $transitions);

        if (! $confirmed) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while confirming summary.', 409);
        }

        $session->refresh();

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
        $session = $this->findSession->execute($request->user()->id, $id);

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in summary phase.', 409);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
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
        $session = $this->findSession->execute($request->user()->id, $id);

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in summary phase.', 409);
        }

        $validated = $request->validate([
            'focus' => ['nullable', 'string', 'max:4000'],
            'revisit_question_id' => ['nullable', 'string', 'max:120'],
        ]);

        $focus = trim((string) ($validated['focus'] ?? ''));
        $revisitQuestionId = trim((string) ($validated['revisit_question_id'] ?? ''));
        $summary = $this->planService->normalizedSummaryJson($session, true);
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
        $this->planService->invalidateDerivedArtifactsForReinterrogation($session);

        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_INTERROGATION,
            (string) $session->status,
            ['at' => CarbonImmutable::now('UTC')->toIso8601String(), 'reopened_by_user_id' => $request->user()->id],
        );

        if ($revisitQuestionId !== '') {
            $this->clearSummaryOpenQuestionQueue($session);
        } else {
            $normalizedOpenQuestions = $this->normalizeOpenQuestionList($openQuestions);
            if ($normalizedOpenQuestions !== []) {
                $queue = $this->buildSummaryOpenQuestionQueue($normalizedOpenQuestions, $focus);
                $this->persistSummaryOpenQuestionQueue($session, $queue);
                $this->dispatchNextSummaryOpenQuestionFromQueue($session);
            } else {
                $this->clearSummaryOpenQuestionQueue($session);

                $prompt = 'Continue interrogation from summary. Ask the NEXT single unresolved question only. '
                    .'Do not batch multiple questions into one response.';

                if ($focus !== '') {
                    $prompt .= "\nAdditional user focus:\n".$focus;
                }

                ExecuteInterrogationRoundJob::dispatch((int) $session->id, $prompt, true);
            }
        }

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
        $session = $this->findSession->execute($request->user()->id, $id);

        if ((int) $session->phase < InterrogationSession::PHASE_PLANNING || in_array($session->status, [InterrogationSession::STATUS_FAILED], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not ready to generate a plan.', 409);
        }

        $this->planService->generatePlan($session);

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

    public function regeneratePlan(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan regeneration is only available during planning phase.', 409);
        }

        $summary = is_array($session->summary_json) ? $session->summary_json : [];
        if ($summary === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary must be present before plan regeneration.', 409);
        }

        $this->planService->regeneratePlan($session, (int) $request->user()->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.regenerate_plan',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['revision_prompt'],
            before: null,
            after: ['action' => 'regenerate_from_summary'],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => $session->id,
                'revision_status' => 'queued',
                'message' => 'Plan regeneration queued. Rebuilding from summary context now.',
            ],
        ], 202);
    }

    public function approvePlan(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if ($this->approvalService->isPlanAlreadyApproved($session)) {
            return response()->json([
                'data' => [
                    'approved' => true,
                    'session_id' => $session->id,
                    'approved_at' => $this->toRfc3339Millis($session->approved_at),
                ],
            ]);
        }

        $error = $this->approvalService->validatePlanApprovalEligibility($session);
        if ($error !== null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $error, 409);
        }

        $result = $this->planService->approvePlan($session, $transitions, (int) $request->user()->id);

        if ($result === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while approving plan.', 409);
        }

        $session->refresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.approve_plan',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['approved_at', 'phase', 'status'],
            before: ['approved_at' => null, 'phase' => InterrogationSession::PHASE_PLANNING, 'status' => $session->getOriginal('status')],
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
        $session = $this->findSession->execute($request->user()->id, $id);
        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan revision is only available during planning phase.', 409);
        }

        $validated = $request->validated();

        $this->planService->requestRevision($session, $validated);

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
                'revision_status' => 'queued',
                'message' => 'Plan revision queued. Revising plan now.',
            ],
        ], 202);
    }

    public function generateBuildTasks(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if ($error = $this->buildService->checkGenerateTasksPreconditions($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $error, 409);
        }

        $this->buildService->generateBuildTasks($session, $request);

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

    public function storeBuildTask(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        if ($conflict = $this->buildService->buildTaskEditingConflict($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $conflict, 409);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:12000'],
            'instructions_markdown' => ['nullable', 'string', 'max:120000'],
        ]);

        $task = $this->buildService->storeBuildTask($session, $validated);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_task.create',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['build.tasks'],
            before: null,
            after: [
                'task_id' => (int) $task->id,
                'sequence' => (int) $task->sequence,
            ],
        );

        return response()->json([
            'data' => $this->buildService->transformBuildTask($task),
        ], 201);
    }

    public function reorderBuildTasks(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        if ($conflict = $this->buildService->buildTaskEditingConflict($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $conflict, 409);
        }

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'distinct'],
        ]);

        $requestedIds = array_values(array_map(static fn (mixed $value): int => (int) $value, (array) $validated['task_ids']));

        $beforeOrder = $session->buildTasks()
            ->ordered()
            ->get(['id'])
            ->map(static fn (InterrogationBuildTask $task): int => (int) $task->id)
            ->all();

        $this->buildService->reorderBuildTasks($session, $requestedIds);

        $afterOrder = $session->buildTasks()
            ->ordered()
            ->get(['id'])
            ->map(static fn (InterrogationBuildTask $task): int => (int) $task->id)
            ->all();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_task.reorder',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['build.tasks'],
            before: ['task_ids' => $beforeOrder],
            after: ['task_ids' => $afterOrder],
        );

        return response()->json([
            'data' => [
                'tasks' => $session->buildTasks()
                    ->ordered()
                    ->get()
                    ->map(fn (InterrogationBuildTask $task): array => $this->buildService->transformBuildTask($task))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function updateBuildTask(
        Request $request,
        int $id,
        int $taskId,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        if ($conflict = $this->buildService->buildTaskEditingConflict($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $conflict, 409);
        }

        /** @var InterrogationBuildTask $task */
        $task = $session->buildTasks()->whereKey($taskId)->firstOrFail();

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:12000'],
            'instructions_markdown' => ['sometimes', 'nullable', 'string', 'max:120000'],
        ]);

        if (! array_key_exists('title', $validated) && ! array_key_exists('description', $validated) && ! array_key_exists('instructions_markdown', $validated)) {
            throw ValidationException::withMessages([
                'task' => 'At least one field must be provided.',
            ]);
        }

        $before = [
            'title' => (string) $task->title,
            'description' => $task->description,
            'instructions_markdown' => $task->instructions_markdown,
            'status' => (string) $task->status,
        ];

        $task = $this->buildService->updateBuildTask($task, $session, $validated);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_task.update',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['build.tasks'],
            before: $before,
            after: [
                'task_id' => (int) $task->id,
                'title' => (string) $task->title,
                'description' => $task->description,
                'instructions_markdown' => $task->instructions_markdown,
                'status' => (string) $task->status,
            ],
        );

        return response()->json([
            'data' => $this->transformBuildTask($task->fresh()),
        ]);
    }

    public function destroyBuildTask(
        Request $request,
        int $id,
        int $taskId,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        if ($conflict = $this->buildService->buildTaskEditingConflict($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $conflict, 409);
        }

        /** @var InterrogationBuildTask $task */
        $task = $session->buildTasks()->whereKey($taskId)->firstOrFail();
        $deletedTaskId = (int) $task->id;

        $this->buildService->destroyBuildTask($task, $session);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_task.delete',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['build.tasks'],
            before: ['task_id' => $deletedTaskId],
            after: ['task_id' => $deletedTaskId, 'deleted' => true],
        );

        return response()->json([
            'data' => [
                'deleted' => true,
                'task_id' => $deletedTaskId,
            ],
        ]);
    }

    public function regenerateBuildTask(
        Request $request,
        int $id,
        int $taskId,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        if ($conflict = $this->buildService->buildTaskEditingConflict($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $conflict, 409);
        }

        /** @var InterrogationBuildTask $task */
        $task = $session->buildTasks()->whereKey($taskId)->firstOrFail();

        $validated = $request->validate([
            'amend_notes' => ['required', 'string', 'max:6000'],
            'additional_context' => ['nullable', 'string', 'max:6000'],
        ]);

        $amendNotes = trim((string) ($validated['amend_notes'] ?? ''));
        $additionalContext = $this->buildService->normalizedNullableText($validated['additional_context'] ?? null);
        if ($amendNotes === '') {
            throw ValidationException::withMessages([
                'amend_notes' => 'Amend notes are required to regenerate a task.',
            ]);
        }

        $this->buildService->regenerateBuildTask($task, $session, $amendNotes, $additionalContext, (int) $request->user()->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_task.regenerate',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['build.tasks'],
            before: ['task_id' => (int) $task->id],
            after: ['task_id' => (int) $task->id, 'queued' => true],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'queued' => true,
                'session_id' => (int) $session->id,
                'task_id' => (int) $task->id,
            ],
        ], 202);
    }

    public function approveBuildTasks(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if ($error = $this->approvalService->validateBuildTaskApprovalEligibility($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $error, 409);
        }

        $result = $this->buildService->approveBuildTasks($session, (int) $request->user()->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.approve_build_tasks',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: [
                'build_tasks_approved_at' => $result['tasks_approved_at'],
                'task_provider_sync_queued' => $result['task_provider_sync_queued'],
            ],
        );

        return response()->json([
            'data' => [
                'approved' => true,
                'session_id' => $session->id,
                'tasks_approved_at' => $result['tasks_approved_at'],
                'task_provider_sync_queued' => $result['task_provider_sync_queued'],
            ],
        ], 202);
    }

    public function startBuild(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        $validated = $request->validate([
            'restart_failed' => ['nullable', 'boolean'],
            'restart_all' => ['nullable', 'boolean'],
        ]);

        $restartFailed = (bool) ($validated['restart_failed'] ?? false);
        $restartAll = (bool) ($validated['restart_all'] ?? false);

        if ($error = $this->approvalService->validateBuildStartEligibility($session, $restartFailed || $restartAll)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $error, 409);
        }

        $this->buildService->startBuild($session, $transitions, $restartFailed, $restartAll);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.start_build',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['build_status' => 'running', 'restart_failed' => $restartFailed, 'restart_all' => $restartAll],
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
        $session = $this->findSession->execute($request->user()->id, $id);

        if ($error = $this->buildService->checkPauseBuildPreconditions($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $error, 409);
        }

        if ($this->buildService->isBuildAlreadyPaused($session)) {
            return response()->json([
                'data' => [
                    'accepted' => true,
                    'session_id' => $session->id,
                    'build_status' => 'paused',
                    'already_paused' => true,
                ],
            ]);
        }

        $this->buildService->pauseBuild($session, $runTransitions);

        $activeTask = $session->buildTasks()
            ->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)
            ->orderBy('sequence')
            ->first();

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
        $session = $this->findSession->execute($request->user()->id, $id);

        if ($error = $this->buildService->checkResumeBuildPreconditions($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', $error, 409);
        }

        $this->buildService->resumeBuild($session, $transitions);

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
        $session = $this->findSession->execute($request->user()->id, $id);

        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build clarification is only available during build execution.', 409);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:6000'],
            'task_id' => ['nullable', 'integer'],
        ]);

        $message = trim((string) $validated['message']);

        $targetTaskId = isset($validated['task_id']) ? (int) $validated['task_id'] : null;
        /** @var InterrogationBuildTask|null $targetTask */
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

        $result = $this->buildService->clarifyBuild($session, $targetTask, $message, (int) $request->user()->id, $runTransitions);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.build_clarify',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: ['task_id' => $result['task_id']],
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'session_id' => $session->id,
                'task_id' => $result['task_id'],
                'build_status' => $result['build_status'],
            ],
        ], 202);
    }

    public function taskDiff(
        Request $request,
        int $id,
        int $taskId,
        GitOperationsService $gitService,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        /** @var InterrogationBuildTask|null $task */
        $task = $session->buildTasks()->whereKey($taskId)->first();

        if ($task === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Build task not found.', 404);
        }

        $taskMeta = is_array($task->metadata_json) ? $task->metadata_json : [];
        $baselineHead = isset($taskMeta['git_baseline_head']) ? (string) $taskMeta['git_baseline_head'] : null;

        if ($baselineHead === null || $baselineHead === '') {
            return response()->json([
                'data' => ['available' => false, 'files' => []],
            ]);
        }

        $worktreePath = isset($taskMeta['git_worktree_path']) ? (string) $taskMeta['git_worktree_path'] : null;
        $diff = $gitService->getTaskDiff((string) $session->project_directory, $baselineHead, $worktreePath);

        return response()->json([
            'data' => $diff,
        ]);
    }

    public function artefactContent(
        Request $request,
        int $id,
        int $taskId,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        /** @var InterrogationBuildTask|null $task */
        $task = $session->buildTasks()->whereKey($taskId)->first();

        if ($task === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Build task not found.', 404);
        }

        $path = (string) $request->query('path', '');

        if ($path === '') {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The path parameter is required.', 422);
        }

        if (mb_strlen($path) > 1024) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The path parameter must not exceed 1024 characters.', 422);
        }

        $projectDir = rtrim((string) $session->project_directory, DIRECTORY_SEPARATOR);
        $resolvedPath = realpath($projectDir.DIRECTORY_SEPARATOR.$path);

        if ($resolvedPath === false || ! str_starts_with($resolvedPath, $projectDir.DIRECTORY_SEPARATOR)) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The path is outside the project directory.', 422);
        }

        if (! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            return ErrorEnvelope::make('NOT_FOUND', 'The artefact file was not found or is not readable.', 404);
        }

        $maxSize = 512 * 1024; // 512KB
        $size = filesize($resolvedPath);
        $truncated = $size > $maxSize;
        $content = file_get_contents($resolvedPath, false, null, 0, $maxSize);

        return response()->json([
            'data' => [
                'content' => $content,
                'truncated' => $truncated,
                'size' => $size,
                'path' => $path,
            ],
        ]);
    }

    public function updateAnnotation(
        UpdateAnnotationRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        $validated = $request->validated();

        $this->updateAnnotation->execute($session, (string) $validated['key'], $validated['value'] ?? null);

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

    public function exportSummary(Request $request, int $id): JsonResponse
    {
        $session = $this->findSession->execute($request->user()->id, $id);
        $summary = $this->planService->normalizedSummaryJson($session, true);
        if ($summary === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary is not available for export.', 409);
        }

        $path = $this->exportService->exportSummary($session);

        return response()->json([
            'data' => [
                'path' => $path,
            ],
        ]);
    }

    public function exportPlan(Request $request, int $id): JsonResponse
    {
        $session = $this->findSession->execute($request->user()->id, $id);

        if (! is_array($session->plan_json) || $session->plan_json === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan is not available for export.', 409);
        }

        $path = $this->exportService->exportPlan($session);

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
        $session = $this->findSession->execute($request->user()->id, $id);

        $result = $this->approvalService->pause($session, $transitions);

        if ($result === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be paused from its current state.', 409);
        }

        return response()->json(['data' => $result]);
    }

    public function resume(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);

        if (! in_array($session->status, InterrogationSession::RESUMABLE_STATUSES, true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be resumed from its current state.', 409);
        }

        $result = $this->approvalService->resume($session, $transitions);

        if ($result === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while resuming.', 409);
        }

        return response()->json(['data' => $result]);
    }

    public function retry(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id, withTrashed: true);

        if ($session->trashed()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is deleted. Restore it before retrying.', 409);
        }

        if ($session->status === InterrogationSession::STATUS_COMPLETED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is already completed. Use plan revision for updates.', 409);
        }

        $result = $this->approvalService->retry($session, $transitions);

        if ($result === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be retried from its current state.', 409);
        }

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
            'data' => $result,
        ], 202);
    }

    public function restartFromBeginning(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id, withTrashed: true);

        if ($session->trashed()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is deleted. Restore it before restarting.', 409);
        }

        $result = $this->approvalService->restartFromBeginning($session);

        $session->refresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.restart_from_beginning',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['status', 'phase', 'events', 'summary_json', 'plan_json', 'annotations_json', 'metadata_json', 'approved_at', 'error_code', 'error_summary', 'started_at', 'finished_at'],
            before: null,
            after: [
                'status' => $session->status,
                'phase' => $session->phase,
                'events' => 0,
                'summary_json' => [],
                'plan_json' => [],
                'annotations_json' => [],
                'metadata_json' => $session->metadata_json,
                'approved_at' => $this->toRfc3339Millis($session->approved_at),
                'error_code' => $session->error_code,
                'error_summary' => $session->error_summary,
                'started_at' => $this->toRfc3339Millis($session->started_at),
                'finished_at' => $this->toRfc3339Millis($session->finished_at),
            ],
        );

        return response()->json([
            'data' => $result,
        ], 202);
    }

    private function shouldAutoRecoverInterruptedFailedSession(InterrogationSession $session): bool
    {
        if ($session->status !== InterrogationSession::STATUS_FAILED) {
            return false;
        }

        if ((int) $session->phase !== InterrogationSession::PHASE_INTERROGATION) {
            return false;
        }

        if ((string) $session->error_code !== 'ROUND_RUNTIME_EXCEPTION') {
            return false;
        }

        $summary = (string) ($session->error_summary ?? '');

        return str_contains($summary, 'signaled with signal "2"')
            || str_contains($summary, 'signaled with signal "15"');
    }

    public function cleanupInvalidQuestions(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $session = $this->findSession->execute($request->user()->id, $id);
        $pruned = $this->pruneInvalidQuestions->execute($session);
        $queue = $this->sanitizeSummaryOpenQuestionQueue($session);
        $dispatched = false;

        if ($queue['removed_active_open_question'] === true
            && (int) $session->phase === InterrogationSession::PHASE_INTERROGATION
            && (string) $session->status === InterrogationSession::STATUS_INTERROGATING
        ) {
            $dispatched = $this->dispatchNextSummaryOpenQuestionFromQueue($session);
        }

        $result = [
            ...$pruned,
            ...$queue,
            'queued_next_open_question' => $dispatched,
        ];

        $session->refresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.cleanup_invalid_questions',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['events', 'metadata_json'],
            before: null,
            after: $result,
        );

        return response()->json([
            'data' => [
                'accepted' => true,
                'session_id' => $session->id,
                'removed_question_events' => $result['removed_question_events'],
                'removed_answer_events' => $result['removed_answer_events'],
                'removed_question_ids' => $result['removed_question_ids'],
                'removed_pending_open_questions' => $result['removed_pending_open_questions'],
                'removed_asked_open_questions' => $result['removed_asked_open_questions'],
                'removed_active_open_question' => $result['removed_active_open_question'],
                'queued_next_open_question' => $result['queued_next_open_question'],
            ],
        ]);
    }

    /**
     * @return array{queue_changed:bool,removed_pending_open_questions:int,removed_asked_open_questions:int,removed_active_open_question:bool}
     */
    private function sanitizeSummaryOpenQuestionQueue(InterrogationSession $session): array
    {
        $queue = $this->summaryOpenQuestionQueue($session);
        if ($queue === null) {
            return [
                'queue_changed' => false,
                'removed_pending_open_questions' => 0,
                'removed_asked_open_questions' => 0,
                'removed_active_open_question' => false,
            ];
        }

        $queueChanged = false;

        $rawPending = array_values(array_filter(
            (array) ($queue['pending_questions'] ?? []),
            static fn ($item): bool => is_string($item) && trim($item) !== ''
        ));
        $pending = $this->normalizeOpenQuestionList($rawPending);
        $removedPending = max(0, count($rawPending) - count($pending));
        if ($removedPending > 0 || $pending !== $rawPending) {
            $queueChanged = true;
        }

        $asked = [];
        $removedAsked = 0;
        foreach ((array) ($queue['asked_questions'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['question_text'] ?? ''));
            if ($text === '' || $this->normalizeOpenQuestionList([$text]) === []) {
                $removedAsked++;
                $queueChanged = true;

                continue;
            }

            $asked[] = $item;
        }

        $active = is_array($queue['active_open_question'] ?? null)
            ? $queue['active_open_question']
            : null;
        $removedActive = false;
        if (is_array($active)) {
            $activeText = trim((string) ($active['question_text'] ?? ''));
            if ($activeText === '' || $this->normalizeOpenQuestionList([$activeText]) === []) {
                unset($queue['active_open_question']);
                $removedActive = true;
                $queueChanged = true;
            }
        }

        if (! $queueChanged) {
            return [
                'queue_changed' => false,
                'removed_pending_open_questions' => 0,
                'removed_asked_open_questions' => 0,
                'removed_active_open_question' => false,
            ];
        }

        $queue['pending_questions'] = $pending;
        $queue['asked_questions'] = $asked;
        $activeCount = isset($queue['active_open_question']) ? 1 : 0;
        $queue['total'] = max(1, count($queue['pending_questions']) + count($queue['asked_questions']) + $activeCount);
        $queue['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        if ($queue['pending_questions'] === [] && $queue['asked_questions'] === [] && $activeCount === 0) {
            $this->clearSummaryOpenQuestionQueue($session);
        } else {
            $this->persistSummaryOpenQuestionQueue($session, $queue);
        }

        return [
            'queue_changed' => true,
            'removed_pending_open_questions' => $removedPending,
            'removed_asked_open_questions' => $removedAsked,
            'removed_active_open_question' => $removedActive,
        ];
    }

    public function destroy(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $session = $this->findSession->execute($request->user()->id, $id);

        $this->approvalService->destroy($session);

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
        $session = $this->findSession->execute($request->user()->id, $id, withTrashed: true);

        $this->approvalService->restore($session);

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
        $session = $this->findSession->execute($request->user()->id, $id);

        $after = max(0, (int) $request->integer('after_sequence', 0));
        $limit = (int) $request->integer('limit', 100);

        $result = $this->listEvents->execute($session, $after, $limit);
        $returned = $result['events'];
        $hasMore = $result['has_more'];
        /** @var InterrogationEvent|null $lastEvent */
        $lastEvent = $returned->last();
        $nextAfter = $lastEvent->sequence ?? $after;

        return response()->json([
            'data' => $returned->map(fn (InterrogationEvent $event): array => [
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
        $data = [
            'id' => $session->id,
            'user_id' => $session->user_id,
            'name' => $session->name,
            'runner_type' => $session->runner_type,
            'model' => $session->model,
            'project_directory' => $session->project_directory,
            'interrogation_type' => $session->interrogation_type,
            'feature_brief' => $includeLargePayloads ? $session->feature_brief : null,
            'status' => $session->status,
            'phase' => $session->phase,
            'cli_session_id' => $session->cli_session_id,
            'summary_json' => $this->planService->normalizedSummaryJson($session, $includeLargePayloads),
            'plan_json' => $this->planService->normalizedPlanJson($session, $includeLargePayloads),
            'has_meaningful_plan' => $this->planService->hasMeaningfulPlan($session),
            'build' => $this->buildService->transformBuildState($session, $includeLargePayloads),
            'approved_at' => $this->toRfc3339Millis($session->approved_at),
            'annotations_json' => $session->annotations_json,
            'metadata_json' => $session->metadata_json,
            'git_settings' => $session->gitSettings(),
            'build_settings' => $session->buildSettings(),
            'task_providers' => $this->taskProviderPayloads($session),
            'tech_stacks' => $this->techStackPayloads($session),
            'error_code' => $session->error_code,
            'error_summary' => $session->error_summary,
            'started_at' => $this->toRfc3339Millis($session->started_at),
            'finished_at' => $this->toRfc3339Millis($session->finished_at),
            'created_at' => $this->toRfc3339Millis($session->created_at),
            'updated_at' => $this->toRfc3339Millis($session->updated_at),
            'deleted_at' => $this->toRfc3339Millis($session->deleted_at),
        ];

        $operatorSignal = $this->resolveOperatorSignal($session);
        if ($operatorSignal !== null) {
            $data['operator_signal'] = $operatorSignal;
        }

        $complianceData = $this->extractComplianceData($session->metadata_json ?? []);
        if (! empty($complianceData)) {
            $data['compliance_summary'] = (new ComplianceSummaryResource($complianceData))->toArray(request());
        }

        return $data;
    }

    /**
     * Extract compliance-related data from metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeGitSettings(array $raw): array
    {
        return [
            'commit_enabled' => (bool) ($raw['commit_enabled'] ?? false),
            'conventional_commits' => (bool) ($raw['conventional_commits'] ?? false),
            'worktree_enabled' => (bool) ($raw['worktree_enabled'] ?? false),
            'branching_enabled' => (bool) ($raw['branching_enabled'] ?? false),
            'branch_prefix' => is_string($raw['branch_prefix'] ?? null) && trim($raw['branch_prefix']) !== ''
                ? trim($raw['branch_prefix'])
                : null,
            'target_branch' => is_string($raw['target_branch'] ?? null) && trim($raw['target_branch']) !== ''
                ? trim($raw['target_branch'])
                : null,
            'updated_at' => \Carbon\CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeBuildSettings(array $raw): array
    {
        return [
            'auto_advance_tasks' => (bool) ($raw['auto_advance_tasks'] ?? true),
            'updated_at' => \Carbon\CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    private function extractComplianceData(array $metadata): array
    {
        $complianceKeys = [
            'workflow_policy_version',
            'complexity_classification',
            'task_category',
            'plan_required',
            'plan_completed',
            'verification_required',
            'verification_completed',
            'compliance_block_reason',
            'compliance_remediation',
            'compliance_gates',
            'compliance_status',
        ];

        return array_intersect_key($metadata, array_flip($complianceKeys));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskProviderPayloads(InterrogationSession $session): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ConnectedProvider> $providers */
        $providers = $session->providerIntegrations()
            ->where('category', 'task_management')
            ->orderBy('id')
            ->get();

        return $providers
            ->map(function (ConnectedProvider $provider): array {
                $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
                $identity = is_array($metadata['identity'] ?? null) ? $metadata['identity'] : [];
                $projectSync = is_array($metadata['project_sync'] ?? null) ? $metadata['project_sync'] : [];
                $projectMode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
                    ? (string) $projectSync['mode']
                    : 'create_new';

                return [
                    'id' => $provider->id,
                    'driver' => $provider->driver,
                    'category' => $provider->category,
                    'provider_user_id' => $provider->provider_user_id,
                    'provider_workspace_id' => $provider->provider_workspace_id,
                    'provider_workspace_name' => $provider->provider_workspace_name,
                    'provider_user_name' => $identity['provider_user_name'] ?? null,
                    'provider_user_email' => $identity['provider_user_email'] ?? null,
                    'team_id' => $metadata['team_id'] ?? ($identity['team_id'] ?? null),
                    'team_name' => $metadata['team_name'] ?? ($identity['team_name'] ?? null),
                    'team_key' => $metadata['team_key'] ?? ($identity['team_key'] ?? null),
                    'project_mode' => $projectMode,
                    'selected_project_id' => $projectMode === 'existing'
                        ? (is_string($projectSync['selected_project_id'] ?? null) ? trim((string) $projectSync['selected_project_id']) : null)
                        : null,
                    'selected_project_name' => $projectMode === 'existing'
                        ? ($projectSync['selected_project_name'] ?? null)
                        : null,
                    'selected_project_url' => $projectMode === 'existing'
                        ? ($projectSync['selected_project_url'] ?? null)
                        : null,
                    'connected_at' => $metadata['connected_at'] ?? $this->toRfc3339Millis($provider->created_at),
                    'expires_at' => $this->toRfc3339Millis($provider->expires_at),
                    'created_at' => $this->toRfc3339Millis($provider->created_at),
                    'updated_at' => $this->toRfc3339Millis($provider->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function techStackPayloads(InterrogationSession $session): array
    {
        return $session->techStacks()
            ->ordered() // @phpstan-ignore method.notFound
            ->get()
            ->map(fn ($stack): array => [
                'id' => $stack->id,
                'sequence' => $stack->sequence,
                'name' => $stack->name,
                'documentation_url' => $stack->documentation_url,
                'metadata_json' => $stack->metadata_json,
                'created_at' => $this->toRfc3339Millis($stack->created_at),
                'updated_at' => $this->toRfc3339Millis($stack->updated_at),
            ])
            ->values()
            ->all();
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
     * @return array<string, mixed>|null
     */
    private function resolveOperatorSignal(InterrogationSession $session): ?array
    {
        $now = CarbonImmutable::now('UTC');
        $phase = (int) $session->phase;
        $status = strtolower(trim((string) $session->status));

        if ($phase === InterrogationSession::PHASE_DISCOVERY && $status === InterrogationSession::STATUS_SETUP) {
            $reference = $session->updated_at ?? $session->created_at;
            if (($this->secondsSinceReference($reference, $now) ?? 0) >= 20) {
                return [
                    'code' => 'QUEUE_WORKER_UNAVAILABLE',
                    'severity' => 'warning',
                    'title' => 'Interrogation queue worker may be unavailable',
                    'detail' => 'Discovery was queued but has not started. The interrogation queue worker may not be running.',
                    'suggested_action' => 'Start/restart the queue worker (for example `php artisan horizon` or `php artisan queue:work --queue=interrogation`) and retry discovery.',
                ];
            }
        }

        if (
            $phase === InterrogationSession::PHASE_PLANNING
            && $status === InterrogationSession::STATUS_PLANNING
            && ! $this->planService->hasMeaningfulPlan($session)
        ) {
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $planMeta = is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [];
            $generationStatus = strtolower(trim((string) ($planMeta['generation_status'] ?? '')));
            $revisionStatus = strtolower(trim((string) ($planMeta['revision_status'] ?? '')));
            $waitingForPlan = in_array($generationStatus, ['queued', 'running', 'idle'], true)
                || in_array($revisionStatus, ['queued', 'running'], true);

            if ($waitingForPlan) {
                $reference = $planMeta['generation_updated_at']
                    ?? $planMeta['revision_updated_at']
                    ?? $session->updated_at
                    ?? $session->created_at;

                if (($this->secondsSinceReference($reference, $now) ?? 0) >= 30) {
                    return [
                        'code' => 'QUEUE_WORKER_UNAVAILABLE',
                        'severity' => 'warning',
                        'title' => 'Interrogation queue worker may be unavailable',
                        'detail' => 'Plan generation has been queued but no plan output has been produced yet.',
                        'suggested_action' => 'Start/restart the queue worker (for example `php artisan horizon` or `php artisan queue:work --queue=interrogation`) and retry plan generation.',
                    ];
                }
            }
        }

        return null;
    }

    private function secondsSinceReference(mixed $reference, CarbonImmutable $now): ?float
    {
        if ($reference === null) {
            return null;
        }

        if (is_string($reference) && trim($reference) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($reference, 'UTC')->diffInSeconds($now);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $openQuestions
     * @return array<int, string>
     */
    private function normalizeOpenQuestionList(array $openQuestions): array
    {
        return app(SummaryOpenQuestionQueueService::class)->normalizeOpenQuestionList($openQuestions);
    }

    /**
     * @param  array<int, string>  $openQuestions
     * @return array<string, mixed>
     */
    private function buildSummaryOpenQuestionQueue(array $openQuestions, string $focus): array
    {
        return app(SummaryOpenQuestionQueueService::class)->buildQueue($openQuestions, $focus);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summaryOpenQuestionQueue(InterrogationSession $session): ?array
    {
        return app(SummaryOpenQuestionQueueService::class)->getQueue($session);
    }

    /**
     * @param  array<string, mixed>  $queue
     */
    private function persistSummaryOpenQuestionQueue(InterrogationSession $session, array $queue): void
    {
        app(SummaryOpenQuestionQueueService::class)->persistQueue($session, $queue);
    }

    private function clearSummaryOpenQuestionQueue(InterrogationSession $session): void
    {
        app(SummaryOpenQuestionQueueService::class)->clearQueue($session);
    }

    private function dispatchNextSummaryOpenQuestionFromQueue(InterrogationSession $session): bool
    {
        return app(SummaryOpenQuestionQueueService::class)->dispatchNextFromQueue($session);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function handleSummaryOpenQuestionQueueAnswer(
        InterrogationSession $session,
        array $validated,
        SessionStateTransitionService $transitions,
    ): bool {
        $queue = $this->summaryOpenQuestionQueue($session);
        if ($queue === null) {
            return false;
        }

        $writer = new InterrogationEventWriter($session);
        $writer->appendAnswer($this->normalizedAnswerEventPayload($validated));

        $active = is_array($queue['active_open_question'] ?? null) ? $queue['active_open_question'] : null;
        $answeredQuestionId = trim((string) ($validated['question_id'] ?? ''));

        if (is_array($active)) {
            $asked = array_values(array_filter(
                (array) ($queue['asked_questions'] ?? []),
                static fn ($item): bool => is_array($item)
            ));

            $asked[] = [
                'question_id' => $answeredQuestionId !== '' ? $answeredQuestionId : null,
                'question_text' => (string) ($active['question_text'] ?? ''),
                'ordinal' => (int) ($active['ordinal'] ?? (count($asked) + 1)),
                'asked_at' => (string) ($active['dispatched_at'] ?? CarbonImmutable::now('UTC')->toIso8601String()),
                'answered_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            $queue['asked_questions'] = $asked;
        }

        unset($queue['active_open_question']);
        $queue['last_answered_question_id'] = trim((string) ($validated['question_id'] ?? ''));
        $queue['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $this->persistSummaryOpenQuestionQueue($session, $queue);

        if ($this->dispatchNextSummaryOpenQuestionFromQueue($session)) {
            return true;
        }

        $focus = trim((string) ($queue['focus'] ?? ''));
        $this->clearSummaryOpenQuestionQueue($session);

        // Mark auto-continue so the summary job will automatically re-queue
        // any remaining open questions without requiring user intervention.
        app(SummaryOpenQuestionQueueService::class)->markAutoReinterrogation($session, $focus);

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_INTERROGATION,
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::STATUS_SUMMARIZING,
            [InterrogationSession::STATUS_INTERROGATING, InterrogationSession::STATUS_PAUSED],
        );

        if ($moved) {
            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendPhaseTransition(
                InterrogationSession::PHASE_INTERROGATION,
                InterrogationSession::PHASE_SUMMARY,
                (string) $session->status,
                ['at' => CarbonImmutable::now('UTC')->toIso8601String(), 'source' => 'summary_open_question_queue']
            );
        }

        ExecuteInterrogationSummaryJob::dispatch((int) $session->id);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedAnswerEventPayload(array $payload): array
    {
        $answerType = (string) ($payload['answer_type'] ?? 'freetext');
        $questionId = isset($payload['question_id']) ? trim((string) $payload['question_id']) : '';

        return [
            'question_id' => $questionId !== '' ? $questionId : null,
            'answer_type' => $answerType,
            'answer_text' => (string) ($payload['answer_text'] ?? ''),
            'selected_option' => (string) ($payload['selected_option'] ?? ''),
            'selected_options' => array_values(array_filter(
                (array) ($payload['selected_options'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            )),
            'skip_reason' => (string) ($payload['skip_reason'] ?? ''),
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
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
}
