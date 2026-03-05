<?php

namespace App\Services\Messenger;

/**
 * Result object for slash command registration operations.
 *
 * @property bool $success
 * @property string|null $error
 */
readonly class RegistrationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $commands
     */
    private function __construct(
        private bool $successful,
        private string $message,
        private int $commandCount = 0,
        private array $commands = []
    ) {}

    /**
     * @param  array<int, array<string, mixed>>|null  $commands  Registered commands from Discord API (optional)
     */
    public static function success(string $message, int $commandCount = 0, ?array $commands = null): self
    {
        $count = $commands !== null ? count($commands) : $commandCount;

        return new self(true, $message, $count, $commands ?? []);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message, 0, []);
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

    /**
     * @return array<int, string>
     */
    public function getCommandNames(): array
    {
        return array_map(
            fn (array $command): string => (string) ($command['name'] ?? ''),
            $this->commands
        );
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'success' => $this->successful,
            'error' => $this->successful ? null : $this->message,
            default => throw new \InvalidArgumentException("Unknown property: {$name}"),
        };
    }
}
