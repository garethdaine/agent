<?php

declare(strict_types=1);

namespace App\Messenger\Discord;

/**
 * Result of slash command registration with Discord.
 *
 * Contains success/failure status, registered commands on success,
 * or actionable error message on failure.
 */
readonly class RegistrationResult
{
    /**
     * @param  bool  $success  Whether registration succeeded
     * @param  array<int, array<string, mixed>>  $commands  Registered command data from Discord
     * @param  string|null  $error  Error message on failure
     */
    public function __construct(
        public bool $success,
        public array $commands = [],
        public ?string $error = null,
    ) {}

    /**
     * Create a successful registration result.
     *
     * @param  array<int, array<string, mixed>>  $commands  Registered commands from Discord API
     */
    public static function success(array $commands): self
    {
        return new self(true, $commands, null);
    }

    /**
     * Create a failed registration result.
     *
     * @param  string  $error  Actionable error message
     */
    public static function failure(string $error): self
    {
        return new self(false, [], $error);
    }

    /**
     * Get command IDs from the registered commands.
     *
     * @return array<int, string>
     */
    public function getCommandIds(): array
    {
        return array_map(
            fn (array $command): string => (string) ($command['id'] ?? ''),
            $this->commands
        );
    }

    /**
     * Get command names from the registered commands.
     *
     * @return array<int, string>
     */
    public function getCommandNames(): array
    {
        return array_map(
            fn (array $command): string => (string) ($command['name'] ?? ''),
            $this->commands
        );
    }
}
