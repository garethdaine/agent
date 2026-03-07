<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation\Analyzers;

class BoundaryAnalyzer
{
    private const ZERO_WIDTH_CHARS = [
        "\u{200B}", // Zero-width space
        "\u{200C}", // Zero-width non-joiner
        "\u{200D}", // Zero-width joiner
        "\u{FEFF}", // Byte order mark / zero-width no-break space
        "\u{00AD}", // Soft hyphen
    ];

    public function analyze(string $content): float
    {
        $findingsCount = 0;

        // Detect zero-width characters
        foreach (self::ZERO_WIDTH_CHARS as $char) {
            if (str_contains($content, $char)) {
                $findingsCount++;
            }
        }

        // Detect HTML comments with directive-like content
        if (preg_match_all('/<!--\s*(?:.*(?:ignore|override|bypass|execute|inject|directive|hidden|secret).*)\s*-->/i', $content, $matches)) {
            $findingsCount += count($matches[0]);
        }

        // Detect base64 strings longer than 50 chars
        if (preg_match_all('/[A-Za-z0-9+\/]{50,}={0,2}/', $content, $matches)) {
            $findingsCount += count($matches[0]);
        }

        return min(1.0, $findingsCount * 0.25);
    }
}
