<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation\Analyzers;

class PatternMatchAnalyzer
{
    public function analyze(string $content): float
    {
        $patterns = config('skills-injection-patterns', []);
        $matchedCount = 0;
        $contentLower = mb_strtolower($content);

        foreach ($patterns as $category => $categoryPatterns) {
            foreach ($categoryPatterns as $pattern) {
                // Check if pattern contains regex metacharacters
                if ($this->isRegexPattern($pattern)) {
                    if (@preg_match('/' . $pattern . '/i', $content)) {
                        $matchedCount++;
                    }
                } else {
                    if (str_contains($contentLower, mb_strtolower($pattern))) {
                        $matchedCount++;
                    }
                }
            }
        }

        return min(1.0, $matchedCount * 0.15);
    }

    private function isRegexPattern(string $pattern): bool
    {
        return (bool) preg_match('/[.*+?^${}()|\\[\\]\\\\]/', $pattern);
    }
}
