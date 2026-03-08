<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AgentConnectorConnection;
use App\Models\User;

class ConnectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, AgentConnectorConnection $connection): bool
    {
        return $user->belongsToTeam($connection->team);
    }

    public function manage(User $user, AgentConnectorConnection $connection): bool
    {
        return $user->ownsTeam($connection->team)
            || $user->hasTeamRole($connection->team, 'admin');
    }
}
