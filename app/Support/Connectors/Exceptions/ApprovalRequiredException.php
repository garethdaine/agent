<?php

declare(strict_types=1);

namespace App\Support\Connectors\Exceptions;

use App\Models\AgentConnectorApproval;
use RuntimeException;

class ApprovalRequiredException extends RuntimeException
{
    public function __construct(
        public readonly AgentConnectorApproval $approval,
    ) {
        parent::__construct("Action requires approval. Approval ID: {$approval->id}");
    }
}
