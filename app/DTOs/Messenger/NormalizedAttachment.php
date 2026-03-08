<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

final readonly class NormalizedAttachment
{
    public function __construct(
        public string $providerFileId,
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
        public ?string $downloadUrl = null,
    ) {}
}
