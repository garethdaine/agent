<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Support\Memory\WorkingMemoryBuffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fire-and-forget job for Working Memory buffer appends.
 *
 * Design principles:
 * - Zero retries: Failures are acceptable, never block agent execution
 * - Short timeout: 5 seconds max, typically completes in <1ms
 * - Silent failures: Logs errors but never throws to caller
 * - Queue isolation: Uses dedicated memory-working queue
 *
 * This job is dispatched from RunEventWriter during agent execution
 * to buffer output events without blocking the 250ms poll loop.
 */
class MemoryWorkingBufferJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Fire-and-forget: 0 retries.
     */
    public int $tries = 0;

    /**
     * Short timeout for fast operations.
     */
    public int $timeout = 5;

    /**
     * Create a new job instance.
     *
     * @param  int  $runId  The agent job run ID
     * @param  string  $eventType  The event type (stdout, stderr, lifecycle)
     * @param  string  $rawPayload  The raw event payload
     */
    public function __construct(
        public int $runId,
        public string $eventType,
        public string $rawPayload
    ) {
        $this->onQueue('memory-working');
    }

    /**
     * Execute the job.
     */
    public function handle(WorkingMemoryBuffer $buffer): void
    {
        try {
            $role = $this->mapEventTypeToRole($this->eventType);
            $metadata = $this->extractMetadata();

            $buffer->append($this->runId, $role, $this->rawPayload, $metadata);
        } catch (Throwable $e) {
            // Silent failure - log but don't throw
            Log::debug('MemoryWorkingBufferJob: Failed to append', [
                'run_id' => $this->runId,
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     * This is called when the job fails permanently (after all retries exhausted).
     * Since we have 0 retries, this is called on first failure.
     */
    public function failed(Throwable $exception): void
    {
        // Silent failure - just log, never throw
        Log::debug('MemoryWorkingBufferJob: Job failed permanently', [
            'run_id' => $this->runId,
            'event_type' => $this->eventType,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'memory',
            'working-buffer',
            'run:'.$this->runId,
        ];
    }

    /**
     * Map event type to role for storage.
     */
    private function mapEventTypeToRole(string $eventType): string
    {
        return match ($eventType) {
            'stdout' => 'assistant',
            'stderr' => 'stderr',
            'lifecycle' => 'lifecycle',
            default => $eventType,
        };
    }

    /**
     * Extract metadata from the payload if it's a lifecycle event.
     *
     * @return array<string, mixed>
     */
    private function extractMetadata(): array
    {
        if ($this->eventType !== 'lifecycle') {
            return [];
        }

        $decoded = json_decode($this->rawPayload, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }
}
