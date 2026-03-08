<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;

class ConversationReconstructor
{
    public function reconstruct(InterrogationSession $session): string
    {
        $events = InterrogationEvent::query()
            ->where('interrogation_session_id', $session->id)
            ->orderBy('sequence')
            ->get();

        $lines = [];

        foreach ($events as $event) {
            $payload = is_array($event->payload) ? $event->payload : [];

            if ($event->event_type === InterrogationEvent::TYPE_QUESTION) {
                $questionId = trim((string) ($payload['question_id'] ?? ''));
                $prefix = $questionId !== '' ? 'Assistant Question ['.$questionId.']' : 'Assistant Question';
                $lines[] = $prefix.': '.(string) ($payload['question_text'] ?? '');
            }

            if ($event->event_type === InterrogationEvent::TYPE_ANSWER) {
                $questionId = trim((string) ($payload['question_id'] ?? ''));
                $answerType = strtolower(trim((string) ($payload['answer_type'] ?? 'freetext')));
                $answerText = trim((string) ($payload['answer_text'] ?? ''));
                $selectedOption = trim((string) ($payload['selected_option'] ?? ''));
                $selectedOptions = array_values(array_filter(
                    (array) ($payload['selected_options'] ?? []),
                    static fn ($value): bool => is_string($value) && trim($value) !== ''
                ));

                $answer = $answerText;
                if ($answer === '' && $selectedOption !== '') {
                    $answer = $selectedOption;
                }
                if ($answer === '' && $selectedOptions !== []) {
                    $answer = implode('; ', array_map(static fn (string $value): string => trim($value), $selectedOptions));
                }
                if ($answer === '') {
                    $answer = '(no answer content provided)';
                }

                $prefix = $questionId !== '' ? 'User Answer ['.$questionId.']' : 'User Answer';
                $lines[] = $prefix.' ('.$answerType.'): '.$answer;
            }

            if ($event->event_type === InterrogationEvent::TYPE_SUMMARY && isset($payload['summary_markdown'])) {
                $lines[] = 'Summary Draft: '.(string) $payload['summary_markdown'];
            }
        }

        if ($lines === []) {
            return 'No prior Q&A available. Ask the first requirements question.';
        }

        return implode("\n", $lines);
    }
}
