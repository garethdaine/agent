<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

use DateTimeInterface;

final readonly class NormalizedMessage
{
    /**
     * @param  array<int, string>  $attachmentIds
     * @param  array<int, NormalizedAttachment>  $attachments
     */
    public function __construct(
        public string $providerUserId,
        public string $channelId,
        public string $content,
        public ?string $threadId = null,
        public ?string $providerMessageId = null,
        public ?string $providerEventId = null,
        public ?DateTimeInterface $providerTimestamp = null,
        public array $attachmentIds = [],
        public array $attachments = [],
    ) {}
}
