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

    'types' => [
        'login' => [
            'label' => 'Website Login',
            'secret_fields' => ['username', 'password', 'totp_secret'],
            'metadata_fields' => ['login_url', 'notes_hint'],
        ],
        'api_key' => [
            'label' => 'API Key',
            'secret_fields' => ['api_key', 'api_secret', 'bearer_token'],
            'metadata_fields' => ['base_url', 'header_name', 'secret_header_name', 'auth_method'],
        ],
        'ssh_key' => [
            'label' => 'SSH Key',
            'secret_fields' => ['private_key', 'passphrase'],
            'metadata_fields' => ['public_key', 'key_type', 'host', 'port', 'fingerprint'],
        ],
        'oauth_token' => [
            'label' => 'OAuth Token',
            'secret_fields' => ['access_token', 'refresh_token', 'client_id', 'client_secret'],
            'metadata_fields' => ['token_url', 'scopes', 'token_type'],
        ],
    ],

    'approval_modes' => ['autonomous', 'supervised', 'restricted'],

    'trust_thresholds' => [
        'autonomous' => 0.0,
        'supervised' => 0.7,
        'restricted' => 1.1,
    ],
];
