<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Models\DelegateeProfile;

/**
 * Result of a delegatee assignment operation.
 *
 * Contains the selected profile and the reasoning behind the assignment
 * decision for audit logging and debugging purposes.
 */
class AssignmentResult
{
    /**
     * @param  DelegateeProfile  $profile  The selected delegatee profile
     * @param  array<string, mixed>  $reasoning  Key-value pairs explaining the assignment decision
     */
    public function __construct(
        public readonly DelegateeProfile $profile,
        public readonly array $reasoning
    ) {}
}
