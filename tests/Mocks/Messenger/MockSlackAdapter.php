<?php

declare(strict_types=1);

namespace Tests\Mocks\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\NormalizedMessage;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Mock Slack adapter for integration testing.
 *
 * This adapter returns canned responses and records all interactions
 * for later assertion in tests.
 */
class MockSlackAdapter implements ConnectorAdapterInterface
{
    /**
     * @var array<int, array{session: ChatSession, payload: OutboundPayload, response: ProviderResponse}>
     */
    private array $sentMessages = [];

    /**
     * @var array<int, Request>
     */
    private array $verifiedRequests = [];

    /**
     * @var array<int, Request>
     */
    private array $parsedRequests = [];

    private bool $shouldVerify = true;

    private ?ProviderResponse $nextResponse = null;

    private ?NormalizedMessage $nextParsedMessage = null;

    /**
     * Configure the adapter to reject signature verification.
     */
    public function rejectSignatures(): self
    {
        $this->shouldVerify = false;

        return $this;
    }

    /**
     * Configure the adapter to accept signature verification.
     */
    public function acceptSignatures(): self
    {
        $this->shouldVerify = true;

        return $this;
    }

    /**
     * Set the response for the next sendMessage call.
     */
    public function willRespond(ProviderResponse $response): self
    {
        $this->nextResponse = $response;

        return $this;
    }

    /**
     * Set the parsed message for the next parseInboundMessage call.
     */
    public function willParse(NormalizedMessage $message): self
    {
        $this->nextParsedMessage = $message;

        return $this;
    }

    /**
     * Get all sent messages for assertions.
     *
     * @return array<int, array{session: ChatSession, payload: OutboundPayload, response: ProviderResponse}>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Get the last sent message.
     *
     * @return array{session: ChatSession, payload: OutboundPayload, response: ProviderResponse}|null
     */
    public function getLastSentMessage(): ?array
    {
        return $this->sentMessages[count($this->sentMessages) - 1] ?? null;
    }

    /**
     * Assert that a message was sent with the given content.
     */
    public function assertSent(string $content): void
    {
        foreach ($this->sentMessages as $message) {
            if (str_contains($message['payload']->content, $content)) {
                return;
            }
        }

        throw new \PHPUnit\Framework\AssertionFailedError(
            "No message was sent containing: {$content}"
        );
    }

    /**
     * Assert that no messages were sent.
     */
    public function assertNothingSent(): void
    {
        if (count($this->sentMessages) > 0) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                'Expected no messages to be sent, but '.count($this->sentMessages).' messages were sent.'
            );
        }
    }

    /**
     * Reset all recorded state.
     */
    public function reset(): void
    {
        $this->sentMessages = [];
        $this->verifiedRequests = [];
        $this->parsedRequests = [];
        $this->shouldVerify = true;
        $this->nextResponse = null;
        $this->nextParsedMessage = null;
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $this->verifiedRequests[] = $request;

        return $this->shouldVerify;
    }

    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        $this->parsedRequests[] = $request;

        if ($this->nextParsedMessage) {
            $message = $this->nextParsedMessage;
            $this->nextParsedMessage = null;

            return $message;
        }

        // Default Slack message parsing
        $event = $request->input('event', []);

        return new NormalizedMessage(
            providerUserId: $event['user'] ?? 'U_MOCK_USER',
            channelId: $event['channel'] ?? 'C_MOCK_CHANNEL',
            content: $event['text'] ?? '',
            threadId: $event['thread_ts'] ?? null,
            providerMessageId: $event['ts'] ?? null,
            providerEventId: $request->input('event_id'),
            providerTimestamp: isset($event['ts'])
                ? Carbon::createFromTimestamp((float) $event['ts'])
                : Carbon::now(),
            attachments: [],
        );
    }

    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse
    {
        $response = $this->nextResponse ?? ProviderResponse::success(
            providerMessageId: time().'.'.rand(100000, 999999),
            rawResponse: [
                'ok' => true,
                'channel' => $payload->channelId,
                'ts' => time().'.'.rand(100000, 999999),
            ]
        );

        $this->sentMessages[] = [
            'session' => $session,
            'payload' => $payload,
            'response' => $response,
        ];

        $this->nextResponse = null;

        return $response;
    }

    public function editMessage(ChatSession $session, string $providerMessageId, string $content): ProviderResponse
    {
        return ProviderResponse::success($providerMessageId);
    }

    public function supportsMessageEditing(): bool
    {
        return true;
    }

    public function supportsThreading(): bool
    {
        return true;
    }

    public function getThreadingStrategy(): ThreadingStrategy
    {
        return ThreadingStrategy::Native;
    }

    public function getReplayProtectionStrategy(): ReplayProtectionStrategy
    {
        return ReplayProtectionStrategy::Timestamp;
    }

    public function supportsReactions(): bool
    {
        return true;
    }

    public function addReaction(ChatSession $session, string $messageId, string $emoji): ProviderResponse
    {
        return ProviderResponse::success($messageId);
    }
}
