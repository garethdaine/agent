<?php

namespace App\DTOs\Messenger;

final readonly class OutboundPayload
{
    /**
     * @param  array<int, string>  $attachmentIds
     */
    public function __construct(
        public string $content,
        public string $channelId,
        public ?string $threadId = null,
        public ?string $replyToMessageId = null,
        public array $attachmentIds = [],
    ) {}
}
