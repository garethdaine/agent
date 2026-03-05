<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

/**
 * Result DTO for slash command execution.
 *
 * Represents the outcome of a slash command handler execution,
 * including success status, message, and optional data.
 */
final readonly class CommandResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
    ) {}

    /**
     * Create a successful command result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function success(string $message, array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
        );
    }

    /**
     * Create a failed command result.
     */
    public static function failure(string $message): self
    {
        return new self(
            success: false,
            message: $message,
        );
    }

    /**
     * Convert to array for storage/serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
