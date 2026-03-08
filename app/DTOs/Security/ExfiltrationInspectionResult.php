<?php

declare(strict_types=1);

namespace App\DTOs\Security;

readonly class ExfiltrationInspectionResult
{
    /**
     * @param  array<\App\Enums\Security\ExfiltrationPattern>  $matchedPatterns
     */
    public function __construct(
        public bool $blocked,
        public array $matchedPatterns,
        public ?string $reason,
    ) {}
}
