<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AgentAuditLog;
use App\Models\User;

class AgentAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function view(User $user, AgentAuditLog $agentAuditLog): bool
    {
        return $agentAuditLog->user_id === $user->id;
    }
}
