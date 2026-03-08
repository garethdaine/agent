<?php

declare(strict_types=1);

namespace App\Support\Connectors\Webhooks;

class WebhookSignatureVerifier
{
    public function verify(string $payload, string $signature, string $secret, string $algorithm = 'sha256'): bool
    {
        if ($signature === '') {
            return false;
        }

        // Strip common prefixes (e.g., "sha256=" from GitHub/Xero pattern)
        $cleanSignature = $signature;
        if (str_contains($signature, '=') && ! ctype_xdigit($signature)) {
            $parts = explode('=', $signature, 2);
            $cleanSignature = $parts[1] ?? $signature;
        }

        $expected = hash_hmac($algorithm, $payload, $secret);

        return hash_equals($expected, $cleanSignature);
    }
}
