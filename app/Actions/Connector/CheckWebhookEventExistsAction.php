<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnectorWebhookEvent;

class CheckWebhookEventExistsAction
{
    public function execute(string $connectionId, string $externalEventId): bool
    {
        return AgentConnectorWebhookEvent::where('connection_id', $connectionId)
            ->where('external_event_id', $externalEventId)
            ->exists();
    }
}
