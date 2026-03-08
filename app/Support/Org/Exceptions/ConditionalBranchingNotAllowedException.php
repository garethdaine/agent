<?php

declare(strict_types=1);

namespace App\Support\Org\Exceptions;

use RuntimeException;

class ConditionalBranchingNotAllowedException extends RuntimeException
{
    public function __construct(
        string $message = 'Conditional branching is not allowed in phase graphs',
        public readonly ?string $phaseId = null,
        public readonly ?string $conditionalKey = null,
    ) {
        parent::__construct($message);
    }
}
