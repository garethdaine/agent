<?php

namespace App\Support\Interrogation;

class QuestionPayloadGuard
{
    /**
     * @param  array<string, mixed>  $question
     * @return array{valid:bool,reason:string}
     */
    public function validate(array $question): array
    {
        $text = trim((string) ($question['question_text'] ?? ''));
        $lowerText = strtolower($text);
        $answerType = strtolower(trim((string) ($question['answer_type'] ?? '')));
        $progress = (int) ($question['progress_estimate'] ?? 0);
        $isComplete = (bool) ($question['is_complete'] ?? false);
        $options = is_array($question['options'] ?? null)
            ? array_values(array_filter($question['options'], static fn ($option): bool => is_string($option) && trim($option) !== ''))
            : [];

        if ($text === '') {
            return ['valid' => false, 'reason' => 'question_text is empty'];
        }

        if ($isComplete || $progress >= 100) {
            return ['valid' => true, 'reason' => 'completion marker'];
        }

        if ($answerType === '') {
            return ['valid' => false, 'reason' => 'answer_type is missing'];
        }

        if (preg_match('/\bQ\d+\s*:/i', $text) === 1) {
            return ['valid' => false, 'reason' => 'batched question markers detected'];
        }

        if (preg_match('/\bbatch\s+\d+\s+of\s+\d+\b/i', $text) === 1) {
            return ['valid' => false, 'reason' => 'batched question wording detected'];
        }

        if (preg_match('/(?:^|\n)\s*-\s*Option\s+[A-Z]/i', $text) === 1) {
            return ['valid' => false, 'reason' => 'options embedded in question_text'];
        }

        if (preg_match('/\bOption\s+[A-Z]\s*[—:-]/i', $text) === 1 && count($options) < 2) {
            return ['valid' => false, 'reason' => 'option-style text present but structured options are missing'];
        }

        if ($this->looksLikeOperationalStatusText($text, $lowerText)) {
            return ['valid' => false, 'reason' => 'operational status text was returned instead of a user-answerable question'];
        }

        if ($answerType === 'choice' && count($options) < 2) {
            return ['valid' => false, 'reason' => 'choice answer_type requires at least two structured options'];
        }

        if ($answerType === 'freetext' && count($options) >= 2) {
            return ['valid' => false, 'reason' => 'freetext answer_type cannot carry structured options'];
        }

        return ['valid' => true, 'reason' => 'ok'];
    }

    private function looksLikeOperationalStatusText(string $text, string $lowerText): bool
    {
        if (str_contains($lowerText, 'in this workspace/session state')) {
            return true;
        }

        $asksForVerbatimReplay = preg_match('/\b(?:paste|quote|repeat|restate|provide|copy)\b/i', $text) === 1
            && preg_match('/\b(?:exact|full\s+text|verbatim|word[- ]for[- ]word)\b/i', $text) === 1
            && preg_match('/\b(?:latest|previous|resumed|unanswered|question\s+text|interrogation\s+question|answer\s+text|that\s+question|user\'?s\s+answer)\b/i', $text) === 1;
        if ($asksForVerbatimReplay) {
            return true;
        }

        $asksForPriorQaReplay = preg_match('/\b(?:what\s+is|provide|paste|quote|repeat|restate|copy)\b/i', $text) === 1
            && preg_match('/\b(?:answer\s+(?:content|text)|question\s+text)\b/i', $text) === 1
            && preg_match('/\b(?:that\s+question|latest|previous|resumed|unanswered)\b/i', $text) === 1;
        if ($asksForPriorQaReplay) {
            return true;
        }

        $operationalVerb = preg_match('/\b(resum(?:e|ed|ing)|continu(?:e|ed|ing)|locat(?:e|ed|ing)|retriev(?:e|ed|ing)|load(?:ed|ing)?|search(?:ed|ing)?|inspect(?:ed|ing)?|analyz(?:e|ed|ing)|processing)\b/i', $text) === 1;
        $operationalContext = preg_match('/\b(workspace|session|state|thread|phase|saved|latest|runner output|interrogation)\b/i', $text) === 1;
        $isQuestion = str_contains($text, '?');

        return $operationalVerb && $operationalContext && ! $isQuestion;
    }
}
