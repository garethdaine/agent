<?php

return [
    'enabled' => env('CONNECTORS_ENABLED', false),
    'ui_enabled' => env('CONNECTORS_UI_ENABLED', false),
    'webhooks_enabled' => env('CONNECTORS_WEBHOOKS_ENABLED', false),
    'auto_resolve' => env('CONNECTORS_AUTO_RESOLVE', false),
    'write_actions' => env('CONNECTORS_WRITE_ACTIONS', false),
    'credential_refresh' => env('CONNECTORS_CREDENTIAL_REFRESH', false),
    'manifests_path' => env('CONNECTORS_MANIFESTS_PATH', base_path('connectors')),
    'max_connections_per_team' => (int) env('CONNECTORS_MAX_CONNECTIONS_PER_TEAM', 20),
    'approval_timeout_minutes' => (int) env('CONNECTORS_APPROVAL_TIMEOUT_MINUTES', 15),
    'default_action_timeout_seconds' => (int) env('CONNECTORS_DEFAULT_ACTION_TIMEOUT_SECONDS', 30),
    'retry' => [
        'max_attempts' => 3,
        'backoff_seconds' => [1, 2, 4],
    ],
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'failure_window_seconds' => 60,
        'recovery_timeout_seconds' => 120,
    ],
    'health' => [
        'weights' => [
            'credential_health' => 0.40,
            'error_rate' => 0.30,
            'latency' => 0.15,
            'rate_limit_headroom' => 0.15,
        ],
        'thresholds' => [
            'healthy' => 0.8,
            'degraded' => 0.5,
        ],
    ],
    'telemetry' => [
        'retention_days' => (int) env('CONNECTORS_TELEMETRY_RETENTION_DAYS', 90),
    ],
    'pii_redaction' => [
        'enabled' => true,
        'replacement' => '[REDACTED]',
    ],
];
