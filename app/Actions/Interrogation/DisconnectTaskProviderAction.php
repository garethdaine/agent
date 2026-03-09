<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;

class DisconnectTaskProviderAction
{
    public function execute(InterrogationSession $session, string $driver): int
    {
        return $session->providerIntegrations()
            ->where('category', 'task_management')
            ->where('driver', strtolower(trim($driver)))
            ->delete();
    }
}
