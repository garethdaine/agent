<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a delegation graph contains a dependency cycle.
 *
 * A cycle in a task dependency graph means tasks cannot be executed in any
 * valid order (e.g., Task A depends on Task B, which depends on Task C,
 * which depends on Task A).
 */
class DelegationGraphCycleException extends RuntimeException
{
    /**
     * @param  string[]  $cyclePath  Optional list of task names forming the cycle
     */
    public function __construct(
        string $message = 'Delegation graph contains a dependency cycle',
        public readonly array $cyclePath = [],
    ) {
        parent::__construct($message);
    }
}
