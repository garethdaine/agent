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

        // Construct the signature base string
        $body = $request->getContent();
        $sigBaseString = 'v0:'.$timestamp.':'.$body;

        // Calculate expected signature
        $expectedSignature = 'v0='.hash_hmac('sha256', $sigBaseString, $signingSecret);

        // Timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }

    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        $event = $request->input('event', []);
        $eventType = $event['type'] ?? '';

        // Handle different event types
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

        $data = [
            'channel' => $payload->channelId,
            'text' => $payload->content,
        ];

        // Add threading support (thread_ts)
        if ($payload->threadId) {
            $data['thread_ts'] = $payload->threadId;
        } elseif ($payload->replyToMessageId) {
            // Use reply_to_message_id as thread_ts for Slack
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

        // Check Slack API response for success
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

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'channel' => $payload->channelId,
            'message_ts' => $messageTs,
        ]);

        return ProviderResponse::success($messageTs, $responseData);
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
     * Extract text content from various Slack event types.
     *
     * @param  array<string, mixed>  $event
     */
    private function extractContent(array $event, string $eventType): string
    {
        // Handle blocks structure (rich text)
        if (isset($event['blocks']) && is_array($event['blocks'])) {
            return $this->extractTextFromBlocks($event['blocks']);
        }

        // Handle attachments text
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

        // Default to plain text field
        return $event['text'] ?? '';
    }

    /**
     * Extract text content from Slack blocks structure.
     *
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
     * Extract text from a rich text element.
     *
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
     * Extract file attachments from Slack event.
     *
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

    private function getSigningSecret(ConnectorAccount $account): ?string
    {
        // Check config first, then credentials
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
