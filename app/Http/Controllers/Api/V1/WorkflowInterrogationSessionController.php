<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkflowInterrogator\StoreWorkflowInterrogationSessionRequest;
use App\Http\Requests\WorkflowInterrogator\SubmitWorkflowInterrogationBatchRequest;
use App\Jobs\GenerateWorkflowInterrogationPlanJob;
use App\Jobs\GenerateWorkflowInterrogationRoundJob;
use App\Models\WorkflowInterrogationAttachment;
use App\Models\WorkflowInterrogationBatch;
use App\Models\WorkflowInterrogationEvent;
use App\Models\WorkflowInterrogationSession;
use App\Support\Agent\ErrorEnvelope;
use App\Support\WorkflowInterrogator\WorkflowInterrogationAttachmentStore;
use App\Support\WorkflowInterrogator\WorkflowInterrogationBatchStore;
use App\Support\WorkflowInterrogator\WorkflowInterrogationEventWriter;
use App\Support\WorkflowInterrogator\WorkflowInterrogationExecutionService;
use App\Support\WorkflowInterrogator\WorkflowInterrogationPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkflowInterrogationSessionController extends Controller
{
    public function __construct(
        private readonly WorkflowInterrogationPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = WorkflowInterrogationSession::query()
            ->forUser((int) $request->user()->id)
            ->with(['attachments', 'activeBatch.questions.answer'])
            ->latest()
            ->get()
            ->map(fn (WorkflowInterrogationSession $session): array => $this->presenter->session($session, false))
            ->values();

        return response()->json(['data' => $sessions]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = $this->findSession($request, $id, ['attachments', 'activeBatch.questions.answer']);
        $data = $this->presenter->session($session, true);

        $data['events'] = $session->events()
            ->orderByDesc('sequence')
            ->limit(150)
            ->get()
            ->sortBy('sequence')
            ->values()
            ->map(fn (WorkflowInterrogationEvent $event): array => [
                'id' => $event->id,
                'sequence' => $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'event_ts' => optional($event->event_ts)?->toIso8601String(),
                'created_at' => optional($event->created_at)?->toIso8601String(),
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(
        StoreWorkflowInterrogationSessionRequest $request,
        WorkflowInterrogationAttachmentStore $attachmentStore,
    ): JsonResponse {
        $validated = $request->validated();

        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $request->user()->id,
            'name' => $validated['name'] ?? null,
            'runner_type' => $validated['runner_type'],
            'model' => $validated['model'] ?? null,
            'project_directory' => $validated['project_directory'],
            'interrogation_mode' => $validated['interrogation_mode'],
            'company_name' => $validated['company_name'],
            'company_description' => $validated['company_description'] ?? null,
            'workflow_title' => $validated['workflow_title'],
            'workflow_brief' => $validated['workflow_brief'],
            'target_teams_json' => array_values($validated['target_teams'] ?? []),
            'systems_json' => array_values($validated['systems'] ?? []),
            'status' => WorkflowInterrogationSession::STATUS_SETUP,
            'phase' => WorkflowInterrogationSession::PHASE_SETUP,
            'metadata_json' => [
                'source' => 'ui',
                'active_batch' => null,
                'ambiguity_report' => null,
                'build_runner_preference' => $validated['runner_type'],
            ],
        ]);

        (new WorkflowInterrogationEventWriter($session))->append(WorkflowInterrogationEvent::TYPE_SYSTEM, [
            'message' => 'Workflow interrogation session created.',
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);

        $uploads = $request->file('attachments', []);
        $attachmentUploads = is_array($uploads) ? $uploads : [$uploads];
        $attachmentUploads = array_values(array_filter($attachmentUploads));

        try {
            if ($attachmentUploads !== []) {
                $attachmentStore->storeUploads($session, $attachmentUploads);
            }
        } catch (\Throwable $throwable) {
            $session->delete();

            throw $throwable;
        }

        $session->load('attachments');

        return response()->json(['data' => $this->presenter->session($session, false)], 202);
    }

    public function start(Request $request, int $id, WorkflowInterrogationExecutionService $executionService): JsonResponse
    {
        $session = $this->findSession($request, $id);

        if ($this->isProcessing($session)) {
            return ErrorEnvelope::make('SESSION_BUSY', 'This interrogation session is already processing queued work.', 409);
        }

        $executionService->queueRoundGeneration($session, []);
        GenerateWorkflowInterrogationRoundJob::dispatch((int) $session->id);

        return response()->json(['data' => $this->presenter->session($session->fresh(), true)], 202);
    }

    public function submitBatch(
        SubmitWorkflowInterrogationBatchRequest $request,
        int $id,
        WorkflowInterrogationExecutionService $executionService,
        WorkflowInterrogationBatchStore $batchStore,
    ): JsonResponse {
        $session = $this->findSession($request, $id);
        $validated = $request->validated();
        $answers = array_values($validated['answers']);

        if ($this->isProcessing($session)) {
            return ErrorEnvelope::make('SESSION_BUSY', 'This interrogation session is already processing queued work.', 409);
        }

        $activeBatch = $batchStore->activeBatchForSession($session);
        if (! $activeBatch instanceof WorkflowInterrogationBatch || $activeBatch->questions->isEmpty()) {
            return ErrorEnvelope::make('NO_ACTIVE_BATCH', 'There is no active question batch to submit.', 409);
        }

        $batchStore->recordAnswers($activeBatch, $answers);

        $writer = new WorkflowInterrogationEventWriter($session);
        $writer->append(WorkflowInterrogationEvent::TYPE_ANSWER_BATCH, [
            'batch_id' => (int) $activeBatch->id,
            'round' => (int) $activeBatch->round,
            'answer_count' => count($answers),
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);

        $executionService->queueRoundGeneration($session, $answers);
        GenerateWorkflowInterrogationRoundJob::dispatch((int) $session->id, $answers);

        return response()->json(['data' => $this->presenter->session($session->fresh(['activeBatch.questions.answer']), true)], 202);
    }

    public function generatePlan(Request $request, int $id, WorkflowInterrogationExecutionService $executionService): JsonResponse
    {
        $session = $this->findSession($request, $id);

        if (! is_array($session->summary_json)) {
            return ErrorEnvelope::make('SUMMARY_REQUIRED', 'A confirmed summary is required before generating the action plan.', 409);
        }

        if ($this->isProcessing($session)) {
            return ErrorEnvelope::make('SESSION_BUSY', 'This interrogation session is already processing queued work.', 409);
        }

        $executionService->queuePlanGeneration($session);
        GenerateWorkflowInterrogationPlanJob::dispatch((int) $session->id);

        return response()->json(['data' => $this->presenter->session($session->fresh(), true)], 202);
    }

    public function downloadAttachment(Request $request, int $id, int $attachmentId): BinaryFileResponse
    {
        $session = $this->findSession($request, $id, ['attachments']);
        $attachment = $session->attachments->firstWhere('id', $attachmentId);

        if (! $attachment instanceof WorkflowInterrogationAttachment) {
            abort(404);
        }

        $disk = Storage::disk($attachment->storage_disk);
        abort_unless(method_exists($disk, 'path'), 404);

        $path = $disk->path($attachment->storage_path);
        abort_unless(is_string($path) && $path !== '' && file_exists($path), 404);

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($attachment->filename)),
        ]);
    }

    private function findSession(Request $request, int $id, array $with = []): WorkflowInterrogationSession
    {
        return WorkflowInterrogationSession::query()
            ->forUser((int) $request->user()->id)
            ->with($with)
            ->findOrFail($id);
    }

    private function isProcessing(WorkflowInterrogationSession $session): bool
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $processing = is_array($metadata['processing'] ?? null) ? $metadata['processing'] : [];
        $state = strtolower(trim((string) ($processing['state'] ?? '')));

        return in_array($state, ['queued', 'running'], true);
    }
}
