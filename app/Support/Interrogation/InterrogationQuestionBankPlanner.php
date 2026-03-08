<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

class InterrogationQuestionBankPlanner
{
    /**
     * @param  array<int, array<string, mixed>>  $rawQuestions
     * @return array<int, array{
     *   canonical_key:string,
     *   question_id:string,
     *   prompt:string,
     *   answer_type:string,
     *   options:array<int,string>,
     *   depends_on:array<int,string>,
     *   category:string,
     *   decision_axis:string,
     *   rationale:string,
     *   priority:int
     * }>
     */
    public function normalize(array $rawQuestions): array
    {
        $normalized = [];
        $axisToCanonical = [];

        foreach ($rawQuestions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $category = $this->normalizeToken((string) ($question['category'] ?? 'general'));
            $decisionAxis = $this->normalizeToken((string) ($question['decision_axis'] ?? ''));
            $prompt = trim((string) ($question['prompt'] ?? ''));
            $answerType = strtolower(trim((string) ($question['answer_type'] ?? 'choice')));
            $priority = (int) ($question['priority'] ?? 50);
            $rationale = trim((string) ($question['rationale'] ?? ''));

            if ($decisionAxis === '' || $prompt === '') {
                continue;
            }

            if (! in_array($answerType, ['choice', 'freetext'], true)) {
                $answerType = 'choice';
            }

            $options = array_values(array_filter(
                (array) ($question['options'] ?? []),
                static fn ($option): bool => is_string($option) && trim($option) !== ''
            ));

            if ($answerType === 'choice' && count($options) < 2) {
                continue;
            }

            $canonicalKey = 'interrogation.'.$category.'.'.$decisionAxis;
            if (isset($axisToCanonical[$decisionAxis])) {
                continue;
            }

            $axisToCanonical[$decisionAxis] = $canonicalKey;

            $normalized[] = [
                'canonical_key' => $canonicalKey,
                'question_id' => $this->questionIdForCanonicalKey($canonicalKey),
                'prompt' => $prompt,
                'answer_type' => $answerType,
                'options' => $options,
                'depends_on' => [], // resolved in a second pass
                'category' => $category,
                'decision_axis' => $decisionAxis,
                'rationale' => $rationale,
                'priority' => max(1, min(100, $priority)),
                '_depends_on_axes' => array_values(array_filter(
                    (array) ($question['depends_on_axes'] ?? []),
                    static fn ($axis): bool => is_string($axis) && trim($axis) !== ''
                )),
            ];
        }

        foreach ($normalized as $index => $entry) {
            $dependsOn = [];
            foreach ($entry['_depends_on_axes'] as $axisName) {
                $axisKey = $this->normalizeToken($axisName);
                if ($axisKey === '') {
                    continue;
                }

                $canonicalDependency = $axisToCanonical[$axisKey] ?? null;
                if (is_string($canonicalDependency) && $canonicalDependency !== $entry['canonical_key']) {
                    $dependsOn[] = $canonicalDependency;
                }
            }

            $entry['depends_on'] = array_values(array_unique($dependsOn));
            unset($entry['_depends_on_axes']);
            $normalized[$index] = $entry;
        }

        usort($normalized, static function (array $left, array $right): int {
            $priorityDiff = $left['priority'] <=> $right['priority'];
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }

            return strcmp((string) $left['canonical_key'], (string) $right['canonical_key']);
        });

        return $normalized;
    }

    public function questionIdForCanonicalKey(string $canonicalKey): string
    {
        $normalized = trim(strtolower($canonicalKey));
        $normalized = str_replace('.', '-', $normalized);
        $normalized = preg_replace('/[^a-z0-9_-]+/u', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return $normalized === '' ? 'q-interrogation-decision' : 'q-'.$normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questionBank
     * @param  array<int, string>  $askedCanonicalKeys
     * @param  array<int, string>  $answeredCanonicalKeys
     * @param  array<int, string>  $suppressedCanonicalKeys
     * @param  array<int, string>  $askedQuestionTexts
     * @return array{question:?array<string,mixed>,suppressed:array<int,string>}
     */
    public function nextEligibleQuestion(
        array $questionBank,
        array $askedCanonicalKeys,
        array $answeredCanonicalKeys,
        array $suppressedCanonicalKeys,
        array $askedQuestionTexts,
        InterrogationSemanticDeduper $deduper,
        float $threshold = 0.88,
    ): array {
        $asked = array_fill_keys(array_values(array_unique($askedCanonicalKeys)), true);
        $answered = array_fill_keys(array_values(array_unique($answeredCanonicalKeys)), true);
        $suppressed = array_fill_keys(array_values(array_unique($suppressedCanonicalKeys)), true);
        $newSuppressed = [];

        foreach ($questionBank as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $canonicalKey = trim((string) ($entry['canonical_key'] ?? ''));
            if ($canonicalKey === '') {
                continue;
            }

            if (isset($asked[$canonicalKey]) || isset($suppressed[$canonicalKey])) {
                continue;
            }

            $dependsOn = array_values(array_filter(
                (array) ($entry['depends_on'] ?? []),
                static fn ($dependency): bool => is_string($dependency) && trim($dependency) !== ''
            ));

            $unmetDependency = false;
            foreach ($dependsOn as $dependency) {
                if (! isset($answered[$dependency])) {
                    $unmetDependency = true;
                    break;
                }
            }

            if ($unmetDependency) {
                continue;
            }

            $duplicate = $deduper->detectDuplicate((string) ($entry['prompt'] ?? ''), $askedQuestionTexts, $threshold);
            if ($duplicate['is_duplicate']) {
                $newSuppressed[] = $canonicalKey;

                continue;
            }

            return [
                'question' => $entry,
                'suppressed' => array_values(array_unique($newSuppressed)),
            ];
        }

        return [
            'question' => null,
            'suppressed' => array_values(array_unique($newSuppressed)),
        ];
    }

    private function normalizeToken(string $value): string
    {
        $normalized = trim(mb_strtolower($value));
        $normalized = preg_replace('/[^a-z0-9]+/u', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }
}
