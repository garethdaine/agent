<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Models\WorkflowInterrogationEvent;
use App\Models\WorkflowInterrogationSession;
use App\Support\WorkflowInterrogator\Contracts\WorkflowInterrogatorClient;
use Carbon\CarbonImmutable;

class WorkflowInterrogationExecutionService
{
    public function __construct(
        private readonly WorkflowInterrogatorClient $client,
        private readonly WorkflowInterrogationBatchStore $batchStore,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $latestAnswers
     */
    public function queueRoundGeneration(WorkflowInterrogationSession $session, array $latestAnswers = []): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['processing'] = [
            'kind' => 'round',
            'state' => 'queued',
            'queued_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $metadata['active_batch_id'] = null;

        $session->status = WorkflowInterrogationSession::STATUS_INTERROGATING;
        $session->phase = WorkflowInterrogationSession::PHASE_INTERROGATION;
        $session->started_at = $session->started_at ?? now('UTC');
        $session->error_code = null;
        $session->error_summary = null;
        $session->metadata_json = $metadata;
        $session->save();

        (new WorkflowInterrogationEventWriter($session))->append(WorkflowInterrogationEvent::TYPE_SYSTEM, [
            'notice' => 'workflow_interrogation_round_queued',
            'message' => $latestAnswers === []
                ? 'First interrogation batch queued.'
                : 'Next interrogation batch queued.',
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);
    }

    public function queuePlanGeneration(WorkflowInterrogationSession $session): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['processing'] = [
            'kind' => 'plan',
            'state' => 'queued',
            'queued_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $session->status = WorkflowInterrogationSession::STATUS_PLANNING;
        $session->phase = WorkflowInterrogationSession::PHASE_ACTION_PLAN;
        $session->error_code = null;
        $session->error_summary = null;
        $session->metadata_json = $metadata;
        $session->save();

        (new WorkflowInterrogationEventWriter($session))->append(WorkflowInterrogationEvent::TYPE_SYSTEM, [
            'notice' => 'workflow_interrogation_plan_queued',
            'message' => 'Action plan generation queued.',
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $latestAnswers
     */
    public function executeRound(int $sessionId, array $latestAnswers = []): void
    {
        $session = WorkflowInterrogationSession::query()->find($sessionId);

        if ($session === null || in_array($session->status, [
            WorkflowInterrogationSession::STATUS_COMPLETED,
            WorkflowInterrogationSession::STATUS_FAILED,
        ], true)) {
            return;
        }

        $this->markProcessingState($session, 'round', 'running');

        try {
            $history = $this->conversationHistory($session);
            $result = $this->client->generateRound($session, $history, $latestAnswers);

            if (is_string($result['cli_session_id'] ?? null) && trim((string) $result['cli_session_id']) !== '') {
                $session->cli_session_id = trim((string) $result['cli_session_id']);
            }

            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $metadata['ambiguity_report'] = $result['ambiguity_report'] ?? [];

            $needsAnotherRound = (bool) data_get($result, 'ambiguity_report.needs_another_round', true);
            $questions = array_values(array_filter(
                (array) ($result['questions'] ?? []),
                static fn ($question): bool => is_array($question)
            ));

            $writer = new WorkflowInterrogationEventWriter($session);

            if ($needsAnotherRound && $questions !== []) {
                $nextRound = (int) $session->current_round + 1;
                $batch = $this->batchStore->createActiveBatch($session, $nextRound, $questions);

                $session->status = WorkflowInterrogationSession::STATUS_INTERROGATING;
                $session->phase = WorkflowInterrogationSession::PHASE_INTERROGATION;
                $session->current_round = $nextRound;
                $metadata['active_batch_id'] = (int) $batch->id;
                $metadata['processing'] = [
                    'kind' => 'round',
                    'state' => 'idle',
                    'completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ];
                $session->metadata_json = $metadata;
                $session->save();

                $writer->append(WorkflowInterrogationEvent::TYPE_QUESTION_BATCH, [
                    'batch_id' => (int) $batch->id,
                    'round' => (int) $batch->round,
                    'question_count' => $batch->questions->count(),
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]);

                return;
            }

            $session->status = WorkflowInterrogationSession::STATUS_SUMMARY_READY;
            $session->phase = WorkflowInterrogationSession::PHASE_SUMMARY;
            $session->summary_json = is_array($result['summary'] ?? null) ? $result['summary'] : [];
            $session->summary_confirmed_at = now('UTC');
            $metadata['active_batch_id'] = null;
            $metadata['processing'] = [
                'kind' => 'round',
                'state' => 'idle',
                'completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];
            $session->metadata_json = $metadata;
            $session->save();

            $writer->append(WorkflowInterrogationEvent::TYPE_SUMMARY, [
                'summary_ready' => true,
                'ambiguity_report' => $result['ambiguity_report'] ?? [],
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);
        } catch (\Throwable $throwable) {
            $this->failSession($session, 'ROUND_GENERATION_FAILED', $throwable->getMessage(), 'round');
        }
    }

    public function executePlan(int $sessionId): void
    {
        $session = WorkflowInterrogationSession::query()->find($sessionId);

        if ($session === null || ! is_array($session->summary_json)) {
            return;
        }

        $this->markProcessingState($session, 'plan', 'running');

        try {
            $history = $this->conversationHistory($session);
            $plan = $this->client->generateActionPlan($session, $history, $session->summary_json);

            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $metadata['processing'] = [
                'kind' => 'plan',
                'state' => 'idle',
                'completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            $session->action_plan_json = $plan;
            $session->status = WorkflowInterrogationSession::STATUS_COMPLETED;
            $session->phase = WorkflowInterrogationSession::PHASE_COMPLETED;
            $session->finished_at = now('UTC');
            $session->summary_confirmed_at = $session->summary_confirmed_at ?? now('UTC');
            $session->metadata_json = $metadata;
            $session->save();

            (new WorkflowInterrogationEventWriter($session))->append(WorkflowInterrogationEvent::TYPE_ACTION_PLAN, [
                'action_plan_ready' => true,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);
        } catch (\Throwable $throwable) {
            $this->failSession($session, 'ACTION_PLAN_GENERATION_FAILED', $throwable->getMessage(), 'plan');
        }
    }

    private function markProcessingState(WorkflowInterrogationSession $session, string $kind, string $state): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['processing'] = [
            'kind' => $kind,
            'state' => $state,
            $state === 'running' ? 'started_at' : 'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function conversationHistory(WorkflowInterrogationSession $session): array
    {
        return $session->events()
            ->orderBy('sequence')
            ->get(['event_type', 'payload', 'sequence'])
            ->map(fn (WorkflowInterrogationEvent $event): array => [
                'sequence' => (int) $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload ?? [],
            ])
            ->values()
            ->all();
    }

    private function failSession(
        WorkflowInterrogationSession $session,
        string $code,
        string $message,
        string $kind,
    ): void {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['processing'] = [
            'kind' => $kind,
            'state' => 'failed',
            'failed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $session->status = WorkflowInterrogationSession::STATUS_FAILED;
        $session->error_code = $code;
        $session->error_summary = $message;
        $session->metadata_json = $metadata;
        $session->save();

        (new WorkflowInterrogationEventWriter($session))->append(WorkflowInterrogationEvent::TYPE_ERROR, [
            'code' => $code,
            'message' => $message,
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);
    }
}
