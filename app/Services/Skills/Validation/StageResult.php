<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

final readonly class StageResult
{
    public const SEVERITY_BLOCK = 'block';

    public const SEVERITY_WARN = 'warn';

    public const SEVERITY_INFO = 'info';

    /**
     * @param  array<int, array{check: string, severity: string, message: string}>  $failures
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $passed,
        public string $name,
        public int $checks,
        public array $failures = [],
        public array $warnings = [],
        public array $details = [],
    ) {}

    public function hasBlockingFailures(): bool
    {
        foreach ($this->failures as $failure) {
            if ($failure['severity'] === self::SEVERITY_BLOCK) {
                return true;
            }
        }

        return false;
    }

    public static function pass(string $name, int $checks = 1, array $details = []): self
    {
        return new self(
            passed: true,
            name: $name,
            checks: $checks,
            details: $details,
        );
    }

    public static function fail(string $name, array $failures, int $checks = 1, array $warnings = [], array $details = []): self
    {
        return new self(
            passed: false,
            name: $name,
            checks: $checks,
            failures: $failures,
            warnings: $warnings,
            details: $details,
        );
    }

    public static function warn(string $name, array $warnings, int $checks = 1, array $details = []): self
    {
        return new self(
            passed: true,
            name: $name,
            checks: $checks,
            warnings: $warnings,
            details: $details,
        );
    }
}
