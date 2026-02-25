<?php

namespace App\Jobs\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\Models\ChatMessage;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
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
        ChatSessionManager $sessionManager
    ): void {
        $account = ConnectorAccount::find($this->connectorAccountId);

        if (! $account) {
            Log::warning('ProcessInboundMessage: Connector account not found', [
                'account_id' => $this->connectorAccountId,
            ]);

            return;
        }

        $adapter = $connectorManager->resolve($this->provider);

        // Parse the inbound message using the adapter
        $normalizedMessage = $this->parseMessage($adapter, $account);

        if (! $normalizedMessage) {
            Log::debug('ProcessInboundMessage: Could not parse message, skipping', [
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
            $this->payload,
            $account
        );

        // Create chat message with idempotency check
        try {
            $message = ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => ChatMessage::DIRECTION_INBOUND,
                'content' => $normalizedMessage->content,
                'attachment_ids' => $this->extractAttachmentIds($normalizedMessage),
                'idempotency_key' => $idempotencyKey,
                'provider_event_id' => $normalizedMessage->providerEventId,
                'provider_message_id' => $normalizedMessage->providerMessageId,
                'provider_timestamp' => $normalizedMessage->providerTimestamp,
            ]);

            Log::info('ProcessInboundMessage: Message created', [
                'message_id' => $message->id,
                'session_id' => $session->id,
                'account_id' => $account->id,
                'user_id' => $user->id,
            ]);

            // Dispatch intent processing job
            ProcessChatIntent::dispatch(
                messageId: $message->id,
                sessionId: $session->id,
                userId: $user->id
            );

        } catch (QueryException $e) {
            // Check if it's a duplicate key violation (idempotency)
            if ($this->isDuplicateKeyException($e)) {
                Log::debug('ProcessInboundMessage: Duplicate message, skipping', [
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
            Log::warning('ProcessInboundMessage: Failed to parse message', [
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
            Log::debug('ProcessInboundMessage: Link prompt rate limited', [
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

        Log::info('ProcessInboundMessage: Dispatching account link prompt', [
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
            Log::debug('ProcessInboundMessage: Re-auth prompt rate limited', [
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

        Log::info('ProcessInboundMessage: Dispatching re-auth prompt for expired link', [
            'account_id' => $account->id,
            'provider_user_id' => $message->providerUserId,
            'link_id' => $link->id,
        ]);
    }

    /**
     * @return array<string>|null
     */
    private function extractAttachmentIds(NormalizedMessage $message): ?array
    {
        if (empty($message->attachments)) {
            return null;
        }

        // For now, just return the provider file IDs
        // Attachment processing will be handled separately
        return array_map(
            fn ($attachment) => $attachment->providerFileId,
            $message->attachments
        );
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
