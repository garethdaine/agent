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
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Support\Messenger\MessengerHttpClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Discord adapter implementing the messenger connector interface.
 *
 * Discord API v10 is used for all API calls.
 *
 * Threading Strategy:
 * - Guild channels: Native threads via message_reference or thread channels
 * - DMs: Edit-based pseudo-threading (append to previous bot message)
 *
 * Identity Mapping:
 * - Discord snowflake user IDs are mapped to MessengerIdentityLink
 *
 * Rate Limiting:
 * - Discord returns X-RateLimit-* headers
 * - 429 responses include retry_after in the response body
 */
class DiscordAdapter extends AbstractConnectorAdapter
{
    private const API_BASE_URL = 'https://discord.com/api/v10';

    private const INTERACTION_CONTEXT_TTL_SECONDS = 900;

    private const MAX_MESSAGE_LENGTH = 1950;

    protected function getProviderName(): string
    {
        return ConnectorAccount::PROVIDER_DISCORD;
    }

    /**
     * Verify the webhook signature using Ed25519.
     *
     * Discord uses Ed25519 signatures with:
     * - X-Signature-Ed25519: hex-encoded signature
     * - X-Signature-Timestamp: timestamp string
     * - Message = timestamp + raw body
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        /** @var ConnectorAccount|null $account */
        $account = $request->attributes->get('connector_account');

        if (! $account) {
            return false;
        }

        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if (! $signature || ! $timestamp) {
            return false;
        }

        $publicKey = $this->getPublicKey($account);

        if (! $publicKey) {
            $this->logError('Missing public key for Discord account', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        try {
            $body = $request->getContent();
            $message = $timestamp.$body;

            $verified = sodium_crypto_sign_verify_detached(
                hex2bin($signature),
                $message,
                hex2bin($publicKey)
            );

            return $verified;
        } catch (\Exception $e) {
            $this->logError('Ed25519 signature verification failed', [
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
     * - MESSAGE_CREATE events (from Gateway)
     * - INTERACTION_CREATE events (APPLICATION_COMMAND, MESSAGE_COMPONENT, etc.)
     */
    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        $data = $request->all();
        /** @var ConnectorAccount|null $account */
        $account = $request->attributes->get('connector_account');

        // Check if this is an interaction (webhook) or a Gateway event
        if (isset($data['type']) && is_numeric($data['type'])) {
            $this->storeInteractionContext($account, $data);

            return $this->parseInteraction($data);
        }

        // Gateway event
        if (isset($data['t']) && isset($data['d'])) {
            if (($data['t'] ?? null) === 'INTERACTION_CREATE' && is_array($data['d'] ?? null)) {
                $this->storeInteractionContext($account, $data['d']);
            }

            return $this->parseGatewayEvent($data);
        }

        // Direct message data from Gateway (d field only)
        if (isset($data['d'])) {
            return $this->parseMessageData($data['d']);
        }

        // Fallback: assume it's message data directly
        return $this->parseMessageData($data);
    }

    private const CHANNEL_WEBHOOK_CACHE_TTL_SECONDS = 604800;

    /**
     * Send a message to a Discord channel.
     */
    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $botToken = $this->getBotToken($account);

        if (! $botToken) {
            $this->logError('Missing bot token for Discord account', [
                'account_id' => $account->id,
            ]);

            return ProviderResponse::failure('Missing bot token');
        }

        if ($payload->senderUsername !== null && $payload->senderUsername !== '') {
            $webhookResult = $this->sendViaWebhookAsUser($session, $account, $payload, $botToken);
            if ($webhookResult !== null) {
                return $webhookResult;
            }
        }

        // Normalize content to handle escaped newlines and formatting issues
        $normalizedContent = $this->normalizeContent($payload->content);

        // If this outbound is a response to a deferred slash interaction,
        // edit the original interaction response so Discord clears "thinking…".
        $interactionContext = $this->pullInteractionContextForSession($session, $account);
        if ($interactionContext !== null) {
            $interactionChunks = $this->chunkContent($normalizedContent);
            $originalResponse = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->patch(
                    sprintf(
                        '%s/webhooks/%s/%s/messages/@original',
                        self::API_BASE_URL,
                        $interactionContext['application_id'],
                        $interactionContext['token']
                    ),
                    ['content' => $interactionChunks[0]]
                );

            if ($originalResponse->successful()) {
                $responseData = $originalResponse->json();
                $messageId = $responseData['id'] ?? null;

                if ($messageId !== null) {
                    $this->storeLastBotMessageId($session, $messageId);

                    if (count($interactionChunks) > 1) {
                        $this->sendOverflowChunks($session, $account, $botToken, array_slice($interactionChunks, 1));
                    }

                    return ProviderResponse::success((string) $messageId, $responseData);
                }
            } else {
                $this->logError('Failed to edit original Discord interaction response, falling back to channel post', [
                    'account_id' => $account->id,
                    'channel_id' => $session->channel_id,
                    'status' => $originalResponse->status(),
                    'body' => $originalResponse->body(),
                ]);
            }
        }

        // Determine target channel
        // If thread_id is provided, send to thread; otherwise send to main channel
        $targetChannelId = $payload->threadId ?? $payload->channelId;

        $httpClient = new MessengerHttpClient($account);
        $chunks = $this->chunkContent($normalizedContent);

        $lastMessageId = null;
        $lastResponseData = [];

        foreach ($chunks as $i => $chunk) {
            $data = ['content' => $chunk];

            if ($i === 0 && $payload->components !== null) {
                $data['components'] = $payload->components;
            }

            if ($i === 0 && $payload->replyToMessageId) {
                $data['message_reference'] = [
                    'message_id' => $payload->replyToMessageId,
                ];
            }

            $result = $httpClient->post(
                self::API_BASE_URL.'/channels/'.$targetChannelId.'/messages',
                $data,
                [
                    'Authorization' => 'Bot '.$botToken,
                    'Content-Type' => 'application/json',
                ]
            );

            if (! $result['success']) {
                $this->logError('Failed to send Discord message', [
                    'account_id' => $account->id,
                    'channel_id' => $targetChannelId,
                    'error' => $result['error'],
                    'chunk' => $i + 1,
                    'total_chunks' => count($chunks),
                ]);

                $errorMessage = $result['error'] ?? 'Failed to send message';
                $responseData = $result['response']?->json() ?? [];

                if (isset($responseData['message'])) {
                    $errorMessage = $responseData['message'];
                }

                return ProviderResponse::failure($errorMessage, $responseData);
            }

            $lastResponseData = $result['response']->json();
            $lastMessageId = $lastResponseData['id'] ?? null;
        }

        if (! $lastMessageId) {
            return ProviderResponse::failure('No message ID in response', $lastResponseData);
        }

        $this->storeLastBotMessageId($session, $lastMessageId);

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'channel_id' => $targetChannelId,
            'message_id' => $lastMessageId,
            'chunks' => count($chunks),
        ]);

        return ProviderResponse::success($lastMessageId, $lastResponseData);
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

        $targetChannelId = $session->thread_id ?? $session->channel_id;

        // Normalize content to handle escaped newlines and formatting issues
        $normalizedContent = $this->normalizeContent($content);

        $editResponse = Http::withHeaders([
            'Authorization' => 'Bot '.$botToken,
            'Content-Type' => 'application/json',
        ])->patch(
            self::API_BASE_URL.'/channels/'.$targetChannelId.'/messages/'.$providerMessageId,
            ['content' => $normalizedContent]
        );

        if (! $editResponse->successful()) {
            $this->logError('Failed to edit Discord message for streaming', [
                'account_id' => $account->id,
                'channel_id' => $targetChannelId,
                'message_id' => $providerMessageId,
                'status' => $editResponse->status(),
            ]);

            return ProviderResponse::failure(
                $editResponse->json()['message'] ?? 'Failed to edit message',
                $editResponse->json()
            );
        }

        return ProviderResponse::success($providerMessageId, $editResponse->json());
    }

    public function supportsMessageEditing(): bool
    {
        return true;
    }

    public function getStreamingConfig(): StreamingConfig
    {
        return StreamingConfig::discord();
    }

    /**
     * Append content to the last bot message in a DM (pseudo-threading).
     *
     * This is used in DMs where native threading isn't supported.
     */
    public function appendToLastMessage(ChatSession $session, string $content): ProviderResponse
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ProviderResponse::failure('No connector account associated with session');
        }

        $botToken = $this->getBotToken($account);
        $lastMessageId = $this->getLastBotMessageId($session);

        if (! $lastMessageId) {
            // No previous message to edit; send a new one
            return $this->sendMessage($session, new OutboundPayload(
                content: $content,
                channelId: $session->channel_id,
                threadId: null,
                replyToMessageId: null,
                attachmentIds: [],
            ));
        }

        // Fetch the existing message to get its content
        $existingResponse = Http::withHeaders([
            'Authorization' => 'Bot '.$botToken,
        ])->get(self::API_BASE_URL.'/channels/'.$session->channel_id.'/messages/'.$lastMessageId);

        $existingContent = '';
        if ($existingResponse->successful()) {
            $existingContent = $existingResponse->json()['content'] ?? '';
        }

        // Normalize and append new content with separator
        $normalizedContent = $this->normalizeContent($content);
        $newContent = $existingContent."\n\n---\n\n".$normalizedContent;

        // Edit the message
        $editResponse = Http::withHeaders([
            'Authorization' => 'Bot '.$botToken,
            'Content-Type' => 'application/json',
        ])->patch(
            self::API_BASE_URL.'/channels/'.$session->channel_id.'/messages/'.$lastMessageId,
            ['content' => $newContent]
        );

        if (! $editResponse->successful()) {
            $this->logError('Failed to edit Discord message', [
                'account_id' => $account->id,
                'channel_id' => $session->channel_id,
                'message_id' => $lastMessageId,
            ]);

            return ProviderResponse::failure(
                $editResponse->json()['message'] ?? 'Failed to edit message',
                $editResponse->json()
            );
        }

        $responseData = $editResponse->json();

        return ProviderResponse::success($lastMessageId, $responseData);
    }

    /**
     * Create a thread from a message in a guild channel.
     *
     * @return array{success: bool, thread_id: ?string, error: ?string}
     */
    public function createThread(ChatSession $session, string $name, ?string $messageId = null): array
    {
        $account = $session->connectorAccount;

        if (! $account) {
            return ['success' => false, 'thread_id' => null, 'error' => 'No connector account'];
        }

        $botToken = $this->getBotToken($account);

        $data = [
            'name' => substr($name, 0, 100), // Thread names max 100 chars
            'auto_archive_duration' => 1440, // 24 hours
        ];

        $url = $messageId
            ? self::API_BASE_URL.'/channels/'.$session->channel_id.'/messages/'.$messageId.'/threads'
            : self::API_BASE_URL.'/channels/'.$session->channel_id.'/threads';

        $response = Http::withHeaders([
            'Authorization' => 'Bot '.$botToken,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        if (! $response->successful()) {
            return [
                'success' => false,
                'thread_id' => null,
                'error' => $response->json()['message'] ?? 'Failed to create thread',
            ];
        }

        return [
            'success' => true,
            'thread_id' => $response->json()['id'] ?? null,
            'error' => null,
        ];
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

        $targetChannelId = $session->thread_id ?? $session->channel_id;
        $encodedEmoji = rawurlencode($emoji);

        $response = Http::withHeaders([
            'Authorization' => 'Bot '.$botToken,
        ])->put(
            self::API_BASE_URL.'/channels/'.$targetChannelId.'/messages/'.$messageId.'/reactions/'.$encodedEmoji.'/@me'
        );

        if (! $response->successful()) {
            $this->logDebug('Failed to add Discord reaction', [
                'channel_id' => $targetChannelId,
                'message_id' => $messageId,
                'emoji' => $emoji,
                'status' => $response->status(),
            ]);

            return ProviderResponse::failure(
                $response->json()['message'] ?? 'Failed to add reaction',
                $response->json()
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
        return ReplayProtectionStrategy::EventId;
    }

    /**
     * Parse a Discord interaction (from webhook).
     *
     * @param  array<string, mixed>  $data
     */
    private function parseInteraction(array $data): NormalizedMessage
    {
        $type = (int) $data['type'];

        // Extract user from member (guild) or user (DM)
        $user = $data['member']['user'] ?? $data['user'] ?? [];
        $providerUserId = $user['id'] ?? '';
        $channelId = $data['channel_id'] ?? '';

        // Build content from interaction data
        $content = $this->buildInteractionContent($data);

        if ($type === 2 && trim($content) === '') {
            $interactionData = is_array($data['data'] ?? null) ? $data['data'] : [];

            Log::warning('Discord interaction normalized to empty content', [
                'interaction_id' => $data['id'] ?? null,
                'interaction_type' => $type,
                'top_level_keys' => array_keys($data),
                'interaction_data_keys' => array_keys($interactionData),
                'command_name' => $interactionData['name'] ?? null,
            ]);
        }

        $attachments = $this->extractResolvedAttachments($data);

        return new NormalizedMessage(
            providerUserId: $providerUserId,
            channelId: $channelId,
            content: $content,
            threadId: $data['channel']['parent_id'] ?? null,
            providerMessageId: $data['id'] ?? null,
            providerEventId: $data['id'] ?? null,
            providerTimestamp: null,
            attachments: $attachments,
        );
    }

    /**
     * Parse a Discord Gateway event.
     *
     * @param  array<string, mixed>  $data
     */
    private function parseGatewayEvent(array $data): NormalizedMessage
    {
        $eventType = $data['t'] ?? '';
        $eventData = is_array($data['d'] ?? null) ? $data['d'] : [];

        if ($eventType === 'INTERACTION_CREATE') {
            return $this->parseInteraction($eventData);
        }

        return $this->parseMessageData($eventData);
    }

    /**
     * Parse Discord message data.
     *
     * @param  array<string, mixed>  $data
     */
    private function parseMessageData(array $data): NormalizedMessage
    {
        $author = $data['author'] ?? [];
        $attachments = $this->extractAttachments($data['attachments'] ?? []);

        return new NormalizedMessage(
            providerUserId: $author['id'] ?? '',
            channelId: $data['channel_id'] ?? '',
            content: $data['content'] ?? '',
            threadId: $data['thread']['id'] ?? null,
            providerMessageId: $data['id'] ?? null,
            providerEventId: $data['id'] ?? null,
            providerTimestamp: isset($data['timestamp'])
                ? Carbon::parse($data['timestamp'])
                : null,
            attachments: $attachments,
        );
    }

    /**
     * Build content string from interaction data.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildInteractionContent(array $data): string
    {
        $interactionData = is_array($data['data'] ?? null) ? $data['data'] : [];

        // For slash commands, build from name and options
        if (isset($interactionData['name']) && is_string($interactionData['name'])) {
            $commandName = strtolower(trim($interactionData['name']));
            $options = is_array($interactionData['options'] ?? null) ? $interactionData['options'] : [];

            $mapped = $this->mapKnownSlashCommand($commandName, $options);
            if ($mapped !== null) {
                return $mapped;
            }

            $parts = [$commandName];
            $this->appendInteractionOptionParts($options, $parts);

            return trim(implode(' ', $parts));
        }

        // For button clicks, return custom_id
        if (isset($interactionData['custom_id'])) {
            return 'button:'.$interactionData['custom_id'];
        }

        // For modal submits, combine values
        if (isset($interactionData['components']) && is_array($interactionData['components'])) {
            $values = [];
            foreach ($interactionData['components'] as $row) {
                foreach ($row['components'] ?? [] as $component) {
                    if (isset($component['value'])) {
                        $values[] = $component['value'];
                    }
                }
            }

            return implode("\n", $values);
        }

        return '';
    }

    private const SLASH_COMMAND_NAMES = [
        'jobs', 'runs', 'status', 'sessions', 'mode', 'approve', 'deny', 'browser', 'ask', 'context', 'new',
        'help', 'commands', 'whoami', 'compact',
    ];

    /**
     * Map Discord slash command interaction to /command subcommand args format
     * consumed by CommandRouter.
     *
     * @param  array<int, mixed>  $options
     */
    private function mapKnownSlashCommand(string $commandName, array $options): ?string
    {
        if (! in_array($commandName, self::SLASH_COMMAND_NAMES, true)) {
            return null;
        }

        $subcommand = $this->extractFirstSubcommand($options);

        if ($subcommand !== null) {
            $subName = strtolower(trim($subcommand['name']));
            $subOpts = $subcommand['options'];

            return '/'.$this->buildSlashCommandLine($commandName, $subName, $subOpts);
        }

        return '/'.$this->buildSlashCommandLine($commandName, '', $options);
    }

    /**
     * Build "command [subcommand] arg1 arg2 ..." for CommandRouter.
     *
     * @param  array<int, mixed>  $options
     */
    private function buildSlashCommandLine(string $commandName, string $subcommandName, array $options): string
    {
        $parts = [$commandName];
        if ($subcommandName !== '') {
            $parts[] = $subcommandName;
        }

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            if (! array_key_exists('value', $option)) {
                continue;
            }
            $value = $option['value'];
            if (! is_scalar($value)) {
                continue;
            }
            $parts[] = (string) $value;
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, mixed>  $options
     * @return array{name: string, options: array<int, mixed>}|null
     */
    private function extractFirstSubcommand(array $options): ?array
    {
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if (! $this->isSubcommandOption($option['type'] ?? null)) {
                continue;
            }

            $name = $option['name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $nestedOptions = is_array($option['options'] ?? null) ? $option['options'] : [];

            return [
                'name' => $name,
                'options' => $nestedOptions,
            ];
        }

        return null;
    }

    private function isSubcommandOption(mixed $type): bool
    {
        return (string) $type === '1';
    }

    /**
     * @param  array<int, mixed>  $options
     */
    private function extractOptionValue(array $options, string $optionName): ?string
    {
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $name = $option['name'] ?? null;
            if (! is_string($name) || strtolower(trim($name)) !== strtolower($optionName)) {
                continue;
            }

            if (! array_key_exists('value', $option)) {
                continue;
            }

            $value = $option['value'];
            if (is_scalar($value)) {
                return (string) $value;
            }

            return null;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $options
     * @param  array<int, string>  $parts
     */
    private function appendInteractionOptionParts(array $options, array &$parts): void
    {
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $name = $option['name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            if (array_key_exists('value', $option) && is_scalar($option['value'])) {
                $parts[] = sprintf('%s: %s', $name, (string) $option['value']);
            } else {
                $parts[] = $name;
            }

            if (is_array($option['options'] ?? null)) {
                $this->appendInteractionOptionParts($option['options'], $parts);
            }
        }
    }

    /**
     * Extract attachments from Discord attachment data.
     *
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, NormalizedAttachment>
     */
    private function extractAttachments(array $attachments): array
    {
        $normalized = [];

        foreach ($attachments as $attachment) {
            $normalized[] = new NormalizedAttachment(
                providerFileId: $attachment['id'] ?? '',
                filename: $attachment['filename'] ?? 'unknown',
                mimeType: $attachment['content_type'] ?? 'application/octet-stream',
                sizeBytes: $attachment['size'] ?? 0,
                downloadUrl: $attachment['url'] ?? null,
            );
        }

        return $normalized;
    }

    /**
     * Extract attachments from Discord interaction resolved data.
     *
     * Discord interactions store file attachments at data.resolved.attachments
     * as a keyed map (attachment_id => attachment_object), and also reference
     * them via option values in data.data.options.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, NormalizedAttachment>
     */
    private function extractResolvedAttachments(array $data): array
    {
        $resolved = $data['data']['resolved']['attachments'] ?? null;

        if (! is_array($resolved) || $resolved === []) {
            return [];
        }

        $normalized = [];

        foreach ($resolved as $id => $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $normalized[] = new NormalizedAttachment(
                providerFileId: (string) ($attachment['id'] ?? $id),
                filename: $attachment['filename'] ?? 'unknown',
                mimeType: $attachment['content_type'] ?? 'application/octet-stream',
                sizeBytes: $attachment['size'] ?? 0,
                downloadUrl: $attachment['url'] ?? null,
            );
        }

        return $normalized;
    }

    /**
     * Store the last bot message ID in cache.
     *
     * Cached for 24 hours to support DM edit-based pseudo-threading.
     */
    private function storeLastBotMessageId(ChatSession $session, string $messageId): void
    {
        $cacheKey = $this->getLastBotMessageCacheKey($session);
        Cache::put($cacheKey, $messageId, 86400); // 24 hours
    }

    /**
     * Get the last bot message ID from cache.
     */
    private function getLastBotMessageId(ChatSession $session): ?string
    {
        return Cache::get($this->getLastBotMessageCacheKey($session));
    }

    /**
     * Get the cache key for storing the last bot message ID.
     */
    private function getLastBotMessageCacheKey(ChatSession $session): string
    {
        return sprintf('discord:last_bot_message:%s:%s', $session->connector_account_id, $session->channel_id);
    }

    /**
     * Send message via channel webhook with user attribution (username/avatar).
     * Returns ProviderResponse on success/failure, or null to fall through to bot send.
     */
    private function sendViaWebhookAsUser(
        ChatSession $session,
        ConnectorAccount $account,
        OutboundPayload $payload,
        string $botToken
    ): ?ProviderResponse {
        $channelId = $session->channel_id ?? $payload->channelId;
        if (! $channelId) {
            return ProviderResponse::failure('No channel for webhook send');
        }

        $webhook = $this->getOrCreateChannelWebhook($account, $channelId, $botToken);
        if ($webhook === null) {
            $this->logInfo('Webhook unavailable (e.g. DM channel), falling back to bot send', [
                'account_id' => $account->id,
                'channel_id' => $channelId,
            ]);

            return null;
        }

        $normalizedContent = $this->normalizeContent($payload->content);
        $chunks = $this->chunkContent($normalizedContent);
        $url = sprintf('%s/webhooks/%s/%s', self::API_BASE_URL, $webhook['id'], $webhook['token']);
        $query = ['wait' => 'true'];
        if ($payload->threadId !== null) {
            $query['thread_id'] = $payload->threadId;
        }
        $url .= '?'.http_build_query($query);

        $lastMessageId = null;
        $lastResponseData = [];

        foreach ($chunks as $chunk) {
            $body = [
                'content' => $chunk,
                'username' => substr($payload->senderUsername ?? 'User', 0, 80),
            ];
            if ($payload->senderAvatarUrl !== null && $payload->senderAvatarUrl !== '') {
                $body['avatar_url'] = $payload->senderAvatarUrl;
            }

            $response = Http::timeout(15)
                ->post($url, $body);

            if (! $response->successful()) {
                $this->logError('Discord webhook send failed', [
                    'account_id' => $account->id,
                    'channel_id' => $channelId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $errorMessage = $response->json('message') ?? 'Webhook send failed';

                return ProviderResponse::failure($errorMessage, $response->json() ?? []);
            }

            $lastResponseData = $response->json();
            $lastMessageId = $lastResponseData['id'] ?? null;
        }

        if (! $lastMessageId) {
            return ProviderResponse::failure('No message ID from webhook', $lastResponseData);
        }

        $this->logInfo('User-attributed message sent via webhook', [
            'account_id' => $account->id,
            'channel_id' => $channelId,
            'message_id' => $lastMessageId,
        ]);

        return ProviderResponse::success($lastMessageId, $lastResponseData);
    }

    /**
     * Get or create a channel webhook. Caches webhook id/token per account+channel.
     *
     * @return array{id: string, token: string}|null
     */
    private function getOrCreateChannelWebhook(
        ConnectorAccount $account,
        string $channelId,
        string $botToken
    ): ?array {
        $cacheKey = sprintf('discord:channel_webhook:%s:%s', $account->id, $channelId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['id'], $cached['token'])) {
            return ['id' => $cached['id'], 'token' => $cached['token']];
        }

        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Content-Type' => 'application/json',
            ])
            ->post(
                self::API_BASE_URL.'/channels/'.$channelId.'/webhooks',
                ['name' => 'Agent Ops Chat']
            );

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $id = $data['id'] ?? null;
        $token = $data['token'] ?? null;
        if (! $id || ! $token) {
            return null;
        }

        Cache::put($cacheKey, ['id' => $id, 'token' => $token], self::CHANNEL_WEBHOOK_CACHE_TTL_SECONDS);

        return ['id' => $id, 'token' => $token];
    }

    /**
     * Get the public key for Ed25519 verification.
     */
    private function getPublicKey(ConnectorAccount $account): ?string
    {
        return $account->credentials['public_key'] ?? null;
    }

    /**
     * Get the bot token for API calls.
     */
    private function getBotToken(ConnectorAccount $account): ?string
    {
        return $account->credentials['bot_token'] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function storeInteractionContext(?ConnectorAccount $account, ?array $data): void
    {
        if ($account === null || $data === null) {
            return;
        }

        $interactionId = (string) ($data['id'] ?? '');
        $interactionToken = (string) ($data['token'] ?? '');
        $applicationId = trim((string) ($account->credentials['application_id'] ?? ''));

        if ($interactionId === '' || $interactionToken === '' || $applicationId === '') {
            return;
        }

        Cache::put(
            $this->interactionContextCacheKey($account, $interactionId),
            [
                'token' => $interactionToken,
                'application_id' => $applicationId,
            ],
            self::INTERACTION_CONTEXT_TTL_SECONDS
        );
    }

    /**
     * @return array{token:string,application_id:string}|null
     */
    private function pullInteractionContextForSession(ChatSession $session, ConnectorAccount $account): ?array
    {
        $latestInbound = $session->messages()
            ->where('direction', ChatMessage::DIRECTION_INBOUND)
            ->latest('created_at')
            ->first();

        if ($latestInbound === null) {
            return null;
        }

        $interactionId = trim((string) ($latestInbound->provider_event_id ?? ''));
        if ($interactionId === '') {
            return null;
        }

        $context = Cache::pull($this->interactionContextCacheKey($account, $interactionId));
        if (! is_array($context)) {
            return null;
        }

        $token = trim((string) ($context['token'] ?? ''));
        $applicationId = trim((string) ($context['application_id'] ?? ''));

        if ($token === '' || $applicationId === '') {
            return null;
        }

        return [
            'token' => $token,
            'application_id' => $applicationId,
        ];
    }

    private function interactionContextCacheKey(ConnectorAccount $account, string $interactionId): string
    {
        return sprintf('discord:interaction:%s:%s', $account->id, $interactionId);
    }

    /**
     * Send overflow chunks as follow-up messages (used when an interaction edit holds only the first chunk).
     *
     * @param  list<string>  $chunks
     */
    private function sendOverflowChunks(ChatSession $session, ConnectorAccount $account, string $botToken, array $chunks): void
    {
        $targetChannelId = $session->thread_id ?? $session->channel_id;
        $httpClient = new MessengerHttpClient($account);

        foreach ($chunks as $chunk) {
            $result = $httpClient->post(
                self::API_BASE_URL.'/channels/'.$targetChannelId.'/messages',
                ['content' => $chunk],
                [
                    'Authorization' => 'Bot '.$botToken,
                    'Content-Type' => 'application/json',
                ]
            );

            if (! $result['success']) {
                $this->logError('Failed to send overflow Discord chunk', [
                    'account_id' => $account->id,
                    'channel_id' => $targetChannelId,
                    'error' => $result['error'],
                ]);

                return;
            }

            $lastResponseData = $result['response']->json();
            $lastMessageId = $lastResponseData['id'] ?? null;
            if ($lastMessageId !== null) {
                $this->storeLastBotMessageId($session, $lastMessageId);
            }
        }
    }

    /**
     * Split content into chunks that fit within Discord's message limit.
     * Splits on double-newlines or single newlines when possible to avoid breaking mid-sentence.
     *
     * @return list<string>
     */
    private function chunkContent(string $content): array
    {
        if (mb_strlen($content) <= self::MAX_MESSAGE_LENGTH) {
            return [$content];
        }

        $chunks = [];
        $remaining = $content;

        while (mb_strlen($remaining) > self::MAX_MESSAGE_LENGTH) {
            $slice = mb_substr($remaining, 0, self::MAX_MESSAGE_LENGTH);

            $breakAt = mb_strrpos($slice, "\n\n");
            if ($breakAt === false || $breakAt < self::MAX_MESSAGE_LENGTH / 2) {
                $breakAt = mb_strrpos($slice, "\n");
            }
            if ($breakAt === false || $breakAt < self::MAX_MESSAGE_LENGTH / 4) {
                $breakAt = mb_strrpos($slice, ' ');
            }
            if ($breakAt === false || $breakAt < self::MAX_MESSAGE_LENGTH / 4) {
                $breakAt = self::MAX_MESSAGE_LENGTH;
            }

            $chunks[] = trim(mb_substr($remaining, 0, $breakAt));
            $remaining = trim(mb_substr($remaining, $breakAt));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }
}
