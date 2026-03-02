<?php

namespace App\Jobs\Messenger;

use App\DTOs\Messenger\OutboundPayload;
use App\Messenger\Reliability\DeadLetterManager;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Notifications\OutboundMessageFailedNotification;
use App\Support\Messenger\ConnectorManager;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendOutboundMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 120, 480]; // 30s, 2min, 8min

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
        // Retry for up to 15 minutes
        return now()->addMinutes(15);
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
    public function failed(Throwable $exception): void
    {
        Log::error('SendOutboundMessage: Job failed permanently', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $session = ChatSession::with(['connectorAccount', 'user'])->find($this->sessionId);
        } catch (Throwable $e) {
            // Invalid session ID format (e.g., non-UUID string)
            Log::warning('SendOutboundMessage: Could not look up session for dead-letter', [
                'session_id' => $this->sessionId,
                'lookup_error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $session || ! $session->connectorAccount) {
            return;
        }

        // Store in dead-letter queue
        app(DeadLetterManager::class)->moveToDeadLetter(
            connector: $session->connectorAccount,
            payload: [
                'session_id' => $this->sessionId,
                'content' => $this->content,
                'thread_id' => $this->threadId,
                'reply_to_message_id' => $this->replyToMessageId,
                'attachment_ids' => $this->attachmentIds,
            ],
            error: $exception->getMessage(),
            attempts: $this->attempts()
        );

        // Notify session owner
        $this->notifyOwner($session, $exception);
    }

    private function notifyOwner(ChatSession $session, Throwable $exception): void
    {
        $owner = $session->user;

        if (! $owner) {
            return;
        }

        $channel = $owner->getNotificationChannel();
        $notification = new OutboundMessageFailedNotification(
            sessionId: $this->sessionId,
            reason: $exception->getMessage()
        );

        $owner->notify($notification);
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
