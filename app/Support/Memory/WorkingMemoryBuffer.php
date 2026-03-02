<?php

declare(strict_types=1);

namespace App\Support\Memory;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed Working Memory Buffer for short-term conversational state.
 *
 * Uses Redis sorted sets for O(log N) insertion and efficient range queries.
 * Score is microsecond timestamp for chronological ordering.
 * Implements oldest-first eviction when exceeding max_messages limit.
 *
 * Key pattern: memory:run:{run_id}:working
 * Connection: Redis DB 2 via 'memory' connection
 *
 * Fire-and-forget design:
 * - Operations never throw to caller
 * - Failures are logged but don't block agent execution
 * - TTL ensures automatic cleanup
 */
class WorkingMemoryBuffer
{
    private const REDIS_CONNECTION = 'memory';

    /**
     * Append an entry to the working memory buffer.
     *
     * @param  int  $runId  The run ID to scope the buffer to
     * @param  string  $role  The role (user, assistant, tool, tool_call, stderr, lifecycle)
     * @param  string  $content  The content of the message
     * @param  array<string, mixed>  $metadata  Optional metadata
     */
    public function append(int $runId, string $role, string $content, array $metadata = []): void
    {
        try {
            $key = $this->getKey($runId);
            $score = microtime(true);

            $entry = [
                'role' => $role,
                'content' => $content,
                'metadata' => $metadata,
                'timestamp' => $score,
            ];

            $payload = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($payload === false) {
                return;
            }

            $connection = Redis::connection(self::REDIS_CONNECTION);

            // Add to sorted set with timestamp as score
            $connection->zadd($key, $score, $payload);

            // Enforce max messages (oldest-first eviction via ZREMRANGEBYRANK)
            $maxMessages = (int) config('memory.working_memory.max_messages', 15);
            $count = $connection->zcard($key);

            if ($count > $maxMessages) {
                // Remove oldest entries (those with lowest scores)
                $toRemove = $count - $maxMessages;
                $connection->zremrangebyrank($key, 0, $toRemove - 1);
            }
        } catch (\Throwable) {
            // Silent failure - never block agent execution
        }
    }

    /**
     * Get the most recent entries from the working memory buffer.
     *
     * @param  int  $runId  The run ID to scope the buffer to
     * @param  int  $limit  Maximum number of entries to return (default from config)
     * @return array<int, array{role: string, content: string, metadata: array<string, mixed>, timestamp: float}>
     */
    public function getRecent(int $runId, int $limit = 0): array
    {
        try {
            $key = $this->getKey($runId);
            $connection = Redis::connection(self::REDIS_CONNECTION);

            if ($limit <= 0) {
                $limit = (int) config('memory.working_memory.max_messages', 15);
            }

            // Get entries with ZRANGE in ascending order (oldest to newest)
            // Using negative offset to get last N entries
            $count = $connection->zcard($key);

            if ($count === 0) {
                return [];
            }

            $start = max(0, $count - $limit);
            $entries = $connection->zrange($key, $start, -1);

            if (! is_array($entries) || empty($entries)) {
                return [];
            }

            $result = [];
            foreach ($entries as $payload) {
                $decoded = json_decode((string) $payload, true);
                if (is_array($decoded)) {
                    $result[] = $decoded;
                }
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Clear all entries from the working memory buffer for a run.
     *
     * @param  int  $runId  The run ID to clear
     */
    public function clear(int $runId): void
    {
        try {
            $key = $this->getKey($runId);
            Redis::connection(self::REDIS_CONNECTION)->del($key);
        } catch (\Throwable) {
            // Silent failure
        }
    }

    /**
     * Set TTL on the working memory buffer.
     *
     * @param  int  $runId  The run ID to set TTL for
     * @param  int  $seconds  TTL in seconds (default from config: 7200 = 2 hours)
     */
    public function setTTL(int $runId, int $seconds = 0): void
    {
        try {
            $key = $this->getKey($runId);

            if ($seconds <= 0) {
                $seconds = (int) config('memory.working_memory.ttl_seconds', 7200);
            }

            Redis::connection(self::REDIS_CONNECTION)->expire($key, $seconds);
        } catch (\Throwable) {
            // Silent failure
        }
    }

    /**
     * Get the Redis key for a run's working memory buffer.
     *
     * @param  int  $runId  The run ID
     */
    private function getKey(int $runId): string
    {
        $prefix = config('memory.working_memory.key_prefix', 'memory:run:');

        return $prefix.$runId.':working';
    }
}
