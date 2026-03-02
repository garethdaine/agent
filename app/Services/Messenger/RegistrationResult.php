<?php

namespace App\Services\Messenger;

/**
 * Result object for slash command registration operations.
 */
readonly class RegistrationResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private int $commandCount = 0
    ) {}

    public static function success(string $message, int $commandCount): self
    {
        return new self(true, $message, $commandCount);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCommandCount(): int
    {
        return $this->commandCount;
    }
}
