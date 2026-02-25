<?php

namespace App\Jobs\Messenger;

use App\DTOs\Messenger\OutboundPayload;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Support\Messenger\ConnectorManager;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendOutboundMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0; // Unlimited retries within duration

    public int $maxExceptions = 3;

    /**
     * @param  array<string>  $attachmentIds
     */
    public function __construct(
        public string $sessionId,
        public string $content,
        public ?string $threadId = null,
        public ?string $replyToMessageId = null,
        public array $attachmentIds = []
    ) {
        $this->onQueue('messenger-default');
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        // Retry for up to 1 hour by default
        return now()->addHour();
    }

    public function handle(ConnectorManager $connectorManager): void
    {
        $session = ChatSession::with('connectorAccount')->find($this->sessionId);

        if (! $session) {
            Log::warning('SendOutboundMessage: Session not found', [
                'session_id' => $this->sessionId,
            ]);

            return;
        }

        $account = $session->connectorAccount;

        if (! $account || ! $account->isConnected()) {
            Log::warning('SendOutboundMessage: Account not available or disconnected', [
                'session_id' => $this->sessionId,
                'account_id' => $account?->id,
            ]);

            $this->fail(new \RuntimeException('Connector account not available'));

            return;
        }

        $adapter = $connectorManager->resolve($account->provider);

        // Build outbound payload
        $payload = new OutboundPayload(
            content: $this->content,
            channelId: $session->channel_id,
            threadId: $this->threadId ?? $session->thread_id,
            replyToMessageId: $this->replyToMessageId,
            attachmentIds: $this->attachmentIds,
        );

        // Send the message
        $response = $adapter->sendMessage($session, $payload);

        if (! $response->success) {
            Log::error('SendOutboundMessage: Failed to send message', [
                'session_id' => $this->sessionId,
                'account_id' => $account->id,
                'error' => $response->error,
            ]);

            // Check if this is a rate limit error
            if ($this->isRateLimitError($response)) {
                $retryAfter = $this->extractRetryAfter($response);
                $this->release($retryAfter);

                return;
            }

            throw new \RuntimeException($response->error ?? 'Failed to send message');
        }

        // Create outbound message record
        $idempotencyKey = hash('sha256', implode(':', [
            $account->provider,
            $account->id,
            $response->providerMessageId,
        ]));

        ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'direction' => ChatMessage::DIRECTION_OUTBOUND,
            'content' => $this->content,
            'attachment_ids' => empty($this->attachmentIds) ? null : $this->attachmentIds,
            'idempotency_key' => $idempotencyKey,
            'provider_message_id' => $response->providerMessageId,
            'provider_timestamp' => now(),
        ]);

        Log::info('SendOutboundMessage: Message sent successfully', [
            'session_id' => $this->sessionId,
            'account_id' => $account->id,
            'provider_message_id' => $response->providerMessageId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendOutboundMessage: Job failed permanently', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Move to dead letter queue and notify admin
        // MoveToDeadLetter::dispatch('outbound_message', [...]);
    }

    /**
     * Check if the error is a rate limit response.
     */
    private function isRateLimitError($response): bool
    {
        $error = $response->error ?? '';

        return str_contains(strtolower($error), 'rate')
            || str_contains(strtolower($error), '429')
            || str_contains(strtolower($error), 'too many');
    }

    /**
     * Extract retry-after seconds from response.
     */
    private function extractRetryAfter($response): int
    {
        // Check response metadata for Retry-After header
        $data = $response->data ?? [];

        if (isset($data['retry_after'])) {
            return (int) $data['retry_after'];
        }

        // Default backoff with jitter
        $base = 5;
        $jitter = random_int(0, 5);

        return $base + $jitter;
    }

    public function tags(): array
    {
        return [
            'messenger',
            'outbound',
            'session:'.$this->sessionId,
        ];
    }
}
