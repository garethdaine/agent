<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

/**
 * Result of webhook ingress probe validation.
 *
 * Contains probe status, error details, actionable diagnostics,
 * and optional warnings for non-blocking issues.
 */
readonly class ProbeResult
{
    /**
     * @param  bool  $success  Whether the probe passed
     * @param  string|null  $error  Brief error description
     * @param  string|null  $diagnostic  Actionable remediation guidance
     * @param  string|null  $warning  Non-blocking warning message
     * @param  array<string, mixed>  $metadata  Additional probe data
     */
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public ?string $diagnostic = null,
        public ?string $warning = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful probe result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function success(array $metadata = []): self
    {
        return new self(
            success: true,
            metadata: $metadata
        );
    }

    /**
     * Create a failed probe result with actionable diagnostic.
     */
    public static function failure(string $error, string $diagnostic): self
    {
        return new self(
            success: false,
            error: $error,
            diagnostic: $diagnostic
        );
    }

    /**
     * Create a successful result with a non-blocking warning.
     */
    public static function successWithWarning(string $warning, array $metadata = []): self
    {
        return new self(
            success: true,
            warning: $warning,
            metadata: $metadata
        );
    }

    /**
     * Check if the probe passed.
     */
    public function passed(): bool
    {
        return $this->success;
    }

    /**
     * Check if there's a warning to display.
     */
    public function hasWarning(): bool
    {
        return $this->warning !== null;
    }

    /**
     * Get metadata value by key.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
