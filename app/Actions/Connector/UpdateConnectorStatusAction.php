<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class UpdateConnectorStatusAction
{
    /**
     * @param  array<string, mixed>  $additionalUpdates
     */
    public function execute(ConnectorAccount $connector, string $status, array $additionalUpdates = []): ConnectorAccount
    {
        $connector->update(array_merge(['status' => $status], $additionalUpdates));

        /** @var ConnectorAccount */
        return $connector->fresh();
    }
}
