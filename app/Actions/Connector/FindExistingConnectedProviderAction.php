<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectedProvider;
use App\Models\InterrogationSession;

class FindExistingConnectedProviderAction
{
    public function execute(int $sessionId, string $driverKey): ?ConnectedProvider
    {
        return ConnectedProvider::query()
            ->where('providerable_type', InterrogationSession::class)
            ->where('providerable_id', $sessionId)
            ->where('category', 'task_management')
            ->where('driver', $driverKey)
            ->first();
    }
}
