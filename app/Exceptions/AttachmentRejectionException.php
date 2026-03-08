<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AttachmentRejectionException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $filename = null
    ) {
        $message = $filename
            ? "Attachment '{$filename}' rejected: {$reason}"
            : "Attachment rejected: {$reason}";

        parent::__construct($message);
    }

    public static function oversized(string $filename, int $size, int $maxSize): self
    {
        $sizeMb = round($size / 1024 / 1024, 2);
        $maxMb = round($maxSize / 1024 / 1024, 2);

        return new self("File size ({$sizeMb}MB) exceeds maximum ({$maxMb}MB)", $filename);
    }

    public static function disallowedType(string $filename, string $mimeType): self
    {
        return new self("MIME type '{$mimeType}' is not allowed", $filename);
    }
}
