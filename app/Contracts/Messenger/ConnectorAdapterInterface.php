<?php

namespace App\Contracts\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\StreamingConfig;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use Illuminate\Http\Request;

interface ConnectorAdapterInterface
{
    /**
     * Verify the webhook signature for this provider.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse an inbound webhook request into a normalized message.
     */
    public function parseInboundMessage(Request $request): NormalizedMessage;

    /**
     * Send a message to the messenger provider.
     */
    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse;

    /**
     * Edit an existing message by its provider message ID.
     */
    public function editMessage(ChatSession $session, string $providerMessageId, string $content): ProviderResponse;

    /**
     * Whether this provider supports editing messages for progressive updates.
     */
    public function supportsMessageEditing(): bool;

    /**
     * Whether this provider supports native threading.
     */
    public function supportsThreading(): bool;

    /**
     * Get the threading strategy for this provider.
     */
    public function getThreadingStrategy(): ThreadingStrategy;

    /**
     * Get the replay protection strategy for this provider.
     */
    public function getReplayProtectionStrategy(): ReplayProtectionStrategy;

    /**
     * Whether this provider supports adding emoji reactions to messages.
     */
    public function supportsReactions(): bool;

    /**
     * Add an emoji reaction to a message.
     */
    public function addReaction(ChatSession $session, string $messageId, string $emoji): ProviderResponse;

    /**
     * Get the streaming configuration for this provider.
     */
    public function getStreamingConfig(): StreamingConfig;
}
