<?php

namespace App\Support\Org\Exceptions;

use RuntimeException;

class CycleDetectedException extends RuntimeException
{
    public function __construct(
        string $message = 'Setting this manager would create a cycle in the reporting hierarchy',
    ) {
        parent::__construct($message);
    }
}
