<?php

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

class TelegramAdapter extends AbstractConnectorAdapter
{
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    protected function getProviderName(): string
    {
        return ConnectorAccount::PROVIDER_TELEGRAM;
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        /** @var ConnectorAccount|null $account */
        $account = $request->attributes->get('connector_account');

        if (! $account) {
            return false;
        }

        $expectedToken = $this->getSecretToken($account);

        if (! $expectedToken) {
            // Backward-compatible behavior: if no secret token is configured,
            // fall back to account-key based routing as the only ingress guard.
            $this->logDebug('No Telegram secret token configured; skipping header verification', [
                'account_id' => $account->id,
            ]);

            return true;
        }

        // Telegram uses a secret token header for webhook verification.
        $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (! is_string($providedToken) || $providedToken === '') {
            return false;
        }

        // Timing-safe comparison
        return hash_equals($expectedToken, $providedToken);
    }

    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        // Handle different update types
        $message = $this->extractMessageFromUpdate($request);
        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        // Handle reply threading
        $replyTo = $message['reply_to_message'] ?? null;
        $threadId = $replyTo ? (string) $replyTo['message_id'] : null;

        // Extract attachments
        $attachments = $this->extractAttachments($message);

        return new NormalizedMessage(
            providerUserId: (string) ($from['id'] ?? ''),
            channelId: (string) ($chat['id'] ?? ''),
            content: $message['text'] ?? $message['caption'] ?? '',
            threadId: $threadId,
            providerMessageId: isset($message['message_id']) ? (string) $message['message_id'] : null,
            providerEventId: $request->input('update_id') ? (string) $request->input('update_id') : null,
            providerTimestamp: isset($message['date'])
                ? Carbon::createFromTimestamp($message['date'])
                : null,
            attachments: $attachments,
        );
    }

    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $botToken = $this->getBotToken($account);

        if (! $botToken) {
            $this->logError('Missing bot token for Telegram account', [
                'account_id' => $account->id,
            ]);

            return ProviderResponse::failure('Missing bot token');
        }

        $data = [
            'chat_id' => $payload->channelId,
            'text' => $payload->content,
            'parse_mode' => 'Markdown',
        ];

        // Add reply threading support
        if ($payload->replyToMessageId) {
            $data['reply_to_message_id'] = (int) $payload->replyToMessageId;
        } elseif ($payload->threadId) {
            $data['reply_to_message_id'] = (int) $payload->threadId;
        }

        $httpClient = new MessengerHttpClient($account);

        $result = $httpClient->post(
            self::API_BASE_URL.$botToken.'/sendMessage',
            $data,
            ['Content-Type' => 'application/json']
        );

        if (! $result['success']) {
            $this->logError('Failed to send Telegram message', [
                'account_id' => $account->id,
                'chat_id' => $payload->channelId,
                'error' => $result['error'],
            ]);

            return ProviderResponse::failure(
                $result['error'] ?? 'Failed to send message',
                $result['response']?->json() ?? []
            );
        }

        $responseData = $result['response']->json();

        // Check Telegram API response for success
        if (! ($responseData['ok'] ?? false)) {
            $this->logError('Telegram API returned error', [
                'account_id' => $account->id,
                'chat_id' => $payload->channelId,
                'error' => $responseData['description'] ?? 'Unknown error',
            ]);

            return ProviderResponse::failure(
                $responseData['description'] ?? 'Telegram API error',
                $responseData
            );
        }

        $messageId = $responseData['result']['message_id'] ?? null;

        if (! $messageId) {
            return ProviderResponse::failure('No message ID in response', $responseData);
        }

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'chat_id' => $payload->channelId,
            'message_id' => $messageId,
        ]);

        return ProviderResponse::success((string) $messageId, $responseData);
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
     * Extract message from different Telegram update types.
     *
     * @return array<string, mixed>
     */
    private function extractMessageFromUpdate(Request $request): array
    {
        // Standard message
        if ($request->has('message')) {
            return $request->input('message', []);
        }

        // Edited message
        if ($request->has('edited_message')) {
            return $request->input('edited_message', []);
        }

        // Callback query (from inline keyboards)
        if ($request->has('callback_query')) {
            $callbackQuery = $request->input('callback_query', []);

            return $callbackQuery['message'] ?? [];
        }

        // Channel post
        if ($request->has('channel_post')) {
            return $request->input('channel_post', []);
        }

        // Edited channel post
        if ($request->has('edited_channel_post')) {
            return $request->input('edited_channel_post', []);
        }

        return [];
    }

    /**
     * Extract file attachments from Telegram message.
     *
     * @param  array<string, mixed>  $message
     * @return array<int, NormalizedAttachment>
     */
    private function extractAttachments(array $message): array
    {
        $attachments = [];

        // Handle photos (array of sizes, take largest)
        if (isset($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']); // Largest size
            if ($photo) {
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $photo['file_id'] ?? '',
                    filename: 'photo.jpg',
                    mimeType: 'image/jpeg',
                    sizeBytes: $photo['file_size'] ?? 0,
                );
            }
        }

        // Handle document
        if (isset($message['document'])) {
            $doc = $message['document'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $doc['file_id'] ?? '',
                filename: $doc['file_name'] ?? 'document',
                mimeType: $doc['mime_type'] ?? 'application/octet-stream',
                sizeBytes: $doc['file_size'] ?? 0,
            );
        }

        // Handle audio
        if (isset($message['audio'])) {
            $audio = $message['audio'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $audio['file_id'] ?? '',
                filename: $audio['file_name'] ?? 'audio.mp3',
                mimeType: $audio['mime_type'] ?? 'audio/mpeg',
                sizeBytes: $audio['file_size'] ?? 0,
            );
        }

        // Handle video
        if (isset($message['video'])) {
            $video = $message['video'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $video['file_id'] ?? '',
                filename: $video['file_name'] ?? 'video.mp4',
                mimeType: $video['mime_type'] ?? 'video/mp4',
                sizeBytes: $video['file_size'] ?? 0,
            );
        }

        // Handle voice message
        if (isset($message['voice'])) {
            $voice = $message['voice'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $voice['file_id'] ?? '',
                filename: 'voice.ogg',
                mimeType: $voice['mime_type'] ?? 'audio/ogg',
                sizeBytes: $voice['file_size'] ?? 0,
            );
        }

        // Handle sticker
        if (isset($message['sticker'])) {
            $sticker = $message['sticker'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $sticker['file_id'] ?? '',
                filename: 'sticker.webp',
                mimeType: 'image/webp',
                sizeBytes: $sticker['file_size'] ?? 0,
            );
        }

        return $attachments;
    }

    private function getSecretToken(ConnectorAccount $account): ?string
    {
        // Check explicit Telegram secret token locations only.
        return $account->config['signature_verification']['secret_token']
            ?? $account->credentials['secret_token']
            ?? null;
    }

    private function getBotToken(ConnectorAccount $account): ?string
    {
        return $account->credentials['bot_token'] ?? null;
    }
}
