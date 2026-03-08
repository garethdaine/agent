<?php

declare(strict_types=1);

namespace App\Support\Security;

class PromptSanitizer
{
    /**
     * Patterns that could be used for prompt injection attacks.
     * These match system-level instruction markers and common injection patterns.
     */
    private const INJECTION_PATTERNS = [
        '/\<\|system\|>/i',
        '/\<\|user\|>/i',
        '/\<\|assistant\|>/i',
        '/\<\|endoftext\|>/i',
        '/\[INST\]/i',
        '/\[\/INST\]/i',
        '/\<\<SYS\>\>/i',
        '/\<\<\/SYS\>\>/i',
        '/```system\b/i',
        '/<system>/i',
        '/<\/system>/i',
        '/\{\{#system\}\}/i',
        '/\{\{\/system\}\}/i',
        '/\bSYSTEM:\s/i',
        '/\bHUMAN:\s/i',
        '/\bASSISTANT:\s/i',
    ];

    /**
     * Patterns for template delimiters that could be used to escape prompt boundaries.
     */
    private const TEMPLATE_DELIMITER_PATTERNS = [
        '/\{\{\{[^}]+\}\}\}/',
        '/\{\{[^}]+\}\}/',
    ];

    /**
     * Sanitize user-provided content before prompt concatenation.
     * Strips system-level instruction markers and injection patterns.
     */
    public static function sanitize(string $input): string
    {
        $result = $input;

        foreach (self::INJECTION_PATTERNS as $pattern) {
            $result = (string) preg_replace($pattern, '', $result);
        }

        return trim($result);
    }

    /**
     * Sanitize content that will be used in template substitution.
     * Strips both injection markers and template delimiters.
     */
    public static function sanitizeForTemplate(string $input): string
    {
        $result = self::sanitize($input);

        foreach (self::TEMPLATE_DELIMITER_PATTERNS as $pattern) {
            $result = (string) preg_replace($pattern, '', $result);
        }

        return trim($result);
    }

    /**
     * Check if input contains any injection patterns.
     */
    public static function containsInjectionPatterns(string $input): bool
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}
