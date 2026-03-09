<?php

declare(strict_types=1);

namespace App\Support\Agent;

class OutputRedactor
{
    /**
     * @var array<string, string>
     */
    private const PATTERNS = [
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----/m' => '[REDACTED_PRIVATE_KEY]',
        '/\bBearer\s+[A-Za-z0-9._\-]+\b/i' => '[REDACTED_BEARER_TOKEN]',
        '/\bsk-[a-zA-Z0-9]{20,}\b/' => '[REDACTED_API_KEY]',
        '/\bAKIA[A-Z0-9]{16}\b/' => '[REDACTED_AWS_KEY]',
        '/\b(?:api[_-]?key|apikey)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED_API_KEY]',
        '/\b(?:password|passwd|pwd)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED_PASSWORD]',
        '/\b(?:secret|token|credential)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED]',
        '/[\w.\-+]+@[\w.\-]+\.[a-zA-Z]{2,}/' => '[REDACTED_EMAIL]',
    ];

    public function redact(string $payload, int &$redactionCount): string
    {
        foreach (self::PATTERNS as $pattern => $replacement) {
            $payload = preg_replace_callback($pattern, function () use ($replacement, &$redactionCount) {
                $redactionCount++;

                return $replacement;
            }, $payload) ?? $payload;
        }

        return $payload;
    }

    public function isBinaryChunk(string $chunk): bool
    {
        $sample = substr($chunk, 0, 1024);
        $length = strlen($sample);

        if ($length === 0) {
            return false;
        }

        $nonPrintable = preg_match_all('/[^\x09\x0A\x0D\x20-\x7E]/', $sample);

        return is_int($nonPrintable) && ($nonPrintable / $length) > 0.30;
    }
}
