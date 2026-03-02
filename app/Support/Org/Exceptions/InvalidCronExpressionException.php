<?php

namespace App\Support\Org\Exceptions;

use RuntimeException;

class InvalidCronExpressionException extends RuntimeException
{
    public function __construct(
        string $message = 'Invalid cron expression',
        public readonly ?string $expression = null,
    ) {
        parent::__construct($message);
    }
}
