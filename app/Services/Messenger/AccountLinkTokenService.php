<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\DTOs\Messenger\AccountLinkPayload;
use App\Models\AccountLinkToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AccountLinkTokenService
{
    private const CACHE_PREFIX = 'messenger:link:';

    private const TTL_SECONDS = 600; // 10 minutes

    /**
     * Generate a new account link token.
     */
    public function generate(string $provider, string $providerUserId, ?string $connectorAccountId = null): string
    {
        $token = Str::random(64);

        $payload = new AccountLinkPayload(
            provider: $provider,
            providerUserId: $providerUserId,
            createdAt: now()->unix(),
            connectorAccountId: $connectorAccountId
        );

        $this->storeToken($token, $payload);

        return $token;
    }

    /**
     * Validate a token without consuming it.
     *
     * Returns the payload if valid, null if invalid or expired.
     */
    public function validate(string $token): ?AccountLinkPayload
    {
        $hash = $this->hashToken($token);

        // Try Redis first
        $cached = $this->getCachedPayload($hash);
        if ($cached !== null) {
            return $cached;
        }

        // Try DB fallback
        return $this->getDbPayload($hash);
    }

    /**
     * Atomically consume a token (one-time use).
     *
     * Returns the payload if successful, null if already consumed or invalid.
     */
    public function consume(string $token): ?AccountLinkPayload
    {
        $hash = $this->hashToken($token);

        // Try Redis first with atomic GETDEL
        $payload = $this->consumeFromRedis($hash);
        if ($payload !== null) {
            return $payload;
        }

        // Try DB fallback with atomic update
        return $this->consumeFromDb($hash);
    }

    /**
     * Revoke a token (invalidate without consuming).
     */
    public function revoke(string $token): void
    {
        $hash = $this->hashToken($token);

        // Remove from Redis
        Cache::store('redis')->forget(self::CACHE_PREFIX.$hash);

        // Mark as consumed in DB (if exists)
        AccountLinkToken::where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    private function storeToken(string $token, AccountLinkPayload $payload): void
    {
        $hash = $this->hashToken($token);
        $data = $payload->toArray();

        try {
            Cache::store('redis')->put(
                self::CACHE_PREFIX.$hash,
                $data,
                self::TTL_SECONDS
            );
        } catch (\Throwable) {
            // DB fallback when Redis is unavailable
            AccountLinkToken::create([
                'token_hash' => $hash,
                'connector_account_id' => $payload->connectorAccountId ?? null,
                'provider_user_id' => $payload->providerUserId,
                'issued_at' => now(),
                'expires_at' => now()->addSeconds(self::TTL_SECONDS),
            ]);
        }
    }

    private function getCachedPayload(string $hash): ?AccountLinkPayload
    {
        try {
            $data = Cache::store('redis')->get(self::CACHE_PREFIX.$hash);
            if ($data === null) {
                return null;
            }

            $payload = AccountLinkPayload::fromArray($data);

            // Check expiration based on createdAt timestamp (respects Carbon time travel in tests)
            $expiresAt = $payload->createdAt + self::TTL_SECONDS;
            if (now()->unix() > $expiresAt) {
                // Token is expired, remove from cache
                Cache::store('redis')->forget(self::CACHE_PREFIX.$hash);

                return null;
            }

            return $payload;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getDbPayload(string $hash): ?AccountLinkPayload
    {
        $record = AccountLinkToken::where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return null;
        }

        return new AccountLinkPayload(
            provider: $record->connectorAccount->provider ?? 'unknown',
            providerUserId: $record->provider_user_id,
            createdAt: $record->issued_at->unix(),
            connectorAccountId: $record->connector_account_id
        );
    }

    private function consumeFromRedis(string $hash): ?AccountLinkPayload
    {
        try {
            // Use pull for atomic get-and-delete
            $data = Cache::store('redis')->pull(self::CACHE_PREFIX.$hash);
            if ($data === null) {
                return null;
            }

            $payload = AccountLinkPayload::fromArray($data);

            // Check expiration based on createdAt timestamp (respects Carbon time travel in tests)
            $expiresAt = $payload->createdAt + self::TTL_SECONDS;
            if (now()->unix() > $expiresAt) {
                // Token is expired (already removed by pull, no need to forget)
                return null;
            }

            return $payload;
        } catch (\Throwable) {
            return null;
        }
    }

    private function consumeFromDb(string $hash): ?AccountLinkPayload
    {
        // Atomic consumption with single-statement update
        $affected = AccountLinkToken::where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($affected === 0) {
            return null; // Token already consumed, expired, or not found
        }

        $record = AccountLinkToken::where('token_hash', $hash)->first();
        if ($record === null) {
            return null;
        }

        return new AccountLinkPayload(
            provider: $record->connectorAccount->provider ?? 'unknown',
            providerUserId: $record->provider_user_id,
            createdAt: $record->issued_at->unix(),
            connectorAccountId: $record->connector_account_id
        );
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
