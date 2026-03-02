<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

/**
 * Result of an authorization check.
 *
 * Uses a generic denial message to prevent resource enumeration attacks.
 */
readonly class AuthorizationResult
{
    private function __construct(
        private bool $authorized,
        private string $message = ''
    ) {}

    /**
     * Create an allowed authorization result.
     */
    public static function allowed(): self
    {
        return new self(true);
    }

    /**
     * Create a denied authorization result.
     *
     * @param  string  $message  Denial message (defaults to generic message)
     */
    public static function denied(string $message = 'Permission denied'): self
    {
        return new self(false, $message);
    }

    /**
     * Check if the action is authorized.
     */
    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    /**
     * Get the authorization message (empty for allowed, denial reason for denied).
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
