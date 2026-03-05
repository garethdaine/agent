<?php

namespace App\DTOs\Runtime;

readonly class ToolResult
{
    public function __construct(
        public bool $success,
        public mixed $data,
        public ?string $error,
        public int $durationMs,
        public array $artifacts = [],
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(mixed $data, int $durationMs, array $artifacts = []): self
    {
        return new self(
            success: true,
            data: $data,
            error: null,
            durationMs: $durationMs,
            artifacts: $artifacts,
        );
    }

    /**
     * Create a failure result.
     */
    public static function failure(string $error, int $durationMs): self
    {
        return new self(
            success: false,
            data: null,
            error: $error,
            durationMs: $durationMs,
            artifacts: [],
        );
    }
}
