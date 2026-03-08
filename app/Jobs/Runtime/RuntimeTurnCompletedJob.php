<?php

declare(strict_types=1);

namespace App\Jobs\Runtime;

use App\DTOs\Messenger\OutboundPayload;
use App\Jobs\Messenger\CompactionJob;
use App\Models\ChatMessage;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Services\Messenger\CompactionService;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RuntimeTurnCompletedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $runtimeSessionId,
        public array $result,
        public string|int|null $chatSessionId = null,
        public string|int|null $connectorAccountId = null,
        public ?string $placeholderMessageId = null,
    ) {
        $this->onQueue('messenger-default');
    }

    public function handle(
        ConnectorManager $connectorManager,
        CompactionService $compactionService,
    ): void {
        if ($this->chatSessionId === null || $this->connectorAccountId === null) {
            return;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return;
        }

        $adapter = $connectorManager->resolve($account->provider);
        /** @var \App\Models\ChatSession|null $chatSession */
        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        $status = $this->result['status'] ?? 'unknown';

        if ($status === 'completed' && isset($this->result['text'])) {
            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: $this->result['text'],
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
                $this->persistOutboundMessage($chatSession->id, $account->id, $this->result['text']);

                if ($this->placeholderMessageId !== null && $adapter->supportsMessageEditing()) {
                    $adapter->editMessage($chatSession, $this->placeholderMessageId, '✅ Done');
                }
            } catch (\Throwable $e) {
                Log::error('RuntimeTurnCompletedJob: Failed to send response', [
                    'session_id' => $this->chatSessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($status === 'failed') {
            $errorText = $this->result['error'] ?? 'The turn failed. Please try again.';
            $partialText = isset($this->result['text']) && $this->result['text'] !== '' ? $this->result['text'] : null;

            $content = $partialText !== null
                ? "Error: {$errorText}\n\n**Partial progress before failure:**\n{$partialText}"
                : "Error: {$errorText}";

            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: $content,
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
            } catch (\Throwable $e) {
                Log::error('RuntimeTurnCompletedJob: Failed to send error', ['error' => $e->getMessage()]);
            }
        }

        $this->dispatchAnnounceBackIfSubAgent();

        if ($compactionService->isCompactionNeeded($chatSession)) {
            CompactionJob::dispatch($chatSession->id, null);
        }
    }

    private function dispatchAnnounceBackIfSubAgent(): void
    {
        $session = RuntimeSession::find($this->runtimeSessionId);
        if ($session === null || $session->parent_session_id === null) {
            return;
        }

        SubAgentCompletionJob::dispatch(
            childSessionId: $session->id,
            parentSessionId: $session->parent_session_id,
            status: $this->result['status'] ?? 'unknown',
            text: $this->result['text'] ?? null,
            error: $this->result['error'] ?? null,
            label: $session->title,
        );
    }

    private function persistOutboundMessage(string|int $chatSessionId, string|int $connectorAccountId, string $content): void
    {
        try {
            ChatMessage::create([
                'chat_session_id' => $chatSessionId,
                'connector_account_id' => $connectorAccountId,
                'direction' => ChatMessage::DIRECTION_OUTBOUND,
                'content' => $content,
                'idempotency_key' => hash('sha256', Str::uuid()->toString()),
                'provider_timestamp' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RuntimeTurnCompletedJob: Failed to persist outbound ChatMessage', [
                'chat_session_id' => $chatSessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'runtime',
            'turn-completed',
            'session:'.$this->runtimeSessionId,
        ];
    }
}
