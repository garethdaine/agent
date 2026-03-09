<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\QuestionPayloadGuard;

class PruneInvalidQuestionEventsAction
{
    /**
     * @return array{removed_question_events: int, removed_answer_events: int, removed_question_ids: array<int, string>}
     */
    public function execute(InterrogationSession $session): array
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
}
