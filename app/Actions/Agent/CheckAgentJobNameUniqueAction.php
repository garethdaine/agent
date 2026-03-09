<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\User;

class CheckAgentJobNameUniqueAction
{
    public function execute(User $user, string $name, ?int $excludeId = null): bool
    {
        $query = $user->agentJobs()
            ->whereNull('deleted_at')
            ->where('name', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
