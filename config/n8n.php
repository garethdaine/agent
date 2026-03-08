<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('N8N_INTEGRATION_ENABLED', false),

    'webhook_secret' => env('N8N_WEBHOOK_SECRET'),

    'inbound_path' => env('N8N_INBOUND_PATH', 'agent/api/v1/n8n/webhook'),
];
