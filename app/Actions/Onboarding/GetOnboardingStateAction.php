<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\ConnectorAccount;

class GetOnboardingStateAction
{
    /**
     * @return array{has_connectors: bool, has_connected_channel: bool}
     */
    public function execute(): array
    {
        $connectorCount = ConnectorAccount::count();
        $connectedCount = ConnectorAccount::where('status', ConnectorAccount::STATUS_CONNECTED)->count();

        return [
            'has_connectors' => $connectorCount > 0,
            'has_connected_channel' => $connectedCount > 0,
        ];
    }
}
