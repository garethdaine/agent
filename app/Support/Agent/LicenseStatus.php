<?php

declare(strict_types=1);

namespace App\Support\Agent;

readonly class LicenseStatus
{
    public function __construct(
        public bool $valid,
        public string $plan = '',
        public ?string $expiresAt = null,
        public array $features = [],
        public ?string $error = null,
    ) {}

    public static function valid(string $plan = 'standard', ?string $expiresAt = null, array $features = []): self
    {
        return new self(
            valid: true,
            plan: $plan,
            expiresAt: $expiresAt,
            features: $features,
        );
    }

    public static function invalid(string $error = 'License is not valid'): self
    {
        return new self(valid: false, error: $error);
    }

    public static function bypassed(): self
    {
        return new self(valid: true, plan: 'development', error: null);
    }
}
