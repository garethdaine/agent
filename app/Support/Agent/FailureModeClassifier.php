<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentJob;
use App\Support\Agent\DTOs\FailureModeHint;

class FailureModeClassifier
{
    public function classify(array $reasoningSummary, AgentJob $job): ?FailureModeHint
    {
        $steps = $reasoningSummary['steps'] ?? [];

        if (empty($steps)) {
            return null;
        }

        $taskContent = $steps['task']['content'] ?? '';
        $actionContent = $steps['action']['content'] ?? '';
        $jobKeywords = $this->extractKeywords($job->name.' '.($job->description ?? ''));

        // Type 1: TASK missing expected subjects from job context
        if ($this->isType1Failure($taskContent, $jobKeywords)) {
            return new FailureModeHint(
                type: 1,
                confidence: 'medium',
                description: 'TASK step did not reference key subjects from the job definition',
                suggestedLesson: "When defining TASK for '{$job->name}', ensure the goal references: ".implode(', ', $jobKeywords)
            );
        }

        // Type 2: ACTION contradicts TASK framing
        if ($this->isType2Failure($taskContent, $actionContent)) {
            return new FailureModeHint(
                type: 2,
                confidence: 'low',
                description: 'ACTION step introduced concepts not aligned with TASK framing',
                suggestedLesson: 'Ensure ACTION steps directly follow from TASK goals without scope expansion'
            );
        }

        // Type 3: TASK correct but ACTION diverges
        if ($this->isType3Failure($taskContent, $actionContent, $jobKeywords)) {
            return new FailureModeHint(
                type: 3,
                confidence: 'low',
                description: 'TASK was correctly framed but ACTION did not follow through',
                suggestedLesson: 'Verify each ACTION step directly advances the stated TASK goal'
            );
        }

        return null;
    }

    private function extractKeywords(string $text): array
    {
        $words = preg_split('/\s+/', strtolower($text));
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        return array_values(array_filter($words, fn ($w) => strlen($w) >= 4 && ! in_array($w, $stopWords)));
    }

    private function isType1Failure(string $taskContent, array $jobKeywords): bool
    {
        if (empty($taskContent) || empty($jobKeywords)) {
            return false;
        }

        $taskLower = strtolower($taskContent);
        $matchCount = 0;

        foreach ($jobKeywords as $keyword) {
            if (str_contains($taskLower, $keyword)) {
                $matchCount++;
            }
        }

        // If less than 30% of keywords appear in TASK, likely Type 1
        return count($jobKeywords) > 0 && ($matchCount / count($jobKeywords)) < 0.3; // @phpstan-ignore greater.alwaysTrue
    }

    private function isType2Failure(string $taskContent, string $actionContent): bool
    {
        $contradictionPhrases = ['instead of', 'rather than', 'but first', 'however'];
        $actionLower = strtolower($actionContent);

        foreach ($contradictionPhrases as $phrase) {
            if (str_contains($actionLower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function isType3Failure(string $taskContent, string $actionContent, array $jobKeywords): bool
    {
        if (empty($taskContent) || empty($actionContent)) {
            return false;
        }

        $taskLower = strtolower($taskContent);
        $actionLower = strtolower($actionContent);

        // Check if TASK references job keywords (correct framing)
        $taskHasKeywords = false;
        foreach ($jobKeywords as $keyword) {
            if (str_contains($taskLower, $keyword)) {
                $taskHasKeywords = true;
                break;
            }
        }

        // Check if ACTION references job keywords (follows through)
        $actionHasKeywords = false;
        foreach ($jobKeywords as $keyword) {
            if (str_contains($actionLower, $keyword)) {
                $actionHasKeywords = true;
                break;
            }
        }

        // Type 3: TASK correct, ACTION diverges
        return $taskHasKeywords && ! $actionHasKeywords;
    }
}
