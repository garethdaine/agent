<?php

declare(strict_types=1);

namespace App\Support\NlOrg\Exceptions;

use RuntimeException;

class NlOrgContextOverflowException extends RuntimeException
{
    public function __construct(
        public readonly int $estimatedTokens,
        public readonly int $budgetTokens,
    ) {
        parent::__construct(
            "Prompt exceeds context window budget ({$estimatedTokens} estimated tokens vs {$budgetTokens} budget). "
            .'Try using incremental mode with smaller, targeted commands instead of describing the entire org layer at once.'
        );
    }
}
