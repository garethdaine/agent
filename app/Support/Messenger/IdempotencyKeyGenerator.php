<?php

declare(strict_types=1);

namespace App\Support\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\Models\ConnectorAccount;

class IdempotencyKeyGenerator
{
    /**
     * Generate an idempotency key for a normalized message.
     *
     * When provider_message_id is available, use it directly for the most reliable deduplication.
     * Otherwise, generate a deterministic key from message content and metadata.
     */
    public function generate(NormalizedMessage $message, ConnectorAccount $account): string
    {
        // Primary: use provider_message_id if available (most reliable)
        if ($message->providerMessageId !== null && $message->providerMessageId !== '') {
            return hash('sha256', implode(':', [
                $account->provider,
                $account->id,
                $message->providerMessageId,
            ]));
        }

        // Fallback: generate deterministic key from content + metadata
        return $this->generateFallbackKey($message, $account);
    }

    private function generateFallbackKey(NormalizedMessage $message, ConnectorAccount $account): string
    {
        // Get timestamp in milliseconds for precision
        $timestampMs = $message->providerTimestamp
            ? (string) ($message->providerTimestamp->getTimestamp() * 1000)
            : '0';

        // Sort and hash attachment IDs for deterministic ordering
        $attachmentIds = $message->attachmentIds;
        sort($attachmentIds);
        $attachmentHash = hash('sha256', json_encode($attachmentIds));

        // Combine content and attachment hash for content-based deduplication
        $contentHash = hash('sha256', $message->content.$attachmentHash);

        return hash('sha256', implode(':', [
            $account->provider,
            $account->id,
            $message->channelId,
            $message->providerUserId,
            $timestampMs,
            $contentHash,
        ]));
    }
}
