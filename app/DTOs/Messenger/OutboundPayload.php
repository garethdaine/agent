<?php

namespace App\DTOs\Messenger;

final readonly class OutboundPayload
{
    /**
     * @param  array<int, string>  $attachmentIds
     * @param  array<int, array<string, mixed>>|null  $components  Discord action rows / buttons
     * @param  string|null  $senderUsername  When set, send via webhook as this user (e.g. Chat History)
     * @param  string|null  $senderAvatarUrl  Avatar URL for webhook sender
     */
    public function __construct(
        public string $content,
        public string $channelId,
        public ?string $threadId = null,
        public ?string $replyToMessageId = null,
        public array $attachmentIds = [],
        public ?array $components = null,
        public ?string $senderUsername = null,
        public ?string $senderAvatarUrl = null,
    ) {}
}
