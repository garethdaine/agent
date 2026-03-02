<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Support\Memory\ConsolidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Memory Consolidation Job.
 *
 * Asynchronous job for event-driven consolidation triggers.
 * Runs backfill retries and vector deduplication.
 *
 * Queue: memory-formation (shared with MemoryFormationJob)
 * Tries: 3 with backoff
 * Timeout: 600 seconds (10 minutes)
 */
class MemoryConsolidationJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * Number of retries.
     */
    public int $tries = 3;

    /**
     * Job timeout in seconds.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $userId  Scope to specific user, null for all users
     * @param  string|null  $type  Run specific type: 'backfill', 'dedup', or null for all
     */
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $type = null,
    ) {
        $this->queue = 'memory-formation';
    }

    /**
     * Calculate backoff intervals in seconds.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /**
     * Execute the job.
     */
    public function handle(ConsolidationService $service): void
    {
        if (! config('memory.enabled', false)) {
            Log::debug('MemoryConsolidationJob: Skipped (memory disabled)');

            return;
        }

        Log::info('MemoryConsolidationJob: Starting consolidation', [
            'user_id' => $this->userId,
            'type' => $this->type,
        ]);

        try {
            $result = $service->run($this->userId, $this->type);

            Log::info('MemoryConsolidationJob: Consolidation completed', [
                'user_id' => $this->userId,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('MemoryConsolidationJob: Consolidation failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Determine if the job should be queued.
     */
    public function shouldQueue(): bool
    {
        return config('memory.enabled', false);
    }

    /**
     * Get the tags for Horizon.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        $tags = ['memory', 'consolidation'];

        if ($this->userId !== null) {
            $tags[] = 'user:'.$this->userId;
        }

        if ($this->type !== null) {
            $tags[] = 'type:'.$this->type;
        }

        return $tags;
    }
}
