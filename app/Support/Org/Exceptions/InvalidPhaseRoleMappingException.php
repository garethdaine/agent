<?php

namespace App\Support\Org\Exceptions;

use RuntimeException;

class InvalidPhaseRoleMappingException extends RuntimeException
{
    public function __construct(
        string $message = 'Invalid phase role mapping',
        public readonly ?string $phaseId = null,
        public readonly ?string $roleSlug = null,
    ) {
        parent::__construct($message);
    }
}
