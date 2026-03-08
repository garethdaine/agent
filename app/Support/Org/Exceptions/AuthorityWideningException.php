<?php

declare(strict_types=1);

namespace App\Support\Org\Exceptions;

use RuntimeException;

class AuthorityWideningException extends RuntimeException
{
    public function __construct(
        string $message = 'Cannot request capabilities not present in delegatee profile',
        public readonly array $wideningCapabilities = [],
    ) {
        parent::__construct($message);
    }
}
