<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\Jobs\ExecuteInterrogationRoundJob;
use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;

class SummaryOpenQuestionQueueService
{
    /**
     * @param  array<int, mixed>  $openQuestions
     * @return array<int, string>
     */
    public function normalizeOpenQuestionList(array $openQuestions): array
    {
        $normalized = [];
        $guard = new QuestionPayloadGuard;

        foreach ($openQuestions as $question) {
            if (! is_string($question)) {
                continue;
            }

            $text = trim($question);
            if ($text === '') {
                continue;
            }

            $validation = $guard->validate([
                'question_text' => $text,
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 0,
                'is_complete' => false,
            ]);
            if (! (bool) ($validation['valid'] ?? false)) {
                continue;
            }

            $normalized[] = $text;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $openQuestions
     * @return array<string, mixed>
     */
    public function buildQueue(array $openQuestions, string $focus): array
    {
        $timestamp = CarbonImmutable::now('UTC')->toIso8601String();

        return [
            'active' => true,
            'total' => count($openQuestions),
            'pending_questions' => array_values($openQuestions),
            'asked_questions' => [],
            'focus' => $focus,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getQueue(InterrogationSession $session): ?array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $queue = is_array($metadata['summary_open_question_queue'] ?? null)
            ? $metadata['summary_open_question_queue']
            : null;

        if ($queue === null || (bool) ($queue['active'] ?? false) !== true) {
            return null;
        }

        $pending = $this->normalizeOpenQuestionList((array) ($queue['pending_questions'] ?? []));
        $asked = array_values(array_filter(
            (array) ($queue['asked_questions'] ?? []),
            static fn ($item): bool => is_array($item)
        ));
        $active = is_array($queue['active_open_question'] ?? null) ? $queue['active_open_question'] : null;

        return [
            ...$queue,
            'active' => true,
            'total' => max(1, (int) ($queue['total'] ?? (count($pending) + count($asked)))),
            'pending_questions' => $pending,
            'asked_questions' => $asked,
            'active_open_question' => $active,
        ];
    }

    /**
     * @param  array<string, mixed>  $queue
     */
    public function persistQueue(InterrogationSession $session, array $queue): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['summary_open_question_queue'] = $queue;
        $session->metadata_json = $metadata;
        $session->save();
    }

    public function clearQueue(InterrogationSession $session): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        if (! array_key_exists('summary_open_question_queue', $metadata)) {
            return;
        }

        unset($metadata['summary_open_question_queue']);
        $session->metadata_json = $metadata;
        $session->save();
    }

    public function dispatchNextFromQueue(InterrogationSession $session): bool
    {
        $queue = $this->getQueue($session);
        if ($queue === null) {
            return false;
        }

        $pending = $this->normalizeOpenQuestionList((array) ($queue['pending_questions'] ?? []));
        if ($pending === []) {
            return false;
        }

        $nextQuestion = array_shift($pending);
        if (! is_string($nextQuestion) || trim($nextQuestion) === '') {
            return false;
        }

        $asked = array_values(array_filter(
            (array) ($queue['asked_questions'] ?? []),
            static fn ($item): bool => is_array($item)
        ));
        $total = max(1, (int) ($queue['total'] ?? (count($pending) + count($asked) + 1)));
        $ordinal = count($asked) + 1;
        $queue['pending_questions'] = array_values($pending);
        $queue['active_open_question'] = [
            'question_text' => $nextQuestion,
            'ordinal' => $ordinal,
            'total' => $total,
            'dispatched_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $queue['total'] = $total;
        $queue['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $this->persistQueue($session, $queue);

        $focus = trim((string) ($queue['focus'] ?? ''));
        $prompt = $this->buildPrompt($nextQuestion, $ordinal, $total, $focus);

        ExecuteInterrogationRoundJob::dispatch((int) $session->id, $prompt, true);

        return true;
    }

    public function buildPrompt(string $openQuestion, int $ordinal, int $total, string $focus): string
    {
        $prompt = 'Summary re-interrogation queue item '.$ordinal.' of '.$total.'. '
            .'Resolve this unresolved open question: "'.$openQuestion."\".\n"
            .'Ask exactly one high-signal question that resolves this item.\n'
            .'Prefer answer_type="choice" with 2-5 concrete options when there is a clear decision to make; otherwise use answer_type="freetext". '
            .'When using choice, options must be provided as a structured options[] array (not embedded in question_text). '
            .'Set category to "open-question". '
            .'Do not batch questions. Do not mark completion (is_complete must be false and progress_estimate must be < 100).';

        if ($focus !== '') {
            $prompt .= "\nAdditional user focus for this queue: ".$focus;
        }

        return $prompt;
    }

    /**
     * Set a metadata flag indicating that the next summary generation should
     * automatically continue interrogation if open questions remain.
     */
    public function markAutoReinterrogation(InterrogationSession $session, string $focus = ''): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['auto_continue_open_questions'] = true;
        $metadata['auto_continue_open_questions_focus'] = $focus;
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * Check and clear the auto-reinterrogation flag.
     *
     * @return array{active: bool, focus: string}
     */
    public function consumeAutoReinterrogationFlag(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $active = (bool) ($metadata['auto_continue_open_questions'] ?? false);
        $focus = trim((string) ($metadata['auto_continue_open_questions_focus'] ?? ''));

        if ($active) {
            unset($metadata['auto_continue_open_questions'], $metadata['auto_continue_open_questions_focus']);
            $session->metadata_json = $metadata;
            $session->save();
        }

        return ['active' => $active, 'focus' => $focus];
    }
}
