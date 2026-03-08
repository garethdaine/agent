<?php

declare(strict_types=1);

namespace App\Support\Connectors\Exceptions;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message = '',
    ) {
        parent::__construct($message ?: "Rate limit exceeded. Retry after {$retryAfterSeconds} seconds.");
    }
}
