<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnectorWebhookEvent;

class CreateWebhookEventAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): AgentConnectorWebhookEvent
    {
        return AgentConnectorWebhookEvent::create($attributes);
    }
}
