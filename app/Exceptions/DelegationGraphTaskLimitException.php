<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a delegation graph exceeds the maximum allowed number of tasks.
 *
 * The limit is configured via config('delegation.max_tasks_per_graph').
 */
class DelegationGraphTaskLimitException extends RuntimeException
{
    public function __construct(
        string $message = 'Delegation graph exceeds maximum task limit',
        public readonly int $taskCount = 0,
        public readonly int $maxAllowed = 0,
    ) {
        parent::__construct($message);
    }

    public static function exceedsLimit(int $taskCount, int $maxAllowed): self
    {
        return new self(
            message: "Delegation graph contains {$taskCount} tasks, which exceeds the maximum allowed ({$maxAllowed})",
            taskCount: $taskCount,
            maxAllowed: $maxAllowed,
        );
    }
}
