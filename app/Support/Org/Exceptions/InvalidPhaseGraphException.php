<?php

declare(strict_types=1);

namespace App\Support\Org\Exceptions;

use RuntimeException;

class InvalidPhaseGraphException extends RuntimeException
{
    public function __construct(
        string $message = 'Invalid phase graph structure',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
