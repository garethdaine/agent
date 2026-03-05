<?php

namespace App\Jobs\Runtime;

use App\DTOs\Messenger\OutboundPayload;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRuntimeTurnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $runtimeSessionId,
        public string $userMessage,
        public string|int|null $chatSessionId = null,
        public string|int|null $connectorAccountId = null,
    ) {
        $this->onQueue('agent');
    }

    public function handle(
        MessengerRuntimeOrchestrator $orchestrator,
        ConnectorManager $connectorManager,
    ): void {
        $session = RuntimeSession::query()
            ->with(['user'])
            ->whereKey($this->runtimeSessionId)
            ->first();

        if ($session === null) {
            Log::warning('ProcessRuntimeTurnJob: Runtime session not found', [
                'runtime_session_id' => $this->runtimeSessionId,
            ]);

            return;
        }

        try {
            $result = $orchestrator->executeTurn($session, $this->userMessage);
        } catch (\Throwable $e) {
            Log::error('ProcessRuntimeTurnJob: Turn execution failed', [
                'runtime_session_id' => $this->runtimeSessionId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sendErrorToChat($e->getMessage(), $connectorManager);

            throw $e;
        }

        if ($this->chatSessionId === null || $this->connectorAccountId === null) {
            return;
        }

        $account = $this->connectorAccountId !== null
            ? ConnectorAccount::find($this->connectorAccountId)
            : null;
        $adapter = $account !== null ? $connectorManager->resolve($account->provider) : null;

        if ($adapter === null) {
            return;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        if ($result['status'] === 'completed' && isset($result['text'])) {
            $payload = new OutboundPayload(
                content: $result['text'],
                channelId: $chatSession->channel_id,
                threadId: $chatSession->thread_id,
            );

            try {
                $adapter->sendMessage($chatSession, $payload);
            } catch (\Throwable $e) {
                Log::error('ProcessRuntimeTurnJob: Failed to send response', [
                    'session_id' => $this->chatSessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($result['status'] === 'pending_approval') {
            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: 'A tool call is waiting for your approval. Use `/approve <id>` or `/deny <id>`.',
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
            } catch (\Throwable $e) {
                Log::warning('ProcessRuntimeTurnJob: Failed to send approval prompt', ['error' => $e->getMessage()]);
            }
        }

        if ($result['status'] === 'failed') {
            $errorText = $result['error'] ?? 'The turn failed. Please try again.';
            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: 'Error: '.$errorText,
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
            } catch (\Throwable $e) {
                Log::error('ProcessRuntimeTurnJob: Failed to send error', ['error' => $e->getMessage()]);
            }
        }
    }

    private function sendErrorToChat(string $errorMessage, ConnectorManager $connectorManager): void
    {
        if ($this->chatSessionId === null || $this->connectorAccountId === null) {
            return;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return;
        }

        $adapter = $connectorManager->resolve($account->provider);
        if ($adapter === null) {
            return;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        $text = strlen($errorMessage) > 500 ? substr($errorMessage, 0, 497).'…' : $errorMessage;
        try {
            $adapter->sendMessage($chatSession, new OutboundPayload(
                content: 'Error: '.$text,
                channelId: $chatSession->channel_id,
                threadId: $chatSession->thread_id,
            ));
        } catch (\Throwable $e) {
            Log::warning('ProcessRuntimeTurnJob: Could not send error to chat', ['error' => $e->getMessage()]);
        }
    }

    public function tags(): array
    {
        return [
            'runtime',
            'turn',
            'session:'.$this->runtimeSessionId,
        ];
    }
}
