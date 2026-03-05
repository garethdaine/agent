<?php

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
 * Mock Telegram adapter for integration testing.
 *
 * This adapter returns canned responses and records all interactions
 * for later assertion in tests.
 */
class MockTelegramAdapter implements ConnectorAdapterInterface
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

    private int $nextMessageId = 1000;

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
        $this->nextMessageId = 1000;
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

        // Default Telegram message parsing
        $message = $request->input('message', []);
        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        return new NormalizedMessage(
            providerUserId: (string) ($from['id'] ?? 0),
            channelId: (string) ($chat['id'] ?? 0),
            content: $message['text'] ?? '',
            threadId: isset($message['reply_to_message'])
                ? (string) $message['reply_to_message']['message_id']
                : null,
            providerMessageId: isset($message['message_id'])
                ? (string) $message['message_id']
                : null,
            providerEventId: $request->input('update_id')
                ? (string) $request->input('update_id')
                : null,
            providerTimestamp: isset($message['date'])
                ? Carbon::createFromTimestamp($message['date'])
                : Carbon::now(),
            attachments: [],
        );
    }

    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse
    {
        $messageId = $this->nextMessageId++;

        $response = $this->nextResponse ?? ProviderResponse::success(
            providerMessageId: (string) $messageId,
            rawResponse: [
                'ok' => true,
                'result' => [
                    'message_id' => $messageId,
                    'chat' => ['id' => (int) $payload->channelId],
                    'text' => $payload->content,
                ],
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
        return ThreadingStrategy::ReplyTo;
    }

    public function getReplayProtectionStrategy(): ReplayProtectionStrategy
    {
        return ReplayProtectionStrategy::EventId;
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
