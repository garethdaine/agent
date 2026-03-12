<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;

/**
 * Redacts credential values from outbound text to prevent secret exposure
 * in chat messages, Discord responses, and other untrusted output channels.
 *
 * Defence-in-depth: even if the LLM agent includes credentials in its response,
 * this redactor strips them before they reach the user.
 */
class CredentialRedactor
{
    private const string REDACTED = '[REDACTED]';

    /**
     * Sensitive field names sourced from config/credentials.php types.*.secret_fields.
     * These are the JSON keys and label patterns to match.
     */
    private const array SENSITIVE_LABELS = [
        'password',
        'api_key',
        'api_secret',
        'bearer_token',
        'access_token',
        'refresh_token',
        'client_id',
        'client_secret',
        'private_key',
        'passphrase',
        'totp_secret',
        'secret',
        'token',
        'secret_key',
    ];

    /**
     * Redact credential values from a string.
     *
     * Matches multiple formats:
     * - JSON:     "password":"value" or "password": "value"
     * - Markdown: **Password:** value or **Password**: value
     * - Labels:   Password: value
     * - Inline:   password=value or password = value
     *
     * Returns the redacted string and count of redactions made.
     *
     * @return array{content: string, redactions: int}
     */
    public function redact(string $content): array
    {
        $redactions = 0;

        // Build alternation from sensitive labels (case-insensitive labels)
        // Replace underscores with [_ ] so "api_key" matches both "api_key" and "API Key"
        $labelPattern = implode('|', array_map(
            fn (string $label) => str_replace('_', '[_ ]', preg_quote($label, '/')),
            self::SENSITIVE_LABELS
        ));

        // 1. JSON string values: "password":"value" or "password": "value"
        $content = preg_replace_callback(
            '/("(?:'.$labelPattern.')")\s*:\s*"([^"]+)"/i',
            function (array $matches) use (&$redactions): string {
                $redactions++;

                return $matches[1].':"'.self::REDACTED.'"';
            },
            $content
        ) ?? $content;

        // 2. Markdown bold labels: **Password:** value or **Password**: value
        //    Handles colon inside bold (**Password:**) or outside (**Password**:)
        //    Excludes backtick-wrapped values (handled by pattern 3)
        $content = preg_replace_callback(
            '/(\*\*(?:'.$labelPattern.')(?::?\s*)\*\*\s*:?\s*)([^\n*\[`]+|\[REDACTED\])/i',
            function (array $matches) use (&$redactions): string {
                $value = trim($matches[2]);
                if ($value === '' || $value === self::REDACTED) {
                    return $matches[0];
                }
                $redactions++;

                return $matches[1].self::REDACTED;
            },
            $content
        ) ?? $content;

        // 3. Backtick-wrapped values after labels: **Password:** `value` or Password: `value`
        //    Handles colon inside bold (**Password:**) or outside (**Password**:)
        $content = preg_replace_callback(
            '/((?:\*\*)?(?:'.$labelPattern.')(?::?\s*(?:\*\*)?\s*:?\s*))`([^`]+)`/i',
            function (array $matches) use (&$redactions): string {
                $redactions++;

                return $matches[1].'`'.self::REDACTED.'`';
            },
            $content
        ) ?? $content;

        // 4. Plain label: value (at start of line or after bullet)
        //    e.g. "Password: mySecret123" or "- Password: mySecret123"
        $content = preg_replace_callback(
            '/^([\s\-*]*(?:'.$labelPattern.')\s*:\s*)(\S[^\n]*)/im',
            function (array $matches) use (&$redactions): string {
                $value = trim($matches[2]);
                // Skip if already redacted (including with markdown cruft like "** [REDACTED]")
                // or looks like a description/instruction
                if ($value === self::REDACTED || str_contains($value, self::REDACTED) || str_word_count($value) > 6) {
                    return $matches[0];
                }
                $redactions++;

                return $matches[1].self::REDACTED;
            },
            $content
        ) ?? $content;

        // 5. Env var assignments: VAULT_TOKEN=xyz, PASSWORD=xyz
        $content = preg_replace_callback(
            '/\b((?:'.implode('|', array_map(fn ($l) => preg_quote(strtoupper($l), '/'), self::SENSITIVE_LABELS)).'))\s*=\s*([^\s&|;]+)/i',
            function (array $matches) use (&$redactions): string {
                if (str_starts_with($matches[2], '$')) {
                    return $matches[0]; // Don't redact variable references like $VAULT_TOKEN
                }
                $redactions++;

                return $matches[1].'='.self::REDACTED;
            },
            $content
        ) ?? $content;

        if ($redactions > 0) {
            Log::channel('runtime')->warning('CredentialRedactor: Redacted credential values from outbound content', [
                'redactions' => $redactions,
            ]);
        }

        return ['content' => $content, 'redactions' => $redactions];
    }
}
