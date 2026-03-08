<?php

declare(strict_types=1);

namespace App\DTOs\Security;

readonly class SanitizationResult
{
    /**
     * @param  array<string>  $strippedElements
     */
    public function __construct(
        public string $content,
        public bool $sanitized,
        public bool $truncated,
        public int $originalTokenCount,
        public array $strippedElements = [],
    ) {}
}
