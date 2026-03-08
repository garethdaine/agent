<?php

declare(strict_types=1);

return [
    'driver' => env('RUNTIME_MODE_DRIVER', 'api'),

    'providers' => [
        'openai' => [
            'enabled' => (bool) env('RUNTIME_PROVIDER_OPENAI_ENABLED', true),
            'endpoint' => env('RUNTIME_PROVIDER_OPENAI_ENDPOINT'),
        ],
        'anthropic' => [
            'enabled' => (bool) env('RUNTIME_PROVIDER_ANTHROPIC_ENABLED', true),
            'endpoint' => env('RUNTIME_PROVIDER_ANTHROPIC_ENDPOINT'),
        ],
        'local' => [
            'enabled' => (bool) env('RUNTIME_PROVIDER_LOCAL_ENABLED', false),
            'endpoint' => env('RUNTIME_PROVIDER_LOCAL_ENDPOINT'),
        ],
    ],
];
