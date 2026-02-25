<?php

namespace App\Contracts\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\ReplayProtectionStrategy;
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
}
