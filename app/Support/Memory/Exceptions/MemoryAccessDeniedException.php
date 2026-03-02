<?php

declare(strict_types=1);

namespace App\Support\Memory\Exceptions;

use Exception;

/**
 * Thrown when memory access is denied due to permission restrictions.
 *
 * Used when:
 * - Agent attempts to modify identity blocks
 * - Agent attempts to downgrade classification
 * - User attempts to access another user's memory
 */
class MemoryAccessDeniedException extends Exception
{
    public function __construct(string $message = 'Memory access denied', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
