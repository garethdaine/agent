<?php

declare(strict_types=1);

namespace App\Support\Org\Exceptions;

use RuntimeException;

class InvalidDelegateeBindingException extends RuntimeException
{
    public function __construct(
        string $message = 'Delegatee profile must belong to the user',
    ) {
        parent::__construct($message);
    }
}
