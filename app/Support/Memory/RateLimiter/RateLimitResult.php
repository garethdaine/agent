<?php

declare(strict_types=1);

namespace App\Support\Memory\RateLimiter;

/**
 * Result of a rate limit attempt.
 *
 * Immutable value object containing:
 * - allowed: Whether the request should proceed
 * - remaining: Number of requests remaining in the window
 * - retryAfter: Seconds until retry is allowed (null if allowed)
 */
readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $remaining,
        public ?int $retryAfter = null,
    ) {}

    /**
     * Create a result indicating the request is allowed.
     */
    public static function allowed(int $remaining): self
    {
        return new self(
            allowed: true,
            remaining: $remaining,
            retryAfter: null
        );
    }

    /**
     * Create a result indicating the request is blocked.
     */
    public static function blocked(int $retryAfter): self
    {
        return new self(
            allowed: false,
            remaining: 0,
            retryAfter: $retryAfter
        );
    }
}
