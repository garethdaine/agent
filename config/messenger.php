<?php

declare(strict_types=1);

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
    | General Task CLI Timeout
    |--------------------------------------------------------------------------
    |
    | Max seconds for the CLI process when handling a general task (one-shot
    | claude -p prompt). Keep below HORIZON_MESSENGER_TIMEOUT so the job can
    | finish and update the "Thinking…" message.
    |
    */

    'general_task_cli_timeout_seconds' => (int) env('MESSENGER_GENERAL_TASK_CLI_TIMEOUT', 45),

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
        'discord' => \App\Support\Messenger\Adapters\DiscordAdapter::class,
        'whatsapp' => \App\Support\Messenger\Adapters\WhatsAppAdapter::class,
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
                'requests_per_second' => 30,
                'burst_limit' => 30,
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
                'requests_per_second' => 50,
                'burst_limit' => 100,
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
                'requests_per_second' => 80,
                'burst_limit' => 200,
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

    /*
    |--------------------------------------------------------------------------
    | Gateway Runtime Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the messenger gateway supervisor process that manages
    | local-mode workers (WebSocket, long-polling connections).
    |
    */

    'gateway' => [
        // Maximum time to wait for workers to drain during shutdown
        'shutdown_timeout' => env('MESSENGER_GATEWAY_SHUTDOWN_TIMEOUT', 30),

        // Interval between health check updates to database
        'health_check_interval' => env('MESSENGER_GATEWAY_HEALTH_INTERVAL', 10),

        // Interval for checking credential changes
        'credential_poll_interval' => env('MESSENGER_GATEWAY_CREDENTIAL_POLL', 30),

        // Reconnection backoff settings
        'reconnect' => [
            // Initial delay before first reconnection attempt
            'initial_delay' => env('MESSENGER_GATEWAY_RECONNECT_INITIAL', 1),

            // Maximum delay between reconnection attempts (5 minutes)
            'max_delay' => env('MESSENGER_GATEWAY_RECONNECT_MAX', 300),

            // Percentage of jitter to apply to delays (prevents thundering herd)
            'jitter_percent' => env('MESSENGER_GATEWAY_RECONNECT_JITTER', 20),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the per-connector circuit breaker that protects outbound
    | API calls from cascading failures.
    |
    */

    'circuit_breaker' => [
        // Number of consecutive failures before opening the circuit
        'failure_threshold' => env('MESSENGER_CIRCUIT_FAILURE_THRESHOLD', 5),

        // Seconds to wait before attempting recovery (half-open state)
        'cooldown_seconds' => env('MESSENGER_CIRCUIT_COOLDOWN', 60),

        // Number of test requests allowed in half-open state
        'half_open_requests' => env('MESSENGER_CIRCUIT_HALF_OPEN', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Usage and Compaction
    |--------------------------------------------------------------------------
    |
    | Context usage estimation (chars per token, context window size for %).
    | Compaction triggers when message count or estimated tokens exceed thresholds.
    |
    */

    'context' => [
        'chars_per_token' => (int) env('MESSENGER_CONTEXT_CHARS_PER_TOKEN', 4),
        'estimated_context_window' => (int) env('MESSENGER_ESTIMATED_CONTEXT_WINDOW', 200_000),
    ],

    'compaction' => [
        'enabled' => env('MESSENGER_COMPACTION_ENABLED', true),
        'trigger_message_count' => (int) env('MESSENGER_COMPACTION_TRIGGER_MESSAGE_COUNT', 30),
        'trigger_estimated_tokens' => (int) env('MESSENGER_COMPACTION_TRIGGER_TOKENS', 15_000),
        'target_message_count' => (int) env('MESSENGER_COMPACTION_TARGET_MESSAGES', 10),
    ],

];
