<?php

namespace App\Jobs\Runtime;

use App\Models\ConnectorAccount;
use App\Services\Runtime\SessionProcessManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResumeRuntimeTurnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public string $runtimeSessionId,
        public int $remainingTimeout,
        public string|int|null $chatSessionId = null,
        public string|int|null $connectorAccountId = null,
        public ?string $placeholderMessageId = null,
    ) {
        $this->onQueue(config('runtime.queue', 'agent'));
        $this->timeout = $remainingTimeout + 60;
    }

    public function handle(
        SessionProcessManager $processManager,
        ConnectorManager $connectorManager,
    ): void {
        Log::info('ResumeRuntimeTurnJob: Resuming turn', [
            'runtime_session_id' => $this->runtimeSessionId,
            'remaining_timeout' => $this->remainingTimeout,
        ]);

        $progressCallback = $this->buildProgressCallback($connectorManager);

        $result = $processManager->resumeReadTurnResponse(
            $this->runtimeSessionId,
            $this->remainingTimeout,
            $progressCallback,
        );

        $status = $result['status'] ?? 'failed';

        Log::info('ResumeRuntimeTurnJob: Resume result', [
            'runtime_session_id' => $this->runtimeSessionId,
            'status' => $status,
        ]);

        match ($status) {
            'yielded' => $this->handleYielded($result, $connectorManager),
            default => $this->handleTerminal($result),
        };
    }

    private function handleYielded(array $result, ConnectorManager $connectorManager): void
    {
        $elapsed = $result['elapsed_seconds'] ?? 0;
        $this->updatePlaceholder($connectorManager, $elapsed);

        $consumed = (int) config('runtime.cli.yield_after_seconds', 120);
        $remaining = max(60, $this->remainingTimeout - $consumed);

        self::dispatch(
            runtimeSessionId: $this->runtimeSessionId,
            remainingTimeout: $remaining,
            chatSessionId: $this->chatSessionId,
            connectorAccountId: $this->connectorAccountId,
            placeholderMessageId: $this->placeholderMessageId,
        )->delay(now()->addSeconds(5));
    }

    private function handleTerminal(array $result): void
    {
        RuntimeTurnCompletedJob::dispatch(
            runtimeSessionId: $this->runtimeSessionId,
            result: $result,
            chatSessionId: $this->chatSessionId,
            connectorAccountId: $this->connectorAccountId,
            placeholderMessageId: $this->placeholderMessageId,
        );
    }

    private function buildProgressCallback(ConnectorManager $connectorManager): ?\Closure
    {
        if ($this->placeholderMessageId === null || $this->connectorAccountId === null) {
            return null;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return null;
        }

        $adapter = $connectorManager->resolve($account->provider);
        if (! $adapter->supportsMessageEditing()) {
            return null;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return null;
        }

        $placeholderMessageId = $this->placeholderMessageId;

        return function (array $state) use ($adapter, $chatSession, $placeholderMessageId) {
            $elapsed = $state['elapsed_seconds'] ?? 0;
            $minutes = intdiv($elapsed, 60);
            $seconds = $elapsed % 60;
            $timeStr = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";

            try {
                $adapter->editMessage($chatSession, $placeholderMessageId, "⏳ Still working… ({$timeStr} elapsed)");
            } catch (\Throwable $e) {
                Log::debug('ResumeRuntimeTurnJob: progress update failed', ['error' => $e->getMessage()]);
            }
        };
    }

    private function updatePlaceholder(ConnectorManager $connectorManager, int $elapsed): void
    {
        if ($this->placeholderMessageId === null || $this->connectorAccountId === null) {
            return;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return;
        }

        $adapter = $connectorManager->resolve($account->provider);
        if (! $adapter->supportsMessageEditing()) {
            return;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        $minutes = intdiv($elapsed, 60);
        $seconds = $elapsed % 60;
        $timeStr = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";

        try {
            $adapter->editMessage($chatSession, $this->placeholderMessageId, "⏳ Still working… ({$timeStr} elapsed, yielded for other tasks)");
        } catch (\Throwable $e) {
            Log::debug('ResumeRuntimeTurnJob: placeholder update failed', ['error' => $e->getMessage()]);
        }
    }

    public function tags(): array
    {
        return [
            'runtime',
            'turn-resume',
            'session:'.$this->runtimeSessionId,
        ];
    }
}
