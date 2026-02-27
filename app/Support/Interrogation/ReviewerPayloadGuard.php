<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use InvalidArgumentException;

final class ReviewerPayloadGuard
{
    private const VALID_VERDICTS = ['pass', 'revise', 'needs_clarification'];

    private const VALID_SEVERITIES = ['low', 'medium', 'high', 'critical'];

    private const MAX_CLARIFICATION_QUESTIONS = 3;

    public function validate(array $payload, string $artifactType): bool
    {
        $this->validateVerdict($payload);
        $this->validateIssues($payload);
        $this->validateConfidence($payload);
        $this->validateClarificationQuestions($payload, $artifactType);
        $this->validateRequiredChanges($payload);

        return true;
    }

    private function validateVerdict(array $payload): void
    {
        if (! isset($payload['verdict'])) {
            throw new InvalidArgumentException('Reviewer payload missing required field: verdict');
        }

        if (! in_array($payload['verdict'], self::VALID_VERDICTS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid verdict "%s". Must be one of: %s', $payload['verdict'], implode(', ', self::VALID_VERDICTS))
            );
        }
    }

    private function validateIssues(array $payload): void
    {
        $issues = $payload['issues'] ?? [];

        if (! is_array($issues)) {
            throw new InvalidArgumentException('Reviewer payload issues must be an array');
        }

        foreach ($issues as $index => $issue) {
            if (! is_array($issue)) {
                throw new InvalidArgumentException(sprintf('Issue at index %d must be an array', $index));
            }

            if (! isset($issue['severity'])) {
                throw new InvalidArgumentException(sprintf('Issue at index %d missing severity', $index));
            }

            $severity = strtolower((string) $issue['severity']);
            if (! in_array($severity, self::VALID_SEVERITIES, true)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid severity "%s" at index %d. Must be one of: %s', $issue['severity'], $index, implode(', ', self::VALID_SEVERITIES))
                );
            }

            if (! isset($issue['message']) || ! is_string($issue['message'])) {
                throw new InvalidArgumentException(sprintf('Issue at index %d missing message string', $index));
            }
        }
    }

    private function validateConfidence(array $payload): void
    {
        if (! isset($payload['confidence'])) {
            return; // Optional field
        }

        $confidence = $payload['confidence'];

        if (! is_numeric($confidence)) {
            throw new InvalidArgumentException('Reviewer payload confidence must be numeric');
        }

        if ($confidence < 0 || $confidence > 1) {
            throw new InvalidArgumentException('Reviewer payload confidence must be between 0 and 1');
        }
    }

    private function validateClarificationQuestions(array $payload, string $artifactType): void
    {
        $questions = $payload['clarification_questions'] ?? [];

        if (! is_array($questions)) {
            throw new InvalidArgumentException('clarification_questions must be an array');
        }

        if (count($questions) > 0 && $artifactType === 'plan') {
            throw new InvalidArgumentException('clarification_questions not allowed during plan review');
        }

        if (count($questions) > self::MAX_CLARIFICATION_QUESTIONS) {
            throw new InvalidArgumentException(
                sprintf('clarification_questions exceeds maximum of %d', self::MAX_CLARIFICATION_QUESTIONS)
            );
        }
    }

    private function validateRequiredChanges(array $payload): void
    {
        $changes = $payload['required_changes'] ?? [];

        if (! is_array($changes)) {
            throw new InvalidArgumentException('required_changes must be an array');
        }
    }
}
