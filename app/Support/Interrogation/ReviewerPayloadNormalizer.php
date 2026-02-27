<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

final class ReviewerPayloadNormalizer
{
    private const MAX_CLARIFICATION_QUESTIONS = 3;

    public function normalize(array $payload): array
    {
        return [
            'verdict' => $this->normalizeVerdict($payload),
            'issues' => $this->normalizeIssues($payload['issues'] ?? []),
            'required_changes' => $this->normalizeStringArray($payload['required_changes'] ?? []),
            'clarification_questions' => $this->normalizeClarificationQuestions($payload['clarification_questions'] ?? []),
            'confidence' => $this->normalizeConfidence($payload['confidence'] ?? 1.0),
            'review_notes' => trim((string) ($payload['review_notes'] ?? '')),
        ];
    }

    private function normalizeVerdict(array $payload): string
    {
        return strtolower(trim((string) ($payload['verdict'] ?? 'pass')));
    }

    private function normalizeIssues(array $issues): array
    {
        return array_values(array_map(function (array $issue): array {
            return [
                'type' => trim((string) ($issue['type'] ?? 'unknown')),
                'severity' => strtolower(trim((string) ($issue['severity'] ?? 'low'))),
                'message' => trim((string) ($issue['message'] ?? '')),
                'evidence' => trim((string) ($issue['evidence'] ?? '')),
            ];
        }, $issues));
    }

    private function normalizeStringArray(array $items): array
    {
        return array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $items),
            fn (string $item) => $item !== ''
        ));
    }

    private function normalizeClarificationQuestions(array $questions): array
    {
        $filtered = $this->normalizeStringArray($questions);

        return array_slice($filtered, 0, self::MAX_CLARIFICATION_QUESTIONS);
    }

    private function normalizeConfidence(mixed $confidence): float
    {
        $value = (float) $confidence;

        return max(0.0, min(1.0, $value));
    }
}
