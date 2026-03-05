<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\ForgettingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Memory Prune Job.
 *
 * Asynchronous job for scheduled pruning execution.
 * Runs tiered memory pruning (embeddings, graph entities).
 *
 * Queue: memory-formation (shared with other memory jobs)
 * Tries: 3 with backoff
 * Timeout: 600 seconds (10 minutes)
 */
class MemoryPruneJob implements ShouldQueue
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
     * @param  bool  $dryRun  If true, only report what would be pruned
     */
    public function __construct(
        public readonly ?int $userId = null,
        public readonly bool $dryRun = false,
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
    public function handle(ForgettingService $service): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            Log::debug('MemoryPruneJob: Skipped (memory disabled)');

            return;
        }

        Log::info('MemoryPruneJob: Starting prune', [
            'user_id' => $this->userId,
            'dry_run' => $this->dryRun,
        ]);

        try {
            $result = $service->prune($this->userId, $this->dryRun);

            Log::info('MemoryPruneJob: Prune completed', [
                'user_id' => $this->userId,
                'dry_run' => $this->dryRun,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('MemoryPruneJob: Prune failed', [
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
        return app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED);
    }

    /**
     * Get the tags for Horizon.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        $tags = ['memory', 'prune'];

        if ($this->userId !== null) {
            $tags[] = 'user:'.$this->userId;
        }

        $tags[] = $this->dryRun ? 'dry-run' : 'force';

        return $tags;
    }
}
