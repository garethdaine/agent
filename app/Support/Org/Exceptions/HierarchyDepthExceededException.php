<?php

namespace App\Support\Org\Exceptions;

use RuntimeException;

class HierarchyDepthExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'Maximum hierarchy depth exceeded',
        public readonly int $maxDepth = 3,
    ) {
        parent::__construct($message);
    }
}
