<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Mode
    |--------------------------------------------------------------------------
    |
    | This option controls the default connection mode for messenger adapters.
    | Local mode uses long polling or websockets where available. Webhook mode
    | requires publicly accessible endpoints.
    |
    */

    'default_mode' => env('MESSENGER_DEFAULT_MODE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Registered Adapters
    |--------------------------------------------------------------------------
    |
    | This array maps provider names to their adapter implementations.
    | Adapters are registered with the ConnectorManager on boot.
    |
    */

    'adapters' => [
        'slack' => \App\Support\Messenger\Adapters\SlackAdapter::class,
        'telegram' => \App\Support\Messenger\Adapters\TelegramAdapter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for each messenger provider including timeouts, rate limits,
    | signature verification schemes, and feature flags.
    |
    */

    'providers' => [

        'slack' => [
            'signature_verification' => [
                'scheme' => 'hmac_sha256',
            ],
            'replay_protection' => [
                'strategy' => 'timestamp',
                'window_seconds' => 300,
            ],
            'rate_limit' => [
                'requests_per_second' => 1,
                'burst_limit' => 5,
                'backoff_base_seconds' => 1,
                'backoff_multiplier' => 2,
                'backoff_max_seconds' => 300,
                'jitter_percent' => 20,
                'circuit_breaker_threshold' => 10,
                'circuit_breaker_cooldown_seconds' => 60,
            ],
            'threading_mode' => 'native',
            'threading_fallback' => 'edit',
            'session_history_limit' => 20,
            'default_verbosity' => 'summary',
        ],

        'telegram' => [
            'signature_verification' => [
                'scheme' => 'token',
            ],
            'replay_protection' => [
                'strategy' => 'event_id',
                'dedupe_ttl_seconds' => 3600,
            ],
            'rate_limit' => [
                'requests_per_second' => 1,
                'burst_limit' => 5,
                'backoff_base_seconds' => 1,
                'backoff_multiplier' => 2,
                'backoff_max_seconds' => 300,
                'jitter_percent' => 20,
                'circuit_breaker_threshold' => 10,
                'circuit_breaker_cooldown_seconds' => 60,
            ],
            'threading_mode' => 'reply_to',
            'threading_fallback' => 'quote',
            'session_history_limit' => 20,
            'default_verbosity' => 'summary',
        ],

        'discord' => [
            'signature_verification' => [
                'scheme' => 'ed25519',
            ],
            'replay_protection' => [
                'strategy' => 'timestamp',
                'window_seconds' => 300,
            ],
            'rate_limit' => [
                'requests_per_second' => 1,
                'burst_limit' => 5,
                'backoff_base_seconds' => 1,
                'backoff_multiplier' => 2,
                'backoff_max_seconds' => 300,
                'jitter_percent' => 20,
                'circuit_breaker_threshold' => 10,
                'circuit_breaker_cooldown_seconds' => 60,
            ],
            'threading_mode' => 'native',
            'threading_fallback' => 'edit',
            'session_history_limit' => 20,
            'default_verbosity' => 'summary',
        ],

        'whatsapp' => [
            'signature_verification' => [
                'scheme' => 'hmac_sha256',
            ],
            'replay_protection' => [
                'strategy' => 'event_id',
                'dedupe_ttl_seconds' => 3600,
            ],
            'rate_limit' => [
                'requests_per_second' => 1,
                'burst_limit' => 5,
                'backoff_base_seconds' => 1,
                'backoff_multiplier' => 2,
                'backoff_max_seconds' => 300,
                'jitter_percent' => 20,
                'circuit_breaker_threshold' => 10,
                'circuit_breaker_cooldown_seconds' => 60,
            ],
            'threading_mode' => 'quote',
            'threading_fallback' => 'single',
            'session_history_limit' => 20,
            'default_verbosity' => 'summary',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for handling file attachments in chat messages.
    |
    */

    'attachment_config' => [
        'max_file_size_mb' => env('MESSENGER_MAX_FILE_SIZE_MB', 10),
        'allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
        'malware_scan_enabled' => env('MESSENGER_MALWARE_SCAN', false),
        'retention_days' => env('MESSENGER_ATTACHMENT_RETENTION_DAYS', 30),
        'storage_disk' => env('MESSENGER_ATTACHMENT_DISK', 'local'),
        's3_encryption' => 'AES256',
        'signed_url_ttl_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments (Alias for attachment_config)
    |--------------------------------------------------------------------------
    |
    | Simplified access to attachment settings.
    |
    */

    'attachments' => [
        'max_size' => env('MESSENGER_MAX_FILE_SIZE_MB', 10) * 1024 * 1024,
        'allowed_types' => ['image/*', 'application/pdf', 'text/*'],
        'scan_enabled' => env('MESSENGER_SCAN_ATTACHMENTS', false),
        'retention_days' => env('MESSENGER_ATTACHMENT_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Attachments (Legacy Key)
    |--------------------------------------------------------------------------
    |
    | Whether to scan attachments for malware using ClamAV.
    |
    */

    'scan_attachments' => env('MESSENGER_SCAN_ATTACHMENTS', false),

    /*
    |--------------------------------------------------------------------------
    | Account Link Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the account-link flow that connects messenger users
    | to Agent user accounts.
    |
    */

    'account_link' => [
        'token_ttl_minutes' => env('MESSENGER_LINK_TOKEN_TTL', 15),
        'use_redis_primary' => env('MESSENGER_LINK_USE_REDIS', true),
    ],

];
