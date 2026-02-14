<?php

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
                $lines[] = 'Assistant Question: '.(string) ($payload['question_text'] ?? '');
            }

            if ($event->event_type === InterrogationEvent::TYPE_ANSWER) {
                $lines[] = 'User Answer: '.(string) ($payload['answer_text'] ?? $payload['selected_option'] ?? '');
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
