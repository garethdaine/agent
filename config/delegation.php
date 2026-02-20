<?php

return [
    'enabled' => (bool) env('DELEGATION_ENABLED', false),
    'ui_enabled' => (bool) env('DELEGATION_UI_ENABLED', false),
    'max_tasks_per_graph' => (int) env('DELEGATION_MAX_TASKS_PER_GRAPH', 25),
    'max_concurrent_graphs_per_user' => (int) env('DELEGATION_MAX_CONCURRENT_GRAPHS', 3),
    'default_max_parallel_tasks' => (int) env('DELEGATION_DEFAULT_MAX_PARALLEL_TASKS', 5),
    'max_retry_attempts' => (int) env('DELEGATION_MAX_RETRY_ATTEMPTS', 3),
    'default_cancellation_policy' => env('DELEGATION_DEFAULT_CANCELLATION_POLICY', 'drain'),
    'verification_timeout_seconds' => (int) env('DELEGATION_VERIFICATION_TIMEOUT', 300),
    'metrics_event_throttle_seconds' => 60,
    'metrics_recomputation_interval_minutes' => 15,
    'reconciler_interval_minutes' => 2,
    'min_trust_for_retry' => 0.4,
    'min_trust_for_redelegation' => 0.6,
    'escalation_criticality_threshold' => 'high',
    'transient_error_codes' => ['TIMEOUT', 'RATE_LIMIT', 'CONNECTION_*'],
    'non_transient_error_codes' => ['INVALID_OUTPUT', 'PERMISSION_DENIED'],
    'ai_critic_prompt_template' => null,
    'capabilities_seed' => [
        'code_generation', 'code_review', 'testing',
        'documentation', 'refactoring', 'analysis', 'planning',
    ],
    'check_profiles' => [
        'laravel_standard' => ['php artisan test', './vendor/bin/pint --test'],
        'test_only' => ['php artisan test'],
        'lint_only' => ['./vendor/bin/pint --test'],
    ],
];
