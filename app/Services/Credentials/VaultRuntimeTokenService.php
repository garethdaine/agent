<?php

declare(strict_types=1);

namespace App\Services\Credentials;

use App\Models\User;

/**
 * Generates and validates short-lived HMAC tokens for runtime vault access.
 *
 * When the CLI runner spawns a subprocess, it can't use Sanctum/session auth.
 * Instead, we generate a signed token encoding the user ID and expiry, and
 * inject it as an environment variable. The vault runtime endpoint validates
 * this token to authenticate requests from the CLI agent.
 */
class VaultRuntimeTokenService
{
    private const ALGORITHM = 'sha256';

    /**
     * Generate a signed runtime vault token.
     *
     * @param  int  $ttlSeconds  Token validity in seconds (default: matches CLI timeout)
     */
    public function generate(User $user, string $sessionId, int $ttlSeconds = 1800): string
    {
        $expiresAt = time() + $ttlSeconds;
        $payload = json_encode([
            'uid' => $user->id,
            'sid' => $sessionId,
            'exp' => $expiresAt,
        ]);

        $signature = hash_hmac(self::ALGORITHM, $payload, $this->getSigningKey());

        return base64_encode($payload).'.'.$signature;
    }

    /**
     * Validate a runtime vault token and return the user ID if valid.
     *
     * @return array{user_id: int, session_id: string}|null
     */
    public function validate(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;

        $payload = base64_decode($encodedPayload, true);
        if ($payload === false) {
            return null;
        }

        $expectedSignature = hash_hmac(self::ALGORITHM, $payload, $this->getSigningKey());
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return null;
        }

        // Check expiry
        $expiresAt = $data['exp'] ?? 0;
        if (time() > $expiresAt) {
            return null;
        }

        $userId = $data['uid'] ?? null;
        $sessionId = $data['sid'] ?? null;
        if ($userId === null || $sessionId === null) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'session_id' => (string) $sessionId,
        ];
    }

    private function getSigningKey(): string
    {
        return config('app.key', '');
    }
}
