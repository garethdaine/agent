<?php

namespace App\Support\Interrogation;

class InterrogationSemanticDeduper
{
    /**
     * @param  array<int, string>  $existingTexts
     * @return array{is_duplicate:bool,score:float,matched_text:string}
     */
    public function detectDuplicate(
        string $candidateText,
        array $existingTexts,
        float $threshold = 0.88,
    ): array {
        $candidateText = trim($candidateText);
        if ($candidateText === '' || $existingTexts === []) {
            return [
                'is_duplicate' => false,
                'score' => 0.0,
                'matched_text' => '',
            ];
        }

        $bestScore = 0.0;
        $matchedText = '';

        foreach ($existingTexts as $existingText) {
            if (! is_string($existingText)) {
                continue;
            }

            $score = $this->similarityScore($candidateText, $existingText);
            if ($score > $bestScore) {
                $bestScore = $score;
                $matchedText = $existingText;
            }
        }

        return [
            'is_duplicate' => $bestScore >= $threshold,
            'score' => $bestScore,
            'matched_text' => $matchedText,
        ];
    }

    public function similarityScore(string $left, string $right): float
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        $levenshteinScore = $this->normalizedLevenshteinSimilarity($left, $right);
        $jaccardScore = $this->jaccardTokenSimilarity($left, $right);
        similar_text($left, $right, $similarTextPercent);
        $similarTextScore = max(0.0, min(1.0, ((float) $similarTextPercent) / 100.0));

        return max($levenshteinScore, $jaccardScore, $similarTextScore);
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        return $normalized;
    }

    private function normalizedLevenshteinSimilarity(string $left, string $right): float
    {
        $maxLength = max(strlen($left), strlen($right));
        if ($maxLength === 0) {
            return 1.0;
        }

        return max(0.0, 1.0 - (levenshtein($left, $right) / $maxLength));
    }

    private function jaccardTokenSimilarity(string $left, string $right): float
    {
        $leftTokens = array_values(array_filter(explode(' ', $left), static fn (string $token): bool => $token !== ''));
        $rightTokens = array_values(array_filter(explode(' ', $right), static fn (string $token): bool => $token !== ''));

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $leftSet = array_fill_keys($leftTokens, true);
        $rightSet = array_fill_keys($rightTokens, true);
        $intersection = count(array_intersect_key($leftSet, $rightSet));
        $union = count($leftSet + $rightSet);

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }
}
