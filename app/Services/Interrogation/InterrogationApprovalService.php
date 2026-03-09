<?php

declare(strict_types=1);

namespace App\Services\Interrogation;

use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\SessionStateTransitionService;

class InterrogationApprovalService
{
    public function __construct(
        private readonly InterrogationPlanService $planService,
        private readonly InterrogationBuildService $buildService,
    ) {}

    public function isPlanAlreadyApproved(InterrogationSession $session): bool
    {
        return (int) $session->phase > InterrogationSession::PHASE_PLANNING && $session->approved_at !== null;
    }

    public function validatePlanApprovalEligibility(InterrogationSession $session): ?string
    {
        if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING) {
            return 'Plan can only be approved from planning phase.';
        }

        if ($session->status === InterrogationSession::STATUS_FAILED) {
            return 'Failed session must be retried before plan approval.';
        }

        if (! $this->planService->hasMeaningfulPlan($session)) {
            return 'Plan is not available to approve.';
        }

        return null;
    }

    public function validateBuildTaskApprovalEligibility(InterrogationSession $session): ?string
    {
        return $this->buildService->checkApproveTasksPreconditions($session);
    }

    public function validateBuildStartEligibility(InterrogationSession $session, bool $restartFailed = false): ?string
    {
        return $this->buildService->checkStartBuildPreconditions($session, $restartFailed);
    }

    public function pause(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
    ): ?array {
        $transitioned = $transitions->transition(
            (int) $session->id,
            InterrogationSession::ACTIVE_STATUSES,
            InterrogationSession::STATUS_PAUSED,
        );

        if (! $transitioned) {
            return null;
        }

        $session->refresh();

        return [
            'session_id' => $session->id,
            'status' => $session->status,
        ];
    }

    public function resume(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
    ): ?array {
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
            return null;
        }

        $session->refresh();

        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'phase' => $session->phase,
        ];
    }

    /**
     * @return array{accepted:bool,queued:bool,pending_question_id:?string,session_id:int,status:string,phase:int}|null
     */
    public function retry(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
    ): ?array {
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
            return null;
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
            $this->planService->markPlanGenerationState($session, 'queued');
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

        return [
            'accepted' => true,
            'queued' => $queued,
            'pending_question_id' => $pendingQuestionId,
            'session_id' => (int) $session->id,
            'status' => (string) $session->status,
            'phase' => (int) $session->phase,
        ];
    }

    public function restartFromBeginning(InterrogationSession $session): array
    {
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

        return [
            'accepted' => true,
            'queued' => false,
            'session_id' => (int) $session->id,
            'status' => (string) $session->status,
            'phase' => (int) $session->phase,
        ];
    }

    public function destroy(InterrogationSession $session): void
    {
        $session->delete();
    }

    public function restore(InterrogationSession $session): void
    {
        $session->restore();
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

        $guard = new \App\Support\Interrogation\QuestionPayloadGuard;
        $validation = $guard->validate($payload);

        return (bool) $validation['valid'];
    }
}
