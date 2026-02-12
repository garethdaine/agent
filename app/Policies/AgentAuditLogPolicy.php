<?php

namespace App\Policies;

use App\Models\AgentAuditLog;
use App\Models\User;

class AgentAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, AgentAuditLog $agentAuditLog): bool
    {
        return $agentAuditLog->user_id === $user->id;
    }
}
