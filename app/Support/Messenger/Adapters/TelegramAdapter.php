<?php

declare(strict_types=1);

namespace App\Support\Messenger\Adapters;

use App\DTOs\Messenger\NormalizedAttachment;
use App\DTOs\Messenger\NormalizedMessage;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\StreamingConfig;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Support\Messenger\MessengerHttpClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            $this->logDebug('No Telegram secret token configured; skipping header verification', [
                'account_id' => $account->id,
            ]);

            return true;
        }

        $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (! is_string($providedToken) || $providedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $providedToken);
    }

    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        $message = $this->extractMessageFromUpdate($request);
        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        $replyTo = $message['reply_to_message'] ?? null;
        $threadId = $replyTo ? (string) $replyTo['message_id'] : null;

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

        $normalizedContent = $this->normalizeContent($payload->content);

        $data = [
            'chat_id' => $payload->channelId,
            'text' => $normalizedContent,
            'parse_mode' => 'Markdown',
        ];

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

        $this->storeLastBotMessageId($session, (string) $messageId);

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'chat_id' => $payload->channelId,
            'message_id' => $messageId,
        ]);

        return ProviderResponse::success((string) $messageId, $responseData);
    }

    public function editMessage(ChatSession $session, string $providerMessageId, string $content): ProviderResponse
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $botToken = $this->getBotToken($account);

        if (! $botToken) {
            return ProviderResponse::failure('Missing bot token');
        }

        $normalizedContent = $this->normalizeContent($content);
        $httpClient = new MessengerHttpClient($account);

        $result = $this->attemptTelegramEdit(
            $httpClient, $botToken, $session->channel_id, (int) $providerMessageId, $normalizedContent, 'Markdown'
        );

        if ($result->success) {
            return $result;
        }

        $errorDescription = strtolower($result->error ?? '');

        // Streaming can produce partial markdown that Telegram rejects — retry without parse_mode
        if (str_contains($errorDescription, "can't parse entities") || str_contains($errorDescription, 'parse')) {
            $this->logDebug('Retrying edit without Markdown parse_mode', [
                'message_id' => $providerMessageId,
            ]);

            return $this->attemptTelegramEdit(
                $httpClient, $botToken, $session->channel_id, (int) $providerMessageId, $normalizedContent, null
            );
        }

        // "message is not modified" is a no-op success during streaming
        if (str_contains($errorDescription, 'not modified')) {
            return ProviderResponse::success($providerMessageId);
        }

        return $result;
    }

    private function attemptTelegramEdit(
        MessengerHttpClient $httpClient,
        string $botToken,
        string $chatId,
        int $messageId,
        string $content,
        ?string $parseMode
    ): ProviderResponse {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $content,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        $result = $httpClient->post(
            self::API_BASE_URL.$botToken.'/editMessageText',
            $payload,
            ['Content-Type' => 'application/json']
        );

        if (! $result['success'] || ! ($result['response']?->json()['ok'] ?? false)) {
            $description = $result['response']?->json()['description'] ?? $result['error'] ?? 'Unknown';

            $this->logError('Failed to edit Telegram message', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'parse_mode' => $parseMode,
                'error' => $description,
            ]);

            return ProviderResponse::failure($description, $result['response']?->json() ?? []);
        }

        return ProviderResponse::success((string) $messageId, $result['response']->json());
    }

    public function supportsMessageEditing(): bool
    {
        return true;
    }

    public function getStreamingConfig(): StreamingConfig
    {
        return StreamingConfig::telegram();
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

        $botToken = $this->getBotToken($account);

        if (! $botToken) {
            return ProviderResponse::failure('Missing bot token');
        }

        $httpClient = new MessengerHttpClient($account);

        $result = $httpClient->post(
            self::API_BASE_URL.$botToken.'/setMessageReaction',
            [
                'chat_id' => $session->channel_id,
                'message_id' => (int) $messageId,
                'reaction' => [
                    ['type' => 'emoji', 'emoji' => $emoji],
                ],
            ],
            ['Content-Type' => 'application/json']
        );

        if (! $result['success'] || ! ($result['response']?->json()['ok'] ?? false)) {
            $this->logDebug('Failed to add Telegram reaction', [
                'chat_id' => $session->channel_id,
                'message_id' => $messageId,
                'emoji' => $emoji,
            ]);

            return ProviderResponse::failure(
                $result['response']?->json()['description'] ?? 'Failed to add reaction'
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
     * @return array<string, mixed>
     */
    private function extractMessageFromUpdate(Request $request): array
    {
        if ($request->has('message')) {
            return $request->input('message', []);
        }

        if ($request->has('edited_message')) {
            return $request->input('edited_message', []);
        }

        if ($request->has('callback_query')) {
            $callbackQuery = $request->input('callback_query', []);

            return $callbackQuery['message'] ?? [];
        }

        if ($request->has('channel_post')) {
            return $request->input('channel_post', []);
        }

        if ($request->has('edited_channel_post')) {
            return $request->input('edited_channel_post', []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, NormalizedAttachment>
     */
    private function extractAttachments(array $message): array
    {
        $attachments = [];

        if (isset($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']);
            if ($photo) {
                $attachments[] = new NormalizedAttachment(
                    providerFileId: $photo['file_id'] ?? '',
                    filename: 'photo.jpg',
                    mimeType: 'image/jpeg',
                    sizeBytes: $photo['file_size'] ?? 0,
                );
            }
        }

        if (isset($message['document'])) {
            $doc = $message['document'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $doc['file_id'] ?? '',
                filename: $doc['file_name'] ?? 'document',
                mimeType: $doc['mime_type'] ?? 'application/octet-stream',
                sizeBytes: $doc['file_size'] ?? 0,
            );
        }

        if (isset($message['audio'])) {
            $audio = $message['audio'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $audio['file_id'] ?? '',
                filename: $audio['file_name'] ?? 'audio.mp3',
                mimeType: $audio['mime_type'] ?? 'audio/mpeg',
                sizeBytes: $audio['file_size'] ?? 0,
            );
        }

        if (isset($message['video'])) {
            $video = $message['video'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $video['file_id'] ?? '',
                filename: $video['file_name'] ?? 'video.mp4',
                mimeType: $video['mime_type'] ?? 'video/mp4',
                sizeBytes: $video['file_size'] ?? 0,
            );
        }

        if (isset($message['voice'])) {
            $voice = $message['voice'];
            $attachments[] = new NormalizedAttachment(
                providerFileId: $voice['file_id'] ?? '',
                filename: 'voice.ogg',
                mimeType: $voice['mime_type'] ?? 'audio/ogg',
                sizeBytes: $voice['file_size'] ?? 0,
            );
        }

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

    private function storeLastBotMessageId(ChatSession $session, string $messageId): void
    {
        Cache::put(
            sprintf('telegram:last_bot_message:%s:%s', $session->connector_account_id, $session->channel_id),
            $messageId,
            86400
        );
    }

    private function getSecretToken(ConnectorAccount $account): ?string
    {
        return $account->config['signature_verification']['secret_token']
            ?? $account->credentials['secret_token']
            ?? null;
    }

    private function getBotToken(ConnectorAccount $account): ?string
    {
        return $account->credentials['bot_token'] ?? null;
    }
}
