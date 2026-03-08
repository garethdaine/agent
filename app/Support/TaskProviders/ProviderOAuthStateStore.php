<?php

declare(strict_types=1);

namespace App\Support\TaskProviders;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProviderOAuthStateStore
{
    public function put(int $userId, int $sessionId, string $driver, ?string $returnTo = null): string
    {
        $state = Str::random(64);
        $normalizedReturnTo = strtolower(trim((string) $returnTo));
        if (! in_array($normalizedReturnTo, ['wizard', 'settings'], true)) {
            $normalizedReturnTo = 'wizard';
        }

        Cache::put($this->cacheKey($state), [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'driver' => strtolower(trim($driver)),
            'return_to' => $normalizedReturnTo,
            'created_at' => now('UTC')->toIso8601String(),
        ], now('UTC')->addMinutes(15));

        return $state;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(string $state): ?array
    {
        $payload = Cache::pull($this->cacheKey($state));

        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(string $state): string
    {
        return 'task-provider-oauth-state:'.$state;
    }
}
