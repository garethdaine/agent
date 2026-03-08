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

class SlackAdapter extends AbstractConnectorAdapter
{
    private const API_BASE_URL = 'https://slack.com/api';

    protected function getProviderName(): string
    {
        return ConnectorAccount::PROVIDER_SLACK;
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        /** @var ConnectorAccount|null $account */
        $account = $request->attributes->get('connector_account');

        if (! $account) {
            return false;
        }

        $signature = $request->header('X-Slack-Signature');
        $timestamp = $request->header('X-Slack-Request-Timestamp');

        if (! $signature || ! $timestamp) {
            return false;
        }

        $signingSecret = $this->getSigningSecret($account);

        if (! $signingSecret) {
            $this->logError('Missing signing secret for Slack account', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        $body = $request->getContent();
        $sigBaseString = 'v0:'.$timestamp.':'.$body;

        $expectedSignature = 'v0='.hash_hmac('sha256', $sigBaseString, $signingSecret);

        return hash_equals($expectedSignature, $signature);
    }

    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        $event = $request->input('event', []);
        $eventType = $event['type'] ?? '';

        $content = $this->extractContent($event, $eventType);
        $attachments = $this->extractAttachments($event);

        return new NormalizedMessage(
            providerUserId: $event['user'] ?? '',
            channelId: $event['channel'] ?? '',
            content: $content,
            threadId: $event['thread_ts'] ?? null,
            providerMessageId: $event['ts'] ?? null,
            providerEventId: $request->input('event_id'),
            providerTimestamp: isset($event['ts'])
                ? Carbon::createFromTimestamp((float) $event['ts'])
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
            $this->logError('Missing bot token for Slack account', [
                'account_id' => $account->id,
            ]);

            return ProviderResponse::failure('Missing bot token');
        }

        $normalizedContent = $this->normalizeContent($payload->content);

        $data = [
            'channel' => $payload->channelId,
            'text' => $normalizedContent,
        ];

        if ($payload->threadId) {
            $data['thread_ts'] = $payload->threadId;
        } elseif ($payload->replyToMessageId) {
            $data['thread_ts'] = $payload->replyToMessageId;
        }

        $httpClient = new MessengerHttpClient($account);

        $result = $httpClient->post(
            self::API_BASE_URL.'/chat.postMessage',
            $data,
            [
                'Authorization' => 'Bearer '.$botToken,
                'Content-Type' => 'application/json',
            ]
        );

        if (! $result['success']) {
            $this->logError('Failed to send Slack message', [
                'account_id' => $account->id,
                'channel' => $payload->channelId,
                'error' => $result['error'],
            ]);

            return ProviderResponse::failure(
                $result['error'] ?? 'Failed to send message',
                $result['response']?->json() ?? []
            );
        }

        $responseData = $result['response']->json();

        if (! ($responseData['ok'] ?? false)) {
            $this->logError('Slack API returned error', [
                'account_id' => $account->id,
                'channel' => $payload->channelId,
                'error' => $responseData['error'] ?? 'Unknown error',
            ]);

            return ProviderResponse::failure(
                $responseData['error'] ?? 'Slack API error',
                $responseData
            );
        }

        $messageTs = $responseData['ts'] ?? null;

        if (! $messageTs) {
            return ProviderResponse::failure('No message timestamp in response', $responseData);
        }

        $this->storeLastBotMessageId($session, $messageTs);

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'channel' => $payload->channelId,
            'message_ts' => $messageTs,
        ]);

        return ProviderResponse::success($messageTs, $responseData);
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

        $result = $httpClient->post(
            self::API_BASE_URL.'/chat.update',
            [
                'channel' => $session->channel_id,
                'ts' => $providerMessageId,
                'text' => $normalizedContent,
            ],
            [
                'Authorization' => 'Bearer '.$botToken,
                'Content-Type' => 'application/json',
            ]
        );

        if (! $result['success'] || ! ($result['response']?->json()['ok'] ?? false)) {
            $slackError = $result['response']?->json()['error'] ?? $result['error'] ?? 'Unknown';

            // "not_modified" is a no-op success during streaming
            if ($slackError === 'not_modified') {
                return ProviderResponse::success($providerMessageId);
            }

            $this->logError('Failed to edit Slack message', [
                'account_id' => $account->id,
                'channel' => $session->channel_id,
                'message_ts' => $providerMessageId,
                'error' => $slackError,
            ]);

            return ProviderResponse::failure($slackError, $result['response']?->json() ?? []);
        }

        return ProviderResponse::success($providerMessageId, $result['response']->json());
    }

    public function supportsMessageEditing(): bool
    {
        return true;
    }

    public function getStreamingConfig(): StreamingConfig
    {
        return StreamingConfig::slack();
    }

    /**
     * Start a native Slack stream (chat.startStream).
     *
     * Requires thread_ts — streamed messages must be replies.
     * Returns the stream message ts on success.
     *
     * @return array{ok: bool, ts: ?string, error: ?string}
     */
    public function startStream(ChatSession $session, string $threadTs, ?string $initialText = null): array
    {
        $account = $session->connectorAccount;
        $botToken = $account ? $this->getBotToken($account) : null;

        if (! $botToken || ! $account) { // @phpstan-ignore booleanNot.alwaysFalse
            return ['ok' => false, 'ts' => null, 'error' => 'Missing bot token or account'];
        }

        $httpClient = new MessengerHttpClient($account);

        $payload = [
            'channel' => $session->channel_id,
            'thread_ts' => $threadTs,
        ];

        if ($initialText !== null && $initialText !== '') {
            $payload['markdown_text'] = $this->normalizeContent($initialText);
        }

        $result = $httpClient->post(
            self::API_BASE_URL.'/chat.startStream',
            $payload,
            ['Authorization' => 'Bearer '.$botToken, 'Content-Type' => 'application/json']
        );

        if (! $result['success'] || ! ($result['response']?->json()['ok'] ?? false)) {
            return [
                'ok' => false,
                'ts' => null,
                'error' => $result['response']?->json()['error'] ?? $result['error'] ?? 'Unknown',
            ];
        }

        $data = $result['response']->json();

        return ['ok' => true, 'ts' => $data['ts'] ?? null, 'error' => null];
    }

    /**
     * Append text to an active Slack stream (chat.appendStream).
     */
    public function appendStream(ChatSession $session, string $streamTs, string $text): bool
    {
        $account = $session->connectorAccount;
        $botToken = $account ? $this->getBotToken($account) : null;

        if (! $botToken || ! $account) { // @phpstan-ignore booleanNot.alwaysFalse
            return false;
        }

        $httpClient = new MessengerHttpClient($account);

        $result = $httpClient->post(
            self::API_BASE_URL.'/chat.appendStream',
            [
                'channel' => $session->channel_id,
                'ts' => $streamTs,
                'markdown_text' => $this->normalizeContent($text),
            ],
            ['Authorization' => 'Bearer '.$botToken, 'Content-Type' => 'application/json']
        );

        return $result['success'] && ($result['response']?->json()['ok'] ?? false);
    }

    /**
     * Stop an active Slack stream (chat.stopStream).
     */
    public function stopStream(ChatSession $session, string $streamTs, ?string $finalText = null): bool
    {
        $account = $session->connectorAccount;
        $botToken = $account ? $this->getBotToken($account) : null;

        if (! $botToken || ! $account) { // @phpstan-ignore booleanNot.alwaysFalse
            return false;
        }

        $httpClient = new MessengerHttpClient($account);

        $payload = [
            'channel' => $session->channel_id,
            'ts' => $streamTs,
        ];

        if ($finalText !== null && $finalText !== '') {
            $payload['markdown_text'] = $this->normalizeContent($finalText);
        }

        $result = $httpClient->post(
            self::API_BASE_URL.'/chat.stopStream',
            $payload,
            ['Authorization' => 'Bearer '.$botToken, 'Content-Type' => 'application/json']
        );

        return $result['success'] && ($result['response']?->json()['ok'] ?? false);
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
            self::API_BASE_URL.'/reactions.add',
            [
                'channel' => $session->channel_id,
                'timestamp' => $messageId,
                'name' => $emoji,
            ],
            [
                'Authorization' => 'Bearer '.$botToken,
                'Content-Type' => 'application/json',
            ]
        );

        if (! $result['success'] || ! ($result['response']?->json()['ok'] ?? false)) {
            $this->logDebug('Failed to add Slack reaction', [
                'channel' => $session->channel_id,
                'timestamp' => $messageId,
                'emoji' => $emoji,
            ]);

            return ProviderResponse::failure(
                $result['response']?->json()['error'] ?? 'Failed to add reaction'
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
        return ThreadingStrategy::Native;
    }

    public function getReplayProtectionStrategy(): ReplayProtectionStrategy
    {
        return ReplayProtectionStrategy::Timestamp;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function extractContent(array $event, string $eventType): string
    {
        if (isset($event['blocks']) && is_array($event['blocks'])) {
            return $this->extractTextFromBlocks($event['blocks']);
        }

        if (isset($event['attachments']) && is_array($event['attachments'])) {
            $attachmentTexts = [];
            foreach ($event['attachments'] as $attachment) {
                if (isset($attachment['text'])) {
                    $attachmentTexts[] = $attachment['text'];
                }
                if (isset($attachment['fallback'])) {
                    $attachmentTexts[] = $attachment['fallback'];
                }
            }
            if (! empty($attachmentTexts)) {
                $prefix = $event['text'] ?? '';

                return trim($prefix.' '.implode("\n", $attachmentTexts));
            }
        }

        return $event['text'] ?? '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function extractTextFromBlocks(array $blocks): string
    {
        $texts = [];

        foreach ($blocks as $block) {
            $blockType = $block['type'] ?? '';

            switch ($blockType) {
                case 'section':
                    if (isset($block['text']['text'])) {
                        $texts[] = $block['text']['text'];
                    }
                    break;

                case 'rich_text':
                    if (isset($block['elements']) && is_array($block['elements'])) {
                        foreach ($block['elements'] as $element) {
                            $texts[] = $this->extractTextFromRichTextElement($element);
                        }
                    }
                    break;

                case 'context':
                    if (isset($block['elements']) && is_array($block['elements'])) {
                        foreach ($block['elements'] as $element) {
                            if ($element['type'] === 'plain_text' || $element['type'] === 'mrkdwn') {
                                $texts[] = $element['text'] ?? '';
                            }
                        }
                    }
                    break;
            }
        }

        return implode("\n", array_filter($texts));
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function extractTextFromRichTextElement(array $element): string
    {
        $elementType = $element['type'] ?? '';

        if ($elementType === 'rich_text_section' && isset($element['elements'])) {
            $texts = [];
            foreach ($element['elements'] as $subElement) {
                if (($subElement['type'] ?? '') === 'text') {
                    $texts[] = $subElement['text'] ?? '';
                }
            }

            return implode('', $texts);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<int, NormalizedAttachment>
     */
    private function extractAttachments(array $event): array
    {
        $attachments = [];

        if (! isset($event['files']) || ! is_array($event['files'])) {
            return $attachments;
        }

        foreach ($event['files'] as $file) {
            $attachments[] = new NormalizedAttachment(
                providerFileId: $file['id'] ?? '',
                filename: $file['name'] ?? 'unknown',
                mimeType: $file['mimetype'] ?? 'application/octet-stream',
                sizeBytes: $file['size'] ?? 0,
                downloadUrl: $file['url_private_download'] ?? $file['url_private'] ?? null,
            );
        }

        return $attachments;
    }

    private function storeLastBotMessageId(ChatSession $session, string $messageTs): void
    {
        Cache::put(
            sprintf('slack:last_bot_message:%s:%s', $session->connector_account_id, $session->channel_id),
            $messageTs,
            86400
        );
    }

    private function getSigningSecret(ConnectorAccount $account): ?string
    {
        return $account->config['signature_verification']['signing_secret']
            ?? $account->credentials['signing_secret']
            ?? null;
    }

    private function getBotToken(ConnectorAccount $account): ?string
    {
        return $account->credentials['bot_token']
            ?? $account->credentials['access_token']
            ?? null;
    }
}
