<?php

declare(strict_types=1);

namespace App\Services\Messenger;

final class ChannelPolicyResult
{
    private function __construct(
        public readonly string $outcome,
        public readonly ?string $reason = null,
    ) {}

    public static function allowed(): self
    {
        return new self('allowed');
    }

    public static function denied(string $reason): self
    {
        return new self('denied', $reason);
    }

    /** Message should be silently ignored (e.g. no @mention in require_mention mode) */
    public static function ignored(): self
    {
        return new self('ignored');
    }

    public function isAllowed(): bool
    {
        return $this->outcome === 'allowed';
    }

    public function isDenied(): bool
    {
        return $this->outcome === 'denied';
    }

    public function isIgnored(): bool
    {
        return $this->outcome === 'ignored';
    }
}
