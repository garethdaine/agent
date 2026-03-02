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

        // Check if this is an interaction (webhook) or a Gateway event
        if (isset($data['type']) && is_numeric($data['type'])) {
            return $this->parseInteraction($data);
        }

        // Gateway event
        if (isset($data['t']) && isset($data['d'])) {
            return $this->parseGatewayEvent($data);
        }

        // Direct message data from Gateway (d field only)
        if (isset($data['d'])) {
            return $this->parseMessageData($data['d']);
        }

        // Fallback: assume it's message data directly
        return $this->parseMessageData($data);
    }

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

        // Determine target channel
        // If thread_id is provided, send to thread; otherwise send to main channel
        $targetChannelId = $payload->threadId ?? $payload->channelId;

        $httpClient = new MessengerHttpClient($account);

        $data = [
            'content' => $payload->content,
        ];

        // Add message reference for replies (optional threading)
        if ($payload->replyToMessageId) {
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
            ]);

            $errorMessage = $result['error'] ?? 'Failed to send message';
            $responseData = $result['response']?->json() ?? [];

            // Extract Discord error message if available
            if (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            }

            return ProviderResponse::failure($errorMessage, $responseData);
        }

        $responseData = $result['response']->json();
        $messageId = $responseData['id'] ?? null;

        if (! $messageId) {
            return ProviderResponse::failure('No message ID in response', $responseData);
        }

        // Store last bot message ID for DM pseudo-threading
        $this->storeLastBotMessageId($session, $messageId);

        $this->logInfo('Message sent successfully', [
            'account_id' => $account->id,
            'channel_id' => $targetChannelId,
            'message_id' => $messageId,
        ]);

        return ProviderResponse::success($messageId, $responseData);
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

        // Append new content with separator
        $newContent = $existingContent."\n\n---\n\n".$content;

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

        return new NormalizedMessage(
            providerUserId: $providerUserId,
            channelId: $channelId,
            content: $content,
            threadId: $data['channel']['parent_id'] ?? null,
            providerMessageId: $data['id'] ?? null,
            providerEventId: $data['id'] ?? null,
            providerTimestamp: null,
            attachments: [],
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

    /**
     * Map known Discord slash commands to parser-friendly text commands.
     *
     * @param  array<int, mixed>  $options
     */
    private function mapKnownSlashCommand(string $commandName, array $options): ?string
    {
        if ($commandName === 'agent') {
            $legacyCommand = $this->extractOptionValue($options, 'command')
                ?? $this->extractOptionValue($options, 'text')
                ?? $this->extractOptionValue($options, 'query');

            if ($legacyCommand !== null && trim($legacyCommand) !== '') {
                return trim($legacyCommand);
            }
        }

        $subcommand = $this->extractFirstSubcommand($options);
        if ($subcommand !== null) {
            $subcommandName = strtolower(trim($subcommand['name']));
            $subcommandOptions = $subcommand['options'];

            if ($commandName === 'jobs') {
                if ($subcommandName === 'list') {
                    return 'list my jobs';
                }

                if ($subcommandName === 'run') {
                    $jobId = $this->extractOptionValue($subcommandOptions, 'job_id');

                    return $jobId !== null
                        ? sprintf('run job %s now', $jobId)
                        : null;
                }
            }

            if ($commandName === 'runs') {
                if ($subcommandName === 'active') {
                    return 'show active runs';
                }

                if ($subcommandName === 'stop') {
                    $runId = $this->extractOptionValue($subcommandOptions, 'run_id');

                    return $runId !== null
                        ? sprintf('stop run %s', $runId)
                        : null;
                }
            }
        }

        if ($commandName === 'jobs') {
            $legacyAction = strtolower(trim((string) ($this->extractOptionValue($options, 'command')
                ?? $this->extractOptionValue($options, 'action')
                ?? '')));

            if (in_array($legacyAction, ['list', 'ls', 'show'], true)) {
                return 'list my jobs';
            }
        }

        if ($commandName === 'runs') {
            $legacyAction = strtolower(trim((string) ($this->extractOptionValue($options, 'command')
                ?? $this->extractOptionValue($options, 'action')
                ?? '')));

            if (in_array($legacyAction, ['active', 'list', 'show'], true)) {
                return 'show active runs';
            }

            if ($legacyAction === 'stop') {
                $runId = $this->extractOptionValue($options, 'run_id');

                return $runId !== null
                    ? sprintf('stop run %s', $runId)
                    : null;
            }
        }

        return null;
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
}
