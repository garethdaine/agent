<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Models\Runtime\RuntimeSession;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\MemoryFormationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Memory formation for a stopped runtime (messenger) session.
 *
 * Dispatched from RuntimeSessionManager::stopSession when memory is enabled.
 * Persists session turns to memory_conversation_logs and runs extraction/
 * embedding/graph when API mode is on.
 *
 * Queue: memory-formation
 */
class RuntimeMemoryFormationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $runtimeSessionId
    ) {
        $this->onQueue('memory-formation');
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function shouldQueue(): bool
    {
        return app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED);
    }

    public function handle(MemoryFormationPipeline $pipeline): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            Log::debug('RuntimeMemoryFormationJob: Memory disabled, skipping', [
                'runtime_session_id' => $this->runtimeSessionId,
            ]);

            return;
        }

        $session = RuntimeSession::find($this->runtimeSessionId);
        if ($session === null) {
            Log::warning('RuntimeMemoryFormationJob: Session not found', [
                'runtime_session_id' => $this->runtimeSessionId,
            ]);

            return;
        }

        $result = $pipeline->processRuntimeSession($session);

        if ($result->success) {
            Log::info('RuntimeMemoryFormationJob: Formation completed', [
                'runtime_session_id' => $this->runtimeSessionId,
                'conversation_logs' => $result->conversationLogsCreated,
                'embeddings' => $result->embeddingsCreated,
            ]);

            return;
        }

        Log::warning('RuntimeMemoryFormationJob: Formation failed', [
            'runtime_session_id' => $this->runtimeSessionId,
            'failure_type' => $result->failureType,
            'error' => $result->errorMessage,
        ]);
    }

    public function tags(): array
    {
        return [
            'memory',
            'runtime-formation',
            'session:'.$this->runtimeSessionId,
        ];
    }
}
