<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

final readonly class PolicyValidationResult
{
    /**
     * @param  array<string>  $violations
     */
    public function __construct(
        public bool $allowed,
        public string $reason = '',
        public array $violations = [],
    ) {}

    /**
     * Create an allowed result.
     */
    public static function allowed(): self
    {
        return new self(allowed: true);
    }

    /**
     * Create a denied result.
     *
     * @param  array<string>  $violations
     */
    public static function denied(string $reason, array $violations = []): self
    {
        return new self(
            allowed: false,
            reason: $reason,
            violations: $violations,
        );
    }
}
