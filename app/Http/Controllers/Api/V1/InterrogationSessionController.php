<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\RequestPlanRevisionRequest;
use App\Http\Requests\Interrogation\StoreInterrogationSessionRequest;
use App\Http\Requests\Interrogation\SubmitAnswerRequest;
use App\Http\Requests\Interrogation\UpdateAnnotationRequest;
use App\Http\Requests\Interrogation\UpdateInterrogationSessionRequest;
use App\Http\Resources\ComplianceSummaryResource;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Jobs\RegenerateInterrogationBuildTaskJob;
use App\Jobs\SyncInterrogationTasksToTaskProviderJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Interrogation\ExportService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\PlanPayloadGuard;
use App\Support\Interrogation\PlanPayloadNormalizer;
use App\Support\Interrogation\QuestionPayloadGuard;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SummaryOpenQuestionQueueService;
use App\Support\Interrogation\SummaryPayloadNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class InterrogationSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InterrogationSession::query()->where('user_id', $request->user()->id);

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

        /** @var \Illuminate\Support\Collection<int, InterrogationSession> $items */
        $items = collect($sessions->items());
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::withTrashed()->where('user_id', $request->user()->id)->findOrFail($id);
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

        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'] ?? null,
            'runner_type' => $validated['runner_type'],
            'model' => $validated['model'] ?? null,
            'project_directory' => $validated['project_directory'],
            'interrogation_type' => $validated['interrogation_type'],
            'feature_brief' => $validated['feature_brief'] ?? null,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
            'metadata_json' => $metadata,
        ]);

        $afterSnapshot = $session->only(['id', 'name', 'runner_type', 'model', 'project_directory', 'interrogation_type', 'status', 'phase']);
        $changedFields = ['name', 'runner_type', 'model', 'project_directory', 'interrogation_type', 'feature_brief', 'status', 'phase'];
        if (isset($metadata['git'])) {
            $changedFields[] = 'git_settings';
            $afterSnapshot['git_settings'] = $session->gitSettings();
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::withTrashed()->where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $request->validated();
        $before = [
            'name' => $session->name,
            'feature_brief' => $session->feature_brief,
            'model' => $session->model,
            'git_settings' => $session->gitSettings(),
        ];

        if (array_key_exists('name', $validated)) {
            $session->name = $validated['name'];
        }

        if (array_key_exists('feature_brief', $validated)) {
            $session->feature_brief = $validated['feature_brief'];
        }

        if (array_key_exists('model', $validated)) {
            $session->model = $validated['model'];
        }

        if (array_key_exists('git', $validated)) {
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $existingGit = is_array($metadata['git'] ?? null) ? $metadata['git'] : [];
            $incomingGit = is_array($validated['git']) ? $validated['git'] : [];
            $metadata['git'] = $this->normalizeGitSettings(array_merge($existingGit, $incomingGit));
            $session->metadata_json = $metadata;
        }

        $session->save();

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
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

        // Planning must always be regenerated from the latest confirmed summary.
        $this->markPlanGenerationState($session, 'queued');
        ExecuteInterrogationPlanJob::dispatch((int) $session->id);

        return response()->json(['data' => ['confirmed' => true, 'session_id' => $session->id]]);
    }

    public function reviseSummary(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in summary phase.', 409);
        }

        $validated = $request->validate([
            'focus' => ['nullable', 'string', 'max:4000'],
            'revisit_question_id' => ['nullable', 'string', 'max:120'],
        ]);

        $focus = trim((string) ($validated['focus'] ?? ''));
        $revisitQuestionId = trim((string) ($validated['revisit_question_id'] ?? ''));
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
        $this->invalidateDerivedArtifactsForReinterrogation($session);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if ((int) $session->phase < InterrogationSession::PHASE_PLANNING || in_array($session->status, [InterrogationSession::STATUS_FAILED], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not ready to generate a plan.', 409);
        }

        $this->markPlanGenerationState($session, 'queued');
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

    public function regeneratePlan(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan regeneration is only available during planning phase.', 409);
        }

        $summary = is_array($session->summary_json) ? $session->summary_json : [];
        if ($summary === []) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Summary must be present before plan regeneration.', 409);
        }

        $prompt = 'Regenerate the implementation plan from the confirmed summary and metadata context only. '
            .'Ignore any prior plan drafts and rewrite the plan from scratch.';

        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['plan'] = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'revision_status' => 'queued',
                'revision_requested_at' => $now,
                'revision_updated_at' => $now,
                'revision_error' => null,
                'regenerated_at' => $now,
                'regenerated_by_user_id' => (int) $request->user()->id,
            ],
        );
        $session->status = InterrogationSession::STATUS_PLANNING;
        $session->phase = InterrogationSession::PHASE_PLANNING;
        $session->finished_at = null;
        $session->error_code = null;
        $session->error_summary = null;
        $session->metadata_json = $metadata;
        $session->save();

        (new InterrogationEventWriter($session))->appendSystem([
            'notice' => 'plan_regeneration_queued',
            'message' => 'Plan regeneration queued.',
            'at' => $now,
        ]);

        ExecuteInterrogationPlanJob::dispatch((int) $session->id, $prompt);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if ((int) $session->phase > InterrogationSession::PHASE_PLANNING && $session->approved_at !== null) {
            return response()->json([
                'data' => [
                    'approved' => true,
                    'session_id' => $session->id,
                    'approved_at' => $this->toRfc3339Millis($session->approved_at),
                ],
            ]);
        }

        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan can only be approved from planning phase.', 409);
        }

        if ($session->status === InterrogationSession::STATUS_FAILED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Failed session must be retried before plan approval.', 409);
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
            InterrogationSession::PHASE_BUILD_RULES,
            InterrogationSession::STATUS_BUILD_RULES,
            [
                InterrogationSession::STATUS_PLANNING,
                InterrogationSession::STATUS_COMPLETED,
                InterrogationSession::STATUS_PAUSED,
            ],
        );

        if (! $moved) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session state changed while approving plan.', 409);
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::PHASE_BUILD_RULES,
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

    private function invalidateDerivedArtifactsForReinterrogation(InterrogationSession $session): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        $metadata['summary_ready'] = false;
        $metadata['summary_generated_at'] = null;

        if (is_array($metadata['exports'] ?? null)) {
            unset($metadata['exports']['summary'], $metadata['exports']['plan']);
            if ($metadata['exports'] === []) {
                unset($metadata['exports']);
            }
        }

        $metadata['build'] = [
            'status' => 'idle',
            'task_count' => 0,
            'active_task_id' => null,
            'active_run_id' => null,
            'tasks_approved_at' => null,
            'tasks_approved_by_user_id' => null,
            'task_provider_sync' => [
                'status' => 'idle',
                'driver' => null,
                'project_mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
                'synced_task_count' => 0,
                'error' => null,
                'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $session->summary_json = [];
        $session->plan_json = [];
        $session->approved_at = null;
        $session->metadata_json = $metadata;
        $session->save();

        InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->delete();
    }

    public function requestRevision(
        RequestPlanRevisionRequest $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan revision is only available during planning phase.', 409);
        }

        $validated = $request->validated();

        $selectedSections = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) ($validated['sections'] ?? [])
        ), static fn (string $value): bool => $value !== ''));
        $sectionValue = trim((string) ($validated['section'] ?? ''));
        $sectionScope = $selectedSections !== []
            ? implode(', ', $selectedSections)
            : ($sectionValue !== '' ? $sectionValue : 'entire_plan');
        $notes = (string) ($validated['notes'] ?? 'none');

        $prompt = 'Revise the implementation plan.'
            ."\nAction: ".(string) $validated['action']
            ."\nSection: ".$sectionScope
            ."\nSections: ".($selectedSections !== [] ? json_encode($selectedSections, JSON_UNESCAPED_SLASHES) : '[]')
            ."\nNotes (Markdown):\n".$notes;

        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['plan'] = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'revision_status' => 'queued',
                'revision_requested_at' => $now,
                'revision_updated_at' => $now,
                'revision_error' => null,
            ],
        );
        $session->status = InterrogationSession::STATUS_PLANNING;
        $session->phase = InterrogationSession::PHASE_PLANNING;
        $session->finished_at = null;
        $session->error_code = null;
        $session->error_summary = null;
        $session->metadata_json = $metadata;
        $session->save();

        (new InterrogationEventWriter($session))->appendSystem([
            'notice' => 'plan_revision_queued',
            'message' => 'Plan revision queued.',
            'at' => $now,
        ]);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if (
            ! in_array((int) $session->phase, [InterrogationSession::PHASE_BUILD_RULES, InterrogationSession::PHASE_BUILD_TASKS], true)
            || ! in_array($session->status, [InterrogationSession::STATUS_BUILD_RULES, InterrogationSession::STATUS_BUILD_TASKS, InterrogationSession::STATUS_PAUSED], true)
        ) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not in build rules or build tasks phase.', 409);
        }

        if ($session->approved_at === null || ! $this->hasMeaningfulPlan($session)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is not ready to generate build tasks.', 409);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:6000'],
            'project_rules' => ['nullable'],
            'project_rule_files' => ['nullable', 'array', 'max:20'],
            'project_rule_files.*' => ['file', 'max:2048', 'mimes:md,markdown,txt'],
        ]);

        $projectRulePayload = $this->resolveProjectRulesPayload($request);

        $build = $this->buildMetadata($session);
        $build['status'] = 'generating_tasks';
        $build['notes'] = trim((string) ($validated['notes'] ?? ''));
        $build['task_count'] = 0;
        $build['error'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['tasks_approved_at'] = null;
        $build['tasks_approved_by_user_id'] = null;
        $build['task_provider_sync'] = [
            'status' => 'idle',
            'driver' => null,
            'project_mode' => 'create_new',
            'project_id' => null,
            'project_name' => null,
            'project_url' => null,
            'synced_task_count' => 0,
            'error' => null,
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        if ($projectRulePayload['provided']) {
            $build['project_rules'] = $projectRulePayload['rules'];
            $build['project_rules_updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        }
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

    public function storeBuildTask(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = $this->buildTaskEditingConflict($session)) {
            return $conflict;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:12000'],
            'instructions_markdown' => ['nullable', 'string', 'max:120000'],
        ]);

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Task title is required.',
            ]);
        }

        /** @var InterrogationBuildTask $task */
        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => (int) $session->id,
            'sequence' => ((int) ($session->buildTasks()->max('sequence') ?? 0)) + 1,
            'title' => $title,
            'description' => $this->normalizedNullableText($validated['description'] ?? null),
            'instructions_markdown' => $this->normalizedNullableText($validated['instructions_markdown'] ?? null),
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'agent_job_run_id' => null,
            'last_error' => null,
            'metadata_json' => [
                'source' => 'manual',
                'created_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
            'started_at' => null,
            'finished_at' => null,
        ]);

        $this->invalidateBuildTaskApproval($session);

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
            'data' => $this->transformBuildTask($task->fresh()),
        ], 201);
    }

    public function reorderBuildTasks(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = $this->buildTaskEditingConflict($session)) {
            return $conflict;
        }

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'distinct'],
        ]);

        $requestedIds = array_values(array_map(static fn (mixed $value): int => (int) $value, (array) $validated['task_ids']));
        $tasks = $session->buildTasks()->ordered()->get(['id', 'sequence']);

        if ($tasks->isEmpty()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'No build tasks are available to reorder.', 409);
        }

        $existingIds = $tasks
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $requestedSorted = $requestedIds;
        $existingSorted = $existingIds;
        sort($requestedSorted);
        sort($existingSorted);

        if (count($requestedIds) !== count($existingIds) || $requestedSorted !== $existingSorted) {
            throw ValidationException::withMessages([
                'task_ids' => 'Task order payload must include every build task exactly once.',
            ]);
        }

        $beforeOrder = $tasks
            ->sortBy('sequence')
            ->values()
            ->map(static fn (InterrogationBuildTask $task): int => (int) $task->id)
            ->all();

        $temporaryOffset = max(
            100,
            (int) $tasks->max('sequence') + count($requestedIds) + 10,
        );

        DB::transaction(function () use ($requestedIds, $temporaryOffset): void {

            foreach ($requestedIds as $index => $taskId) {
                InterrogationBuildTask::query()
                    ->whereKey($taskId)
                    ->update(['sequence' => $temporaryOffset + $index + 1]);
            }

            foreach ($requestedIds as $index => $taskId) {
                InterrogationBuildTask::query()
                    ->whereKey($taskId)
                    ->update(['sequence' => $index + 1]);
            }
        });

        $this->invalidateBuildTaskApproval($session);

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
                    ->map(fn (InterrogationBuildTask $task): array => $this->transformBuildTask($task))
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = $this->buildTaskEditingConflict($session)) {
            return $conflict;
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

        if (array_key_exists('title', $validated)) {
            $title = trim((string) $validated['title']);
            if ($title === '') {
                throw ValidationException::withMessages([
                    'title' => 'Task title is required.',
                ]);
            }

            $task->title = $title;
        }

        if (array_key_exists('description', $validated)) {
            $task->description = $this->normalizedNullableText($validated['description']);
        }

        if (array_key_exists('instructions_markdown', $validated)) {
            $task->instructions_markdown = $this->normalizedNullableText($validated['instructions_markdown']);
        }

        $task->status = InterrogationBuildTask::STATUS_PENDING;
        $task->attempt_count = 0;
        $task->agent_job_run_id = null;
        $task->last_error = null;
        $task->started_at = null;
        $task->finished_at = null;
        $task->save();

        $this->invalidateBuildTaskApproval($session);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = $this->buildTaskEditingConflict($session)) {
            return $conflict;
        }

        /** @var InterrogationBuildTask $task */
        $task = $session->buildTasks()->whereKey($taskId)->firstOrFail();
        $deletedTaskId = (int) $task->id;

        $task->delete();
        $this->resequenceBuildTasks($session);
        $this->invalidateBuildTaskApproval($session);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = $this->buildTaskEditingConflict($session)) {
            return $conflict;
        }

        /** @var InterrogationBuildTask $task */
        $task = $session->buildTasks()->whereKey($taskId)->firstOrFail();

        $validated = $request->validate([
            'amend_notes' => ['required', 'string', 'max:6000'],
            'additional_context' => ['nullable', 'string', 'max:6000'],
        ]);

        $amendNotes = trim((string) ($validated['amend_notes'] ?? ''));
        $additionalContext = $this->normalizedNullableText($validated['additional_context'] ?? null);
        if ($amendNotes === '') {
            throw ValidationException::withMessages([
                'amend_notes' => 'Amend notes are required to regenerate a task.',
            ]);
        }

        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
        $metadata['regeneration'] = [
            'status' => 'queued',
            'requested_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'requested_by_user_id' => (int) $request->user()->id,
            'amend_notes' => $amendNotes,
            'additional_context' => $additionalContext,
        ];
        $task->metadata_json = $metadata;
        $task->save();

        $this->invalidateBuildTaskApproval($session);

        RegenerateInterrogationBuildTaskJob::dispatch(
            (int) $session->id,
            (int) $task->id,
            $amendNotes,
            $additionalContext,
        );

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_TASKS) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build task approval is only available from build tasks phase.', 409);
        }

        $tasks = $session->buildTasks()->ordered()->get();
        if ($tasks->isEmpty()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'No build tasks are available to approve.', 409);
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks are still being generated.', 409);
        }

        $approvedAt = CarbonImmutable::now('UTC')->toIso8601String();
        $build['tasks_approved_at'] = $approvedAt;
        $build['tasks_approved_by_user_id'] = (int) $request->user()->id;
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $provider = $session->providerIntegrations()
            ->where('category', 'task_management')
            ->orderByDesc('id')
            ->first();

        $syncQueued = false;

        if ($provider instanceof ConnectedProvider) {
            $projectSync = $this->providerProjectSyncPreference($provider);
            $build['task_provider_sync'] = [
                'driver' => (string) $provider->driver,
                'status' => 'queued',
                'project_mode' => $projectSync['mode'],
                'project_id' => $projectSync['project_id'],
                'project_name' => $projectSync['project_name'],
                'project_url' => $projectSync['project_url'],
                'synced_task_count' => 0,
                'error' => null,
                'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            $syncQueued = true;
        }

        $this->saveBuildMetadata($session, $build);

        if ($syncQueued) {
            SyncInterrogationTasksToTaskProviderJob::dispatch((int) $session->id);
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'interrogation.session.approve_build_tasks',
            targetType: 'interrogation_session',
            targetId: (int) $session->id,
            ownerUserId: (int) $session->user_id,
            changedFields: ['metadata_json'],
            before: null,
            after: [
                'build_tasks_approved_at' => $approvedAt,
                'task_provider_sync_queued' => $syncQueued,
            ],
        );

        return response()->json([
            'data' => [
                'approved' => true,
                'session_id' => $session->id,
                'tasks_approved_at' => $approvedAt,
                'task_provider_sync_queued' => $syncQueued,
            ],
        ], 202);
    }

    public function startBuild(
        Request $request,
        int $id,
        SessionStateTransitionService $transitions,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if (! in_array((int) $session->phase, [InterrogationSession::PHASE_BUILD_TASKS, InterrogationSession::PHASE_BUILD_EXECUTION], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build can only start from build phases.', 409);
        }

        if ($session->approved_at === null) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Plan must be approved before starting build.', 409);
        }

        $validated = $request->validate([
            'restart_failed' => ['nullable', 'boolean'],
            'restart_all' => ['nullable', 'boolean'],
        ]);

        $tasks = $session->buildTasks()->ordered()->get();
        if ($tasks->isEmpty()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'No build tasks are available. Generate build tasks first.', 409);
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks are still being generated.', 409);
        }

        if (! is_string($build['tasks_approved_at'] ?? null) || trim((string) $build['tasks_approved_at']) === '') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks must be approved before execution starts.', 409);
        }

        $taskProviderSync = is_array($build['task_provider_sync'] ?? null) ? $build['task_provider_sync'] : [];
        if (in_array((string) ($taskProviderSync['status'] ?? ''), ['queued', 'syncing'], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Task provider sync is still running. Wait for completion before starting build.', 409);
        }

        $currentBuildStatus = $this->normalizedBuildStatus($build);
        $restartFailed = (bool) ($validated['restart_failed'] ?? false);
        $restartAll = (bool) ($validated['restart_all'] ?? false);
        if ($restartAll) {
            $restartFailed = true;
        }

        if ($currentBuildStatus === 'completed' && ! $restartFailed) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build is already completed. Use restart_failed or restart_all to re-run.', 409);
        }

        if ($restartFailed) {
            foreach ($tasks as $task) {
                if ($restartAll) {
                    if ($task->status === InterrogationBuildTask::STATUS_PENDING) {
                        continue;
                    }

                    $task->status = InterrogationBuildTask::STATUS_PENDING;
                    $task->agent_job_run_id = null;
                    $task->last_error = null;
                    $task->started_at = null;
                    $task->finished_at = null;
                    $task->save();

                    continue;
                }

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
        $build['completion_summary'] = null;
        $build['pause_reason'] = null;
        $build['paused_at'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['started_at'] = $build['started_at'] ?? CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['approval_required'] = false;
        $build['permission_required'] = false;
        $build['permission_excerpt'] = null;
        $build['clarification_required'] = false;
        $build['clarification_excerpt'] = null;
        $build['rate_limit_detected'] = false;
        $build['rate_limit_reset_at'] = null;
        $build['rate_limit_excerpt'] = null;

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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

        /** @var InterrogationBuildTask|null $activeTask */


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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        $build['completion_summary'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['approval_required'] = false;
        $build['permission_required'] = false;
        $build['permission_excerpt'] = null;
        $build['clarification_required'] = false;
        $build['clarification_excerpt'] = null;
        $build['rate_limit_detected'] = false;
        $build['rate_limit_reset_at'] = null;
        $build['rate_limit_excerpt'] = null;

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        $build['clarification_required'] = false;
        $build['clarification_excerpt'] = null;

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        if (! in_array($session->status, InterrogationSession::RESUMABLE_STATUSES, true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session cannot be resumed from its current state.', 409);
        }

        $nextStatus = match ((int) $session->phase) {
            InterrogationSession::PHASE_PROVIDER_SETUP => InterrogationSession::STATUS_SETUP,
            InterrogationSession::PHASE_TECH_STACK_SETUP => InterrogationSession::STATUS_SETUP,
            InterrogationSession::PHASE_DISCOVERY => InterrogationSession::STATUS_DISCOVERING,
            InterrogationSession::PHASE_INTERROGATION => InterrogationSession::STATUS_INTERROGATING,
            InterrogationSession::PHASE_SUMMARY => InterrogationSession::STATUS_SUMMARIZING,
            InterrogationSession::PHASE_PLANNING => InterrogationSession::STATUS_PLANNING,
            InterrogationSession::PHASE_BUILD_RULES => InterrogationSession::STATUS_BUILD_RULES,
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::withTrashed()->where('user_id', $request->user()->id)->findOrFail($id);

        if ($session->trashed()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is deleted. Restore it before retrying.', 409);
        }

        if ($session->status === InterrogationSession::STATUS_COMPLETED) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is already completed. Use plan revision for updates.', 409);
        }

        $targetStatus = match ((int) $session->phase) {
            InterrogationSession::PHASE_SETUP,
            InterrogationSession::PHASE_PROVIDER_SETUP,
            InterrogationSession::PHASE_TECH_STACK_SETUP,
            InterrogationSession::PHASE_DISCOVERY => InterrogationSession::STATUS_SETUP,
            InterrogationSession::PHASE_INTERROGATION => InterrogationSession::STATUS_INTERROGATING,
            InterrogationSession::PHASE_SUMMARY => InterrogationSession::STATUS_SUMMARIZING,
            InterrogationSession::PHASE_PLANNING => InterrogationSession::STATUS_PLANNING,
            InterrogationSession::PHASE_BUILD_RULES => InterrogationSession::STATUS_BUILD_RULES,
            InterrogationSession::PHASE_BUILD_TASKS => InterrogationSession::STATUS_BUILD_TASKS,
            InterrogationSession::PHASE_BUILD_EXECUTION => InterrogationSession::STATUS_BUILD_EXECUTING,
            default => InterrogationSession::STATUS_SETUP,
        };

        $allowedFromStatuses = [
            InterrogationSession::STATUS_FAILED,
            InterrogationSession::STATUS_PAUSED,
            InterrogationSession::STATUS_SETUP,
        ];
        if ((int) $session->phase === InterrogationSession::PHASE_INTERROGATION) {
            $allowedFromStatuses[] = InterrogationSession::STATUS_INTERROGATING;
        }

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

        $pendingQuestionId = null;
        if ((int) $session->phase === InterrogationSession::PHASE_INTERROGATION) {
            $pendingQuestionId = $this->latestUnansweredQuestionId($session);

            if ($pendingQuestionId === null) {
                $session->cli_session_id = null;
                $session->save();
            }
        }

        if ((int) $session->phase === InterrogationSession::PHASE_PLANNING) {
            $this->markPlanGenerationState($session, 'queued');
        }

        $queued = true;

        match ((int) $session->phase) {
            InterrogationSession::PHASE_SETUP,
            InterrogationSession::PHASE_PROVIDER_SETUP,
            InterrogationSession::PHASE_TECH_STACK_SETUP => $queued = false,
            InterrogationSession::PHASE_DISCOVERY => ExecuteInterrogationDiscoveryJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_INTERROGATION => $pendingQuestionId !== null
                    ? $queued = false
                    : ExecuteInterrogationRoundJob::dispatch(
                        (int) $session->id,
                        'Retry current interrogation phase. Resume from the latest unanswered question before asking anything new.',
                        true
                    ),
            InterrogationSession::PHASE_SUMMARY => ExecuteInterrogationSummaryJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_PLANNING => ExecuteInterrogationPlanJob::dispatch((int) $session->id),
            InterrogationSession::PHASE_BUILD_RULES => GenerateInterrogationBuildTasksJob::dispatch((int) $session->id),
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
                'queued' => $queued,
                'pending_question_id' => $pendingQuestionId,
                'session_id' => $session->id,
                'status' => $session->status,
                'phase' => $session->phase,
            ],
        ], 202);
    }

    public function restartFromBeginning(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::withTrashed()->where('user_id', $request->user()->id)->findOrFail($id);

        if ($session->trashed()) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Session is deleted. Restore it before restarting.', 409);
        }

        InterrogationEvent::query()
            ->where('interrogation_session_id', $session->id)
            ->delete();

        $session->buildTasks()->delete();

        $source = trim((string) data_get($session->metadata_json, 'source', 'ui'));
        $session->status = InterrogationSession::STATUS_SETUP;
        $session->phase = InterrogationSession::PHASE_SETUP;
        $session->cli_session_id = null;
        $session->summary_json = [];
        $session->plan_json = [];
        $session->annotations_json = [];
        $session->metadata_json = ['source' => $source !== '' ? $source : 'ui'];
        $session->approved_at = null;
        $session->error_code = null;
        $session->error_summary = null;
        $session->started_at = null;
        $session->finished_at = null;
        $session->save();

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
            'data' => [
                'accepted' => true,
                'queued' => false,
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

        /** @var InterrogationEvent $questionEvent */
        foreach ($questions as $questionEvent) {
            $payload = (array) $questionEvent->payload;
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
            return false;
        }

        if (preg_match('/\b(?:requirements\s+)?interrogation\s+is\s+now\s+complete\b/', $text) === 1) {
            return false;
        }

        if (preg_match('/\binterrogation\s+completed\b/', $text) === 1) {
            return false;
        }

        $guard = new QuestionPayloadGuard;
        $validation = $guard->validate($payload);

        return (bool) $validation['valid'];
    }

    public function cleanupInvalidQuestions(
        Request $request,
        int $id,
        AuditLogger $auditLogger,
    ): JsonResponse {
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
        $pruned = $this->pruneInvalidQuestionEvents($session);
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

    private function markPlanGenerationState(
        InterrogationSession $session,
        string $status,
        ?string $error = null,
    ): void {
        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $plan = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'generation_status' => $status,
                'generation_updated_at' => $now,
            ],
        );

        if ($status === 'queued') {
            $plan['generation_queued_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'running') {
            $plan['generation_started_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'idle') {
            $plan['generation_completed_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'failed') {
            $plan['generation_error'] = $error ?: 'Plan generation failed.';
        }

        $metadata['plan'] = $plan;
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * @return array{removed_question_events:int,removed_answer_events:int,removed_question_ids:array<int,string>}
     */
    private function pruneInvalidQuestionEvents(InterrogationSession $session): array
    {
        $questionEvents = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_QUESTION)
            ->orderBy('sequence')
            ->get(['id', 'payload']);

        $removeQuestionEventIds = [];
        $removeQuestionIds = [];

        /** @var InterrogationEvent $questionEvent */
        foreach ($questionEvents as $questionEvent) {
            $payload = (array) $questionEvent->payload;

            if (! $this->shouldRemoveQuestionPayload($payload)) {
                continue;
            }

            $removeQuestionEventIds[] = (int) $questionEvent->id;
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId !== '') {
                $removeQuestionIds[$questionId] = true;
            }
        }

        if ($removeQuestionEventIds === []) {
            return [
                'removed_question_events' => 0,
                'removed_answer_events' => 0,
                'removed_question_ids' => [],
            ];
        }

        $removedAnswerEvents = 0;
        $questionIds = array_keys($removeQuestionIds);
        if ($questionIds !== []) {
            $answerEvents = $session->events()
                ->where('event_type', InterrogationEvent::TYPE_ANSWER)
                ->get(['id', 'payload']);

            $removeAnswerEventIds = [];
            /** @var InterrogationEvent $answerEvent */
            foreach ($answerEvents as $answerEvent) {
                $payload = (array) $answerEvent->payload;
                $questionId = trim((string) ($payload['question_id'] ?? ''));
                if ($questionId === '' || ! isset($removeQuestionIds[$questionId])) {
                    continue;
                }

                $removeAnswerEventIds[] = (int) $answerEvent->id;
            }

            if ($removeAnswerEventIds !== []) {
                $removedAnswerEvents = InterrogationEvent::query()
                    ->where('interrogation_session_id', $session->id)
                    ->whereIn('id', $removeAnswerEventIds)
                    ->delete();
            }
        }

        $removedQuestionEvents = InterrogationEvent::query()
            ->where('interrogation_session_id', $session->id)
            ->whereIn('id', $removeQuestionEventIds)
            ->delete();

        return [
            'removed_question_events' => (int) $removedQuestionEvents,
            'removed_answer_events' => (int) $removedAnswerEvents,
            'removed_question_ids' => array_keys($removeQuestionIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldRemoveQuestionPayload(array $payload): bool
    {
        if ($this->isCompletionQuestionPayload($payload)) {
            return false;
        }

        return ! $this->isAnswerableQuestionPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCompletionQuestionPayload(array $payload): bool
    {
        if ((bool) ($payload['is_complete'] ?? false) === true) {
            return true;
        }

        if (((int) ($payload['progress_estimate'] ?? 0)) >= 100) {
            return true;
        }

        $text = strtolower(trim((string) ($payload['question_text'] ?? '')));
        if ($text === '') {
            return false;
        }

        return preg_match('/\b(?:requirements\s+)?interrogation\s+is\s+now\s+complete\b/', $text) === 1
            || preg_match('/\binterrogation\s+completed\b/', $text) === 1;
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::withTrashed()->where('user_id', $request->user()->id)->findOrFail($id);
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
        /** @var \App\Models\InterrogationSession $session */
        $session = InterrogationSession::where('user_id', $request->user()->id)->findOrFail($id);

        $after = max(0, (int) $request->integer('after_sequence', 0));
        $limit = min(500, max(1, (int) $request->integer('limit', 100)));

        $events = $session->events()
            ->where('sequence', '>', $after)
            ->orderBy('sequence')
            ->limit($limit + 1)
            ->get();

        $hasMore = $events->count() > $limit;
        $returned = $events->take($limit)->values();
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
            'summary_json' => $this->normalizedSummaryJson($session, $includeLargePayloads),
            'plan_json' => $this->normalizedPlanJson($session, $includeLargePayloads),
            'has_meaningful_plan' => $this->hasMeaningfulPlan($session),
            'build' => $this->transformBuildState($session, $includeLargePayloads),
            'approved_at' => $this->toRfc3339Millis($session->approved_at),
            'annotations_json' => $session->annotations_json,
            'metadata_json' => $session->metadata_json,
            'git_settings' => $session->gitSettings(),
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
     * @return array{mode:string,project_id:?string,project_name:?string,project_url:?string}
     */
    private function providerProjectSyncPreference(ConnectedProvider $provider): array
    {
        $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
        $projectSync = is_array($metadata['project_sync'] ?? null) ? $metadata['project_sync'] : [];
        $mode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
            ? (string) $projectSync['mode']
            : 'create_new';

        if ($mode !== 'existing') {
            return [
                'mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
            ];
        }

        $projectId = trim((string) ($projectSync['selected_project_id'] ?? ''));
        if ($projectId === '') {
            return [
                'mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
            ];
        }

        return [
            'mode' => 'existing',
            'project_id' => $projectId,
            'project_name' => is_string($projectSync['selected_project_name'] ?? null)
                ? trim((string) $projectSync['selected_project_name'])
                : null,
            'project_url' => is_string($projectSync['selected_project_url'] ?? null)
                ? trim((string) $projectSync['selected_project_url'])
                : null,
        ];
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
    private function transformBuildState(InterrogationSession $session, bool $includeDetails): array
    {
        $build = $this->buildMetadata($session);
        $status = $this->normalizedBuildStatus($build);

        if (! $includeDetails) {
            $projectRules = $this->normalizedProjectRules((array) ($build['project_rules'] ?? []));
            $taskProviderSync = is_array($build['task_provider_sync'] ?? null) ? $build['task_provider_sync'] : [];
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
                'project_rules_count' => count($projectRules),
                'tasks_approved_at' => isset($build['tasks_approved_at']) ? (string) $build['tasks_approved_at'] : null,
                'task_provider_sync' => [
                    'driver' => isset($taskProviderSync['driver']) ? (string) $taskProviderSync['driver'] : null,
                    'status' => isset($taskProviderSync['status']) ? (string) $taskProviderSync['status'] : 'idle',
                    'project_mode' => isset($taskProviderSync['project_mode']) ? (string) $taskProviderSync['project_mode'] : 'create_new',
                    'project_url' => isset($taskProviderSync['project_url']) ? (string) $taskProviderSync['project_url'] : null,
                    'error' => isset($taskProviderSync['error']) ? (string) $taskProviderSync['error'] : null,
                    'updated_at' => isset($taskProviderSync['updated_at']) ? (string) $taskProviderSync['updated_at'] : null,
                ],
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

        $clarificationExcerpt = $this->normalizeClarificationExcerpt(
            is_string($runMetadata['clarification_excerpt'] ?? null)
                ? $runMetadata['clarification_excerpt']
                : (is_string($build['clarification_excerpt'] ?? null) ? $build['clarification_excerpt'] : null)
        );

        $flags = [
            'approval_required' => (bool) (($runMetadata['approval_required'] ?? null) ?? ($build['approval_required'] ?? false)),
            'approval_excerpt' => is_string($runMetadata['approval_excerpt'] ?? null)
                ? $runMetadata['approval_excerpt']
                : null,
            'permission_required' => (bool) (($runMetadata['permission_blocker_detected'] ?? null) ?? ($build['permission_required'] ?? false)),
            'permission_excerpt' => is_string($runMetadata['permission_blocker_excerpt'] ?? null)
                ? $runMetadata['permission_blocker_excerpt']
                : (is_string($build['permission_excerpt'] ?? null) ? $build['permission_excerpt'] : null),
            'clarification_required' => (bool) (($runMetadata['clarification_required'] ?? null) ?? ($build['clarification_required'] ?? false)),
            'clarification_excerpt' => $clarificationExcerpt,
            'rate_limit_detected' => (bool) (($runMetadata['rate_limit_detected'] ?? null) ?? ($build['rate_limit_detected'] ?? false)),
            'rate_limit_reset_at' => (string) ($runMetadata['rate_limit_reset_at'] ?? $runMetadata['rate_limit_hold_until'] ?? $build['rate_limit_reset_at'] ?? ''),
            'rate_limit_excerpt' => is_string($runMetadata['rate_limit_excerpt'] ?? null)
                ? $runMetadata['rate_limit_excerpt']
                : (is_string($build['rate_limit_excerpt'] ?? null) ? $build['rate_limit_excerpt'] : null),
        ];

        $issues = is_array($runMetadata['issues'] ?? null) ? $runMetadata['issues'] : [];
        $normalizedIssues = $this->normalizeBuildIssues($issues);

        if ($flags['rate_limit_reset_at'] === '') {
            $flags['rate_limit_reset_at'] = null;
        }

        $activeRunPayload = null;
        if ($activeRun !== null) {
            $activeRunPayload = $this->transformBuildRun($activeRun);
            $activeRunPayload['effective_status'] = $this->effectiveBuildRunStatus($activeRun, $activeTask, $flags);
        }

        return [
            'status' => $status,
            'summary' => $summary,
            'tasks' => $tasks->map(fn (InterrogationBuildTask $task): array => $this->transformBuildTask($task))->values(),
            'active_task' => $activeTask !== null ? $this->transformBuildTask($activeTask) : null,
            'active_run' => $activeRunPayload,
            'project_rules' => $this->normalizedProjectRules((array) ($build['project_rules'] ?? [])),
            'flags' => $flags,
            'issues' => $normalizedIssues,
            'pause_reason' => isset($build['pause_reason']) ? (string) $build['pause_reason'] : null,
            'error' => isset($build['error']) ? (string) $build['error'] : null,
            'completion_summary' => isset($build['completion_summary']) ? (string) $build['completion_summary'] : null,
            'started_at' => isset($build['started_at']) ? (string) $build['started_at'] : null,
            'paused_at' => isset($build['paused_at']) ? (string) $build['paused_at'] : null,
            'finished_at' => isset($build['finished_at']) ? (string) $build['finished_at'] : null,
            'tasks_approved_at' => isset($build['tasks_approved_at']) ? (string) $build['tasks_approved_at'] : null,
            'tasks_approved_by_user_id' => isset($build['tasks_approved_by_user_id']) ? (int) $build['tasks_approved_by_user_id'] : null,
            'task_provider_sync' => is_array($build['task_provider_sync'] ?? null) ? $build['task_provider_sync'] : null,
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
     * @param  array<string, mixed>  $flags
     */
    private function effectiveBuildRunStatus(AgentJobRun $run, ?InterrogationBuildTask $activeTask, array $flags): string
    {
        $runStatus = Str::lower(trim((string) $run->status));
        $taskStatus = Str::lower(trim((string) ($activeTask->status ?? '')));

        if ($taskStatus === InterrogationBuildTask::STATUS_FAILED) {
            return 'failed_task';
        }

        if ($taskStatus !== InterrogationBuildTask::STATUS_BLOCKED) {
            return $runStatus !== '' ? $runStatus : (string) $run->status;
        }

        if (($flags['clarification_required'] ?? false) === true) {
            return 'blocked_clarification';
        }

        if (($flags['permission_required'] ?? false) === true) {
            return 'blocked_permission';
        }

        if (($flags['rate_limit_detected'] ?? false) === true) {
            return 'blocked_rate_limit';
        }

        return 'blocked';
    }

    private function normalizeClarificationExcerpt(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $excerpt = trim($value);
        if ($excerpt === '') {
            return null;
        }

        $decoded = null;
        if (Str::startsWith($excerpt, '{') || Str::startsWith($excerpt, '[')) {
            $decoded = json_decode($excerpt, true);
        }

        if (is_array($decoded)) {
            $candidate = $this->extractClarificationText($decoded);
            if (is_string($candidate) && trim($candidate) !== '') {
                $excerpt = trim($candidate);
            }
        }

        if (preg_match('/\\\\n/', $excerpt) === 1 && ! Str::contains($excerpt, "\n")) {
            $excerpt = str_replace('\\n', "\n", $excerpt);
        }

        return trim(Str::limit($excerpt, 1000, ''));
    }

    private function extractClarificationText(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $value = trim($payload);

            return $value === '' ? null : $value;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach (['text', 'message', 'excerpt', 'question', 'prompt', 'detail', 'content'] as $key) {
            if (is_string($payload[$key] ?? null) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        foreach (['item', 'payload', 'data', 'result', 'response', 'error'] as $key) {
            $candidate = $this->extractClarificationText($payload[$key] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        foreach ($payload as $key => $child) {
            if (in_array((string) $key, ['id', 'type', 'status', 'event_type', 'role'], true)) {
                continue;
            }

            $candidate = $this->extractClarificationText($child);
            if ($candidate !== null && preg_match('/\s/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
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

    private function buildTaskEditingConflict(InterrogationSession $session): ?JsonResponse
    {
        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_TASKS) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks can only be managed from the build tasks phase.', 409);
        }

        if (! in_array((string) $session->status, [
            InterrogationSession::STATUS_BUILD_TASKS,
            InterrogationSession::STATUS_PAUSED,
            InterrogationSession::STATUS_FAILED,
        ], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks cannot be managed in the current session state.', 409);
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Build tasks are still being generated.', 409);
        }

        return null;
    }

    private function resequenceBuildTasks(InterrogationSession $session): void
    {
        $tasks = $session->buildTasks()->ordered()->get();

        foreach ($tasks as $index => $task) {
            $nextSequence = $index + 1;
            if ((int) $task->sequence === $nextSequence) {
                continue;
            }

            $task->sequence = $nextSequence;
            $task->save();
        }
    }

    private function invalidateBuildTaskApproval(InterrogationSession $session): void
    {
        $build = $this->buildMetadata($session);
        $build['status'] = 'ready';
        $build['task_count'] = (int) $session->buildTasks()->count();
        $build['error'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['tasks_approved_at'] = null;
        $build['tasks_approved_by_user_id'] = null;
        $build['task_provider_sync'] = [
            'status' => 'idle',
            'driver' => null,
            'project_mode' => 'create_new',
            'project_id' => null,
            'project_name' => null,
            'project_url' => null,
            'synced_task_count' => 0,
            'error' => null,
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $this->saveBuildMetadata($session, $build);
    }

    private function normalizedNullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
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

    /**
     * @return array{provided:bool,rules:array<int,array<string,mixed>>}
     */
    private function resolveProjectRulesPayload(Request $request): array
    {
        $provided = $request->exists('project_rules') || $request->hasFile('project_rule_files');
        $rawRules = $request->input('project_rules');

        if (is_string($rawRules)) {
            $decoded = json_decode($rawRules, true);
            if ($rawRules !== '' && ! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'project_rules' => 'project_rules must be a valid JSON array when provided as text.',
                ]);
            }

            $rawRules = $decoded;
        }

        if ($rawRules === null) {
            $rawRules = [];
        }

        if (! is_array($rawRules)) {
            throw ValidationException::withMessages([
                'project_rules' => 'project_rules must be an array.',
            ]);
        }

        $rules = $this->normalizedProjectRules($rawRules, true);
        $uploadedRuleFiles = $request->file('project_rule_files', []);
        $uploads = is_array($uploadedRuleFiles) ? $uploadedRuleFiles : [$uploadedRuleFiles];
        $uploadRules = $this->normalizedProjectRulesFromUploads($uploads);

        return [
            'provided' => $provided,
            'rules' => array_merge($rules, $uploadRules),
        ];
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function normalizedProjectRules(array $rules, bool $strict = false): array
    {
        $normalized = [];

        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $title = trim((string) ($rule['title'] ?? $rule['name'] ?? ''));
            $markdown = trim((string) ($rule['markdown'] ?? $rule['content'] ?? ''));
            $source = strtolower(trim((string) ($rule['source'] ?? 'manual')));
            $filename = trim((string) ($rule['filename'] ?? ''));

            if ($title === '' && $markdown === '') {
                continue;
            }

            if ($markdown === '') {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'project_rules' => sprintf('project_rules[%d].markdown is required when title is provided.', (int) $index),
                    ]);
                }

                continue;
            }

            if (mb_strlen($title) > 120) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'project_rules' => sprintf('project_rules[%d].title must be 120 characters or fewer.', (int) $index),
                    ]);
                }

                $title = mb_substr($title, 0, 120);
            }

            if (mb_strlen($markdown) > 120000) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'project_rules' => sprintf('project_rules[%d].markdown must be 120000 characters or fewer.', (int) $index),
                    ]);
                }

                $markdown = mb_substr($markdown, 0, 120000);
            }

            if (! in_array($source, ['manual', 'uploaded'], true)) {
                $source = 'manual';
            }

            $normalized[] = [
                'id' => trim((string) ($rule['id'] ?? '')) !== '' ? substr(trim((string) $rule['id']), 0, 80) : 'rule-'.Str::uuid()->toString(),
                'title' => $title !== '' ? $title : 'Rule '.(count($normalized) + 1),
                'markdown' => $markdown,
                'source' => $source,
                'filename' => $filename !== '' ? substr($filename, 0, 255) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $uploads
     * @return array<int, array<string, mixed>>
     */
    private function normalizedProjectRulesFromUploads(array $uploads): array
    {
        $normalized = [];

        foreach ($uploads as $index => $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $rawContent = @file_get_contents($upload->getRealPath());
            if (! is_string($rawContent)) {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Could not read uploaded project rule file at index %d.', (int) $index),
                ]);
            }

            if (! mb_check_encoding($rawContent, 'UTF-8')) {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Uploaded project rule file "%s" must be UTF-8 encoded.', $upload->getClientOriginalName()),
                ]);
            }

            $markdown = trim($rawContent);
            if ($markdown === '') {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Uploaded project rule file "%s" is empty.', $upload->getClientOriginalName()),
                ]);
            }

            if (mb_strlen($markdown) > 120000) {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Uploaded project rule file "%s" exceeds 120000 characters.', $upload->getClientOriginalName()),
                ]);
            }

            $originalName = trim((string) $upload->getClientOriginalName());
            $title = pathinfo($originalName !== '' ? $originalName : 'Uploaded Rule '.($index + 1), PATHINFO_FILENAME);
            $title = trim((string) $title);

            $normalized[] = [
                'id' => 'rule-'.Str::uuid()->toString(),
                'title' => $title !== '' ? substr($title, 0, 120) : 'Uploaded Rule '.(count($normalized) + 1),
                'markdown' => $markdown,
                'source' => 'uploaded',
                'filename' => $originalName !== '' ? substr($originalName, 0, 255) : null,
            ];
        }

        return $normalized;
    }

    private function hasMeaningfulPlan(InterrogationSession $session): bool
    {
        if (! is_array($session->plan_json)) {
            return false;
        }

        $guard = new PlanPayloadGuard;
        $validation = $guard->validate($session->plan_json);

        return (bool) $validation['valid'];
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
            && ! $this->hasMeaningfulPlan($session)
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

    private function secondsSinceReference(mixed $reference, CarbonImmutable $now): float|null
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
     * @param  array<string, mixed>  $issues
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBuildIssues(array $issues): array
    {
        $normalized = [];

        foreach ($issues as $key => $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $code = trim((string) ($issue['code'] ?? strtoupper((string) $key)));
            $title = trim((string) ($issue['title'] ?? 'Issue detected'));
            $detail = trim((string) ($issue['detail'] ?? ''));
            $suggestedAction = trim((string) ($issue['suggested_action'] ?? ''));

            if ($title === '' || $detail === '') {
                continue;
            }

            $normalized[] = [
                'key' => (string) $key,
                'code' => $code,
                'title' => $title,
                'detail' => $detail,
                'suggested_action' => $suggestedAction !== '' ? $suggestedAction : null,
                'endpoint' => isset($issue['endpoint']) ? (string) $issue['endpoint'] : null,
                'count' => max(1, (int) ($issue['count'] ?? 1)),
                'first_detected_at' => isset($issue['first_detected_at']) ? (string) $issue['first_detected_at'] : null,
                'last_detected_at' => isset($issue['last_detected_at']) ? (string) $issue['last_detected_at'] : null,
                'excerpt' => isset($issue['excerpt']) ? (string) $issue['excerpt'] : null,
            ];
        }

        return $normalized;
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

    /** @phpstan-ignore method.unused */
    private function summaryOpenQuestionPrompt(string $openQuestion, int $ordinal, int $total, string $focus): string
    {
        return app(SummaryOpenQuestionQueueService::class)->buildPrompt($openQuestion, $ordinal, $total, $focus);
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
     * @return array<string, mixed>
     */
    private function normalizedPlanJson(InterrogationSession $session, bool $persist): array
    {
        if (! is_array($session->plan_json) || $session->plan_json === []) {
            return [];
        }

        $normalizer = new PlanPayloadNormalizer;
        $normalized = $normalizer->normalize($session->plan_json);

        if ($persist && $normalized !== $session->plan_json) {
            $session->plan_json = $normalized;
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
        /** @var InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_QUESTION) {
                continue;
            }

            $payload = (array) $event->payload;
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
        $resolvedOpenQuestionOrdinals = [];
        /** @var InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_ANSWER) {
                continue;
            }

            $payload = (array) $event->payload;
            $answerType = strtolower(trim((string) ($payload['answer_type'] ?? '')));
            if ($answerType === 'skip') {
                continue;
            }

            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $questionText = (string) ($questionsById[$questionId] ?? '');
            $fingerprint = $this->questionFingerprint($questionText);
            if ($fingerprint !== '') {
                $resolvedFingerprints[] = $fingerprint;
            }

            $ordinal = $this->extractOpenQuestionOrdinal($questionText);
            if ($ordinal !== null) {
                $resolvedOpenQuestionIndexes[] = $ordinal - 1;
            }

            $openQuestionOrdinal = $this->extractOpenQuestionMarkerOrdinal($questionText)
                ?? $this->extractOpenQuestionMarkerOrdinalFromQuestionId($questionId);
            if ($openQuestionOrdinal !== null) {
                $resolvedOpenQuestionOrdinals[] = $openQuestionOrdinal;
            }
        }

        if ($resolvedFingerprints === [] && $resolvedOpenQuestionIndexes === [] && $resolvedOpenQuestionOrdinals === []) {
            return $summary;
        }

        $resolvedFingerprints = array_values(array_unique($resolvedFingerprints));
        $resolvedOpenQuestionIndexes = array_values(array_unique(array_filter(
            $resolvedOpenQuestionIndexes,
            static fn (int $index): bool => $index >= 0
        )));
        $resolvedOpenQuestionIndexLookup = array_fill_keys($resolvedOpenQuestionIndexes, true);
        $resolvedOpenQuestionOrdinals = array_values(array_unique(array_filter(
            $resolvedOpenQuestionOrdinals,
            static fn (int $ordinal): bool => $ordinal > 0
        )));
        $resolvedOpenQuestionOrdinalLookup = array_fill_keys($resolvedOpenQuestionOrdinals, true);

        $remaining = [];
        foreach ($openQuestions as $openIndex => $openQuestion) {
            if (! is_string($openQuestion) || trim($openQuestion) === '') {
                continue;
            }

            if (isset($resolvedOpenQuestionIndexLookup[$openIndex])) {
                continue;
            }

            $openQuestionOrdinal = $this->extractOpenQuestionMarkerOrdinal($openQuestion);
            if ($openQuestionOrdinal !== null && isset($resolvedOpenQuestionOrdinalLookup[$openQuestionOrdinal])) {
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
        $normalized = preg_replace('/^open\s+question\s+\d+\s+of\s+\d+\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^open\s+question\s+\d+\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^oq\s*[-_ ]?\d+\s*:\s*/i', '', $normalized) ?? $normalized;
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

        if (preg_match('/open\s+question\s+(\d+)\b/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) $matches[1];

        return $ordinal > 0 ? $ordinal : null;
    }

    private function extractOpenQuestionMarkerOrdinal(string $value): ?int
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\boq\s*[-_ ]?(\d+)\b/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) $matches[1];

        return $ordinal > 0 ? $ordinal : null;
    }

    private function extractOpenQuestionMarkerOrdinalFromQuestionId(string $questionId): ?int
    {
        $normalized = strtolower(trim($questionId));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(?:^|[^a-z0-9])oq[-_ ]?(\d+)(?:[^a-z0-9]|$)/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) $matches[1];

        return $ordinal > 0 ? $ordinal : null;
    }
}
