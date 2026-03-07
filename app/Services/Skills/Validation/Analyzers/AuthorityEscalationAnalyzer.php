<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation\Analyzers;

class AuthorityEscalationAnalyzer
{
    private const SIMILARITY_THRESHOLD = 80;

    public function analyze(string $content): float
    {
        $patterns = config('skills-injection-patterns.authority_assertion', []);
        $matchedPhrases = 0;
        $contentLower = mb_strtolower($content);

        // Split content into chunks for fuzzy matching
        $words = preg_split('/\s+/', $contentLower);
        $chunks = [];
        $wordCount = count($words);

        for ($size = 3; $size <= 6 && $size <= $wordCount; $size++) {
            for ($i = 0; $i <= $wordCount - $size; $i++) {
                $chunks[] = implode(' ', array_slice($words, $i, $size));
            }
        }

        foreach ($patterns as $pattern) {
            $patternLower = mb_strtolower($pattern);

            // Exact substring match first
            if (str_contains($contentLower, $patternLower)) {
                $matchedPhrases++;

                continue;
            }

            // Fuzzy match using similar_text
            foreach ($chunks as $chunk) {
                similar_text($patternLower, $chunk, $percent);
                if ($percent >= self::SIMILARITY_THRESHOLD) {
                    $matchedPhrases++;
                    break;
                }
            }
        }

        return min(1.0, $matchedPhrases * 0.2);
    }
}
