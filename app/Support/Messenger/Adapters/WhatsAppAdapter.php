<?php

declare(strict_types=1);

namespace App\Support\Messenger\Adapters;

use App\DTOs\Messenger\NormalizedAttachment;
use App\DTOs\Messenger\NormalizedMessage;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Support\Messenger\MessengerHttpClient;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * WhatsApp adapter implementing the messenger connector interface.
 *
 * WhatsApp Cloud API v18+ is used for all API calls.
 * Webhook-only mode (no local/Gateway mode supported).
 *
 * Threading Strategy:
 * - Quote replies: Include context.message_id in message payload
 * - Falls back to single summary message if chain >5 messages
 *
 * Identity Mapping:
 * - Phone numbers (E.164 format) mapped to MessengerIdentityLink
 *
 * Rate Limiting:
 * - Meta API rate limits apply
 * - Should handle 429 responses with backoff
 */
class WhatsAppAdapter extends AbstractConnectorAdapter
{
    private const API_BASE_URL = 'https://graph.facebook.com/v18.0';

    private const MAX_QUOTE_CHAIN_DEPTH = 5;

    protected function getProviderName(): string
    {
        return ConnectorAccount::PROVIDER_WHATSAPP;
    }

    /**
     * Verify the webhook signature using HMAC-SHA256.
     *
     * WhatsApp uses HMAC-SHA256 signatures with:
     * - X-Hub-Signature-256: sha256=hex_encoded_signature
     * - Message = raw body
     * - Key = app_secret
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        /** @var ConnectorAccount|null $account */
        $account = $request->attributes->get('connector_account');

        if (! $account) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $appSecret = $this->getAppSecret($account);

        if (! $appSecret) {
            $this->logError('Missing app secret for WhatsApp account', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        try {
            $body = $request->getContent();
            $expectedSignature = 'sha256='.hash_hmac('sha256', $body, $appSecret);

            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            $this->logError('HMAC-SHA256 signature verification failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse an inbound webhook request into a normalized message.
     *
     * Handles:
     * - Text messages
     * - Media messages (image, document, audio, video)
     * - Status updates (sent, delivered, read) - returns empty content
     */
    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        $data = $request->all();

        // WhatsApp webhook format: entry[].changes[].value.messages[]
        $entry = $data['entry'][0] ?? [];
        $change = $entry['changes'][0] ?? [];
        $value = $change['value'] ?? [];

        // Handle status updates (sent, delivered, read)
        if (isset($value['statuses'])) {
            return $this->parseStatusUpdate($value);
        }

        // Handle incoming messages
        $messages = $value['messages'] ?? [];

        if (empty($messages)) {
            // No messages - return empty normalized message
            return new NormalizedMessage(
                providerUserId: '',
                channelId: '',
                content: '',
            );
        }

        $message = $messages[0];
        $contact = $value['contacts'][0] ?? [];

        return $this->parseMessage($message, $contact);
    }

    /**
     * Send a message to a WhatsApp user.
     */
    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $phoneNumberId = $this->getPhoneNumberId($account);
        $accessToken = $this->getAccessToken($account);

        if (! $phoneNumberId || ! $accessToken) {
            $this->logError('Missing credentials for WhatsApp account', [
                'account_id' => $account->id,
            ]);

            return ProviderResponse::failure('Missing WhatsApp credentials');
        }

        $httpClient = new MessengerHttpClient($account);

        // Normalize phone number to E.164 format
        $toPhoneNumber = $this->normalizePhone($payload->channelId);

        // Normalize content to handle escaped newlines and formatting issues
        $normalizedContent = $this->normalizeContent($payload->content);

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $toPhoneNumber,
            'type' => 'text',
            'text' => ['body' => $normalizedContent],
        ];

        // Add quote reply context if replying to a message
        // But skip if chain depth exceeds max
        if ($payload->replyToMessageId && ! $this->isChainTooLong($session)) {
            $data['context'] = ['message_id' => $payload->replyToMessageId];
        }

        $result = $httpClient->post(
            self::API_BASE_URL.'/'.$phoneNumberId.'/messages',
            $data,
            [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ]
        );

        if (! $result['success']) {
            $this->logError('Failed to send WhatsApp message', [
                'account_id' => $account->id,
                'phone_number' => $toPhoneNumber,
                'error' => $result['error'],
            ]);

            $errorMessage = $result['error'] ?? 'Failed to send message';
            $responseData = $result['response']?->json() ?? [];

            // Extract WhatsApp/Meta error message if available
            if (isset($responseData['error']['message'])) {
                $errorMessage = $responseData['error']['message'];
            }

            return ProviderResponse::failure($errorMessage, $responseData);
        }

        $responseData = $result['response']->json();
        $messageId = $responseData['messages'][0]['id'] ?? null;

        if (! $messageId) {
            return ProviderResponse::failure('No message ID in response', $responseData);
        }

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'phone_number' => $toPhoneNumber,
            'message_id' => $messageId,
        ]);

        return ProviderResponse::success($messageId, $responseData);
    }

    /**
     * Send a template message (for outbound outside 24h window).
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    public function sendTemplate(
        ChatSession $session,
        string $phoneNumber,
        string $templateName,
        array $components = []
    ): ProviderResponse {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $phoneNumberId = $this->getPhoneNumberId($account);
        $accessToken = $this->getAccessToken($account);

        if (! $phoneNumberId || ! $accessToken) {
            return ProviderResponse::failure('Missing WhatsApp credentials');
        }

        $httpClient = new MessengerHttpClient($account);

        $toPhoneNumber = $this->normalizePhone($phoneNumber);

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $toPhoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'en'],
                'components' => $components,
            ],
        ];

        $result = $httpClient->post(
            self::API_BASE_URL.'/'.$phoneNumberId.'/messages',
            $data,
            [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ]
        );

        if (! $result['success']) {
            $errorMessage = $result['error'] ?? 'Failed to send template';
            $responseData = $result['response']?->json() ?? [];

            if (isset($responseData['error']['message'])) {
                $errorMessage = $responseData['error']['message'];
            }

            return ProviderResponse::failure($errorMessage, $responseData);
        }

        $responseData = $result['response']->json();
        $messageId = $responseData['messages'][0]['id'] ?? null;

        if (! $messageId) {
            return ProviderResponse::failure('No message ID in response', $responseData);
        }

        return ProviderResponse::success($messageId, $responseData);
    }

    public function supportsReactions(): bool
    {
        return true;
    }

    public function addReaction(ChatSession $session, string $messageId, string $emoji): ProviderResponse
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $phoneNumberId = $this->getPhoneNumberId($account);
        $accessToken = $this->getAccessToken($account);

        if (! $phoneNumberId || ! $accessToken) {
            return ProviderResponse::failure('Missing WhatsApp credentials');
        }

        $httpClient = new MessengerHttpClient($account);

        $result = $httpClient->post(
            self::API_BASE_URL.'/'.$phoneNumberId.'/messages',
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->normalizePhone($session->channel_id),
                'type' => 'reaction',
                'reaction' => [
                    'message_id' => $messageId,
                    'emoji' => $emoji,
                ],
            ],
            [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ]
        );

        if (! $result['success']) {
            $this->logDebug('Failed to add WhatsApp reaction', [
                'phone_number' => $session->channel_id,
                'message_id' => $messageId,
                'emoji' => $emoji,
            ]);

            return ProviderResponse::failure(
                $result['response']?->json()['error']['message'] ?? 'Failed to add reaction'
            );
        }

        return ProviderResponse::success($messageId);
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

    /**
     * Parse a WhatsApp message into a normalized message.
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $contact
     */
    private function parseMessage(array $message, array $contact): NormalizedMessage
    {
        $from = $message['from'] ?? '';
        $messageId = $message['id'] ?? '';
        $timestamp = $message['timestamp'] ?? '';
        $type = $message['type'] ?? 'text';

        // Extract content based on message type
        $content = '';
        $attachments = [];

        switch ($type) {
            case 'text':
                $content = $message['text']['body'] ?? '';
                break;

            case 'image':
                $content = $message['image']['caption'] ?? '';
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $message['image']['id'] ?? '',
                    filename: 'image.jpg',
                    mimeType: $message['image']['mime_type'] ?? 'image/jpeg',
                    sizeBytes: 0,
                    downloadUrl: null, // WhatsApp requires separate API call for download URL
                );
                break;

            case 'document':
                $content = $message['document']['caption'] ?? '';
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $message['document']['id'] ?? '',
                    filename: $message['document']['filename'] ?? 'document',
                    mimeType: $message['document']['mime_type'] ?? 'application/octet-stream',
                    sizeBytes: 0,
                    downloadUrl: null,
                );
                break;

            case 'audio':
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $message['audio']['id'] ?? '',
                    filename: 'audio.ogg',
                    mimeType: $message['audio']['mime_type'] ?? 'audio/ogg',
                    sizeBytes: 0,
                    downloadUrl: null,
                );
                break;

            case 'video':
                $content = $message['video']['caption'] ?? '';
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $message['video']['id'] ?? '',
                    filename: 'video.mp4',
                    mimeType: $message['video']['mime_type'] ?? 'video/mp4',
                    sizeBytes: 0,
                    downloadUrl: null,
                );
                break;

            case 'sticker':
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $message['sticker']['id'] ?? '',
                    filename: 'sticker.webp',
                    mimeType: $message['sticker']['mime_type'] ?? 'image/webp',
                    sizeBytes: 0,
                    downloadUrl: null,
                );
                break;
        }

        return new NormalizedMessage(
            providerUserId: $from,
            channelId: $from, // In WhatsApp, the channel is the phone number
            content: $content,
            threadId: null,
            providerMessageId: $messageId,
            providerEventId: $messageId,
            providerTimestamp: $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null,
            attachments: $attachments,
        );
    }

    /**
     * Parse a status update into a normalized message.
     *
     * Status updates (sent, delivered, read) don't have message content.
     *
     * @param  array<string, mixed>  $value
     */
    private function parseStatusUpdate(array $value): NormalizedMessage
    {
        $status = $value['statuses'][0] ?? [];

        return new NormalizedMessage(
            providerUserId: $status['recipient_id'] ?? '',
            channelId: $status['recipient_id'] ?? '',
            content: '', // Status updates don't have content
            threadId: null,
            providerMessageId: $status['id'] ?? null,
            providerEventId: $status['id'] ?? null,
            providerTimestamp: isset($status['timestamp'])
                ? Carbon::createFromTimestamp((int) $status['timestamp'])
                : null,
        );
    }

    /**
     * Normalize a phone number to E.164 format.
     *
     * Removes all non-numeric characters except leading +.
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except +
        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure there's no leading + in the middle
        if ($normalized && $normalized[0] === '+') {
            return $normalized[0].preg_replace('/[^0-9]/', '', substr($normalized, 1));
        }

        return preg_replace('/[^0-9]/', '', $normalized);
    }

    /**
     * Check if the quote chain is too long.
     *
     * If chain depth exceeds MAX_QUOTE_CHAIN_DEPTH, we should send
     * a summary message instead of another quote reply.
     */
    private function isChainTooLong(ChatSession $session): bool
    {
        $metadata = $session->metadata ?? [];
        $chainDepth = $metadata['quote_chain_depth'] ?? 0;

        return $chainDepth >= self::MAX_QUOTE_CHAIN_DEPTH;
    }

    /**
     * Get the phone number ID from credentials.
     */
    private function getPhoneNumberId(ConnectorAccount $account): ?string
    {
        return $account->credentials['phone_number_id'] ?? null;
    }

    /**
     * Get the access token from credentials.
     */
    private function getAccessToken(ConnectorAccount $account): ?string
    {
        return $account->credentials['access_token'] ?? null;
    }

    /**
     * Get the app secret for signature verification.
     */
    private function getAppSecret(ConnectorAccount $account): ?string
    {
        return $account->credentials['app_secret'] ?? null;
    }
}
