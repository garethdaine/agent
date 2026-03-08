<?php

declare(strict_types=1);

namespace App\Jobs\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\Exceptions\AttachmentRejectionException;
use App\Models\ChatMessage;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Services\Messenger\AttachmentHandler;
use App\Services\Messenger\ChatSessionManager;
use App\Support\Messenger\ConnectorManager;
use App\Support\Messenger\IdempotencyKeyGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessInboundMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        public string $connectorAccountId,
        public string $provider,
        public array $payload
    ) {
        $this->onQueue('messenger-default');
    }

    public function handle(
        ConnectorManager $connectorManager,
        ChatSessionManager $sessionManager,
        ?AttachmentHandler $attachmentHandler = null
    ): void {
        $attachmentHandler ??= app(AttachmentHandler::class);
        $account = ConnectorAccount::find($this->connectorAccountId);

        if (! $account) {
            Log::channel('messenger')->warning('ProcessInboundMessage: Connector account not found', [
                'account_id' => $this->connectorAccountId,
            ]);

            return;
        }

        $adapter = $connectorManager->resolve($this->provider);

        // Parse the inbound message using the adapter
        $normalizedMessage = $this->parseMessage($adapter, $account);

        if (! $normalizedMessage) {
            Log::channel('messenger')->debug('ProcessInboundMessage: Could not parse message, skipping', [
                'account_id' => $account->id,
                'provider' => $this->provider,
            ]);

            return;
        }

        // Check if user has an identity link
        $identityLink = MessengerIdentityLink::query()
            ->forConnector($account->id)
            ->forProviderUser($normalizedMessage->providerUserId)
            ->valid()
            ->first();

        if (! $identityLink) {
            $this->handleUnlinkedUser($account, $normalizedMessage);

            return;
        }

        // Check if identity link is expired
        if ($identityLink->isExpired()) {
            $this->handleExpiredLink($account, $normalizedMessage, $identityLink);

            return;
        }

        $user = $identityLink->user;

        // Find or create session
        $session = $sessionManager->findOrCreate(
            account: $account,
            channelId: $normalizedMessage->channelId,
            threadId: $normalizedMessage->threadId,
            user: $user
        );

        // Generate idempotency key
        $idempotencyKey = app(IdempotencyKeyGenerator::class)->generate(
            $normalizedMessage,
            $account
        );

        // Fast-path dedupe check. This avoids transaction-abort side effects
        // in PostgreSQL test transactions when relying solely on unique violations.
        if (ChatMessage::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            Log::channel('messenger')->debug('ProcessInboundMessage: Duplicate message, skipping', [
                'idempotency_key' => $idempotencyKey,
                'account_id' => $account->id,
            ]);

            return;
        }

        // Create chat message with idempotency check
        try {
            /** @var ChatMessage $message */
            $message = ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => ChatMessage::DIRECTION_INBOUND,
                'content' => $normalizedMessage->content,
                'idempotency_key' => $idempotencyKey,
                'provider_event_id' => $normalizedMessage->providerEventId,
                'provider_message_id' => $normalizedMessage->providerMessageId,
                'provider_timestamp' => $normalizedMessage->providerTimestamp,
            ]);

            Log::channel('messenger')->info('ProcessInboundMessage: Message created', [
                'message_id' => $message->id,
                'session_id' => $session->id,
                'account_id' => $account->id,
                'user_id' => $user->id,
            ]);

            // Process attachments after message creation
            $this->processAttachments($normalizedMessage, $message, $attachmentHandler);

            // Dispatch intent processing job
            ProcessChatIntent::dispatch(
                messageId: $message->id,
                sessionId: $session->id,
                userId: $user->id
            );

        } catch (QueryException $e) {
            // Check if it's a duplicate key violation (idempotency)
            if ($this->isDuplicateKeyException($e)) {
                Log::channel('messenger')->debug('ProcessInboundMessage: Duplicate message, skipping', [
                    'idempotency_key' => $idempotencyKey,
                    'account_id' => $account->id,
                ]);

                return;
            }

            throw $e;
        }
    }

    private function parseMessage(
        $adapter,
        ConnectorAccount $account
    ): ?NormalizedMessage {
        try {
            // Create a mock request from the payload for the adapter
            $request = new \Illuminate\Http\Request($this->payload);
            $request->attributes->set('connector_account', $account);

            return $adapter->parseInboundMessage($request);
        } catch (\Throwable $e) {
            Log::channel('messenger')->warning('ProcessInboundMessage: Failed to parse message', [
                'account_id' => $account->id,
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function handleUnlinkedUser(
        ConnectorAccount $account,
        NormalizedMessage $message
    ): void {
        $rateLimitKey = sprintf(
            'messenger:link_prompt:%s:%s',
            $account->id,
            $message->providerUserId
        );

        // Rate limit: max 1 prompt per user per hour
        if (Cache::has($rateLimitKey)) {
            Log::channel('messenger')->debug('ProcessInboundMessage: Link prompt rate limited', [
                'account_id' => $account->id,
                'provider_user_id' => $message->providerUserId,
            ]);

            return;
        }

        // Set rate limit flag (1 hour)
        Cache::put($rateLimitKey, true, 3600);

        // Dispatch account link prompt job
        SendAccountLinkPrompt::dispatch(
            connectorAccountId: $account->id,
            providerUserId: $message->providerUserId,
            channelId: $message->channelId,
            threadId: $message->threadId
        )->onQueue('messenger-default');

        Log::channel('messenger')->info('ProcessInboundMessage: Dispatching account link prompt', [
            'account_id' => $account->id,
            'provider_user_id' => $message->providerUserId,
            'channel_id' => $message->channelId,
        ]);
    }

    private function handleExpiredLink(
        ConnectorAccount $account,
        NormalizedMessage $message,
        MessengerIdentityLink $link
    ): void {
        // Rate limit: max 1 re-auth prompt per user per hour
        $rateLimitKey = sprintf(
            'messenger:reauth_prompt:%s:%s',
            $account->id,
            $message->providerUserId
        );

        if (Cache::has($rateLimitKey)) {
            Log::channel('messenger')->debug('ProcessInboundMessage: Re-auth prompt rate limited', [
                'account_id' => $account->id,
                'provider_user_id' => $message->providerUserId,
            ]);

            return;
        }

        Cache::put($rateLimitKey, true, 3600);

        // Dispatch re-auth prompt (same as account link prompt)
        SendAccountLinkPrompt::dispatch(
            connectorAccountId: $account->id,
            providerUserId: $message->providerUserId,
            channelId: $message->channelId,
            threadId: $message->threadId,
            isReauth: true
        )->onQueue('messenger-default');

        Log::channel('messenger')->info('ProcessInboundMessage: Dispatching re-auth prompt for expired link', [
            'account_id' => $account->id,
            'provider_user_id' => $message->providerUserId,
            'link_id' => $link->id,
        ]);
    }

    /**
     * Process attachments by downloading and storing them locally.
     *
     * Each attachment is processed independently - a failure in one does not
     * block others from being processed.
     */
    private function processAttachments(
        NormalizedMessage $normalizedMessage,
        ChatMessage $message,
        AttachmentHandler $attachmentHandler
    ): void {
        if (empty($normalizedMessage->attachments)) {
            return;
        }

        foreach ($normalizedMessage->attachments as $attachment) {
            // Skip attachments without a download URL
            if (empty($attachment->downloadUrl)) {
                Log::channel('messenger')->warning('ProcessInboundMessage: Attachment missing download URL', [
                    'message_id' => $message->id,
                    'provider_file_id' => $attachment->providerFileId,
                ]);

                continue;
            }

            try {
                // Convert NormalizedAttachment to the format expected by AttachmentHandler
                $attachmentData = [
                    'url' => $attachment->downloadUrl,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mimeType,
                    'size' => $attachment->sizeBytes,
                    'provider_file_id' => $attachment->providerFileId,
                ];

                $attachmentHandler->processInbound([$attachmentData], $message);

                Log::channel('messenger')->info('ProcessInboundMessage: Attachment processed', [
                    'message_id' => $message->id,
                    'provider_file_id' => $attachment->providerFileId,
                    'filename' => $attachment->filename,
                ]);
            } catch (AttachmentRejectionException $e) {
                // Attachment was rejected (size, MIME type, etc.)
                Log::channel('messenger')->warning('ProcessInboundMessage: Attachment rejected', [
                    'message_id' => $message->id,
                    'provider_file_id' => $attachment->providerFileId,
                    'filename' => $attachment->filename,
                    'reason' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                // Download or storage failure
                Log::channel('messenger')->error('ProcessInboundMessage: Attachment processing failed', [
                    'message_id' => $message->id,
                    'provider_file_id' => $attachment->providerFileId,
                    'filename' => $attachment->filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        // SQLite: UNIQUE constraint failed
        // MySQL: Duplicate entry ... for key
        // PostgreSQL: duplicate key value violates unique constraint
        $message = $e->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'duplicate key value');
    }

    public function tags(): array
    {
        return [
            'messenger',
            'inbound',
            'provider:'.$this->provider,
            'account:'.$this->connectorAccountId,
        ];
    }
}
