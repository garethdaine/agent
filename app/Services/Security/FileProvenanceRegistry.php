<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Security\ContentTrustLevel;
use App\Models\Runtime\RuntimeSession;
use Illuminate\Support\Facades\Redis;

class FileProvenanceRegistry
{
    private const int HASH_TTL_SECONDS = 86400; // 24 hours

    public function record(
        string $sessionId,
        string $filePath,
        ContentTrustLevel $trustLevel,
        string $origin,
        ?string $toolCallId = null,
    ): void {
        $redisKey = $this->redisKey($sessionId);
        $entry = [
            'trustLevel' => $trustLevel->value,
            'origin' => $origin,
            'createdAt' => now()->toIso8601String(),
            'toolCallId' => $toolCallId,
        ];

        $isNew = ! Redis::connection('cache')->exists($redisKey);

        Redis::connection('cache')->hset($redisKey, $filePath, json_encode($entry));

        if ($isNew) {
            Redis::connection('cache')->expire($redisKey, self::HASH_TTL_SECONDS);
        }

        $this->updateDatabaseProvenance($sessionId, $filePath, $entry);
    }

    public function lookup(string $sessionId, string $filePath): ?ContentTrustLevel
    {
        // Try Redis first
        $redisValue = Redis::connection('cache')->hget($this->redisKey($sessionId), $filePath);

        if ($redisValue !== null && $redisValue !== false) { // @phpstan-ignore notIdentical.alwaysTrue
            $decoded = json_decode($redisValue, true);

            return ContentTrustLevel::tryFrom($decoded['trustLevel'] ?? '');
        }

        // Fall back to database JSONB
        $session = RuntimeSession::find($sessionId);

        if ($session === null || $session->file_provenance === null) {
            return null;
        }

        foreach ($session->file_provenance as $entry) { // @phpstan-ignore foreach.nonIterable
            if (($entry['filePath'] ?? '') === $filePath) {
                return ContentTrustLevel::tryFrom($entry['trustLevel'] ?? '');
            }
        }

        return null;
    }

    public function getAll(string $sessionId): array
    {
        $redisData = Redis::connection('cache')->hgetall($this->redisKey($sessionId));

        $results = [];
        foreach ($redisData as $filePath => $jsonValue) {
            $results[$filePath] = json_decode($jsonValue, true);
        }

        return $results;
    }

    public function purgeSession(string $sessionId): void
    {
        Redis::connection('cache')->del($this->redisKey($sessionId));

        $session = RuntimeSession::find($sessionId);
        if ($session !== null) {
            $session->update(['file_provenance' => null]);
        }
    }

    private function redisKey(string $sessionId): string
    {
        return "security:provenance:{$sessionId}";
    }

    private function updateDatabaseProvenance(string $sessionId, string $filePath, array $entry): void
    {
        $session = RuntimeSession::find($sessionId);

        if ($session === null) {
            return;
        }

        $provenance = $session->file_provenance ?? [];
        $provenance[] = array_merge(['filePath' => $filePath], $entry);

        $session->update(['file_provenance' => $provenance]);
    }
}
