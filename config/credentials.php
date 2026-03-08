<?php

declare(strict_types=1);

return [
    'providers' => [
        'openai' => [
            'keys' => ['api_key'],
            'label' => 'OpenAI',
        ],
        'anthropic' => [
            'keys' => ['api_key'],
            'label' => 'Anthropic',
        ],
        'github' => [
            'keys' => ['token'],
            'label' => 'GitHub',
        ],
    ],

    'vault' => [
        'table' => 'credential_vault',
    ],
];
