<?php

$parseEnvCsvList = static function (string $key): array {
    $raw = env($key, '');

    if (! is_string($raw) || trim($raw) === '') {
        return [];
    }

    $values = str_getcsv($raw);

    return array_values(array_filter(array_map(
        static fn ($value): string => trim((string) $value),
        $values
    ), static fn (string $value): bool => $value !== ''));
};

$claudeExecutable = env('AGENT_RUNNER_CLAUDE_PATH', '/Users/garethdaine/.local/bin/claude');
$codexExecutable = env('AGENT_RUNNER_CODEX_PATH', '/opt/homebrew/bin/codex');
$codexModel = trim((string) env('AGENT_RUNNER_CODEX_MODEL', 'gpt-5.3-codex'));
$codexModelArgs = $codexModel !== '' ? ' -m '.$codexModel : '';

return [
    'allowed_working_directory_bases' => array_values(array_unique(array_merge([
        '/Users/garethdaine/Code',
        '/Users/garethdaine/Code/agent',
        '/Users/garethdaine/Documents',
    ], $parseEnvCsvList('AGENT_ADDITIONAL_WORKING_DIRECTORY_BASES')))),

    'allowed_task_markdown_bases' => array_values(array_unique(array_merge([
        '/Users/garethdaine/Code/agent/tasks',
        '/Users/garethdaine/Code/agent/prompts',
    ], $parseEnvCsvList('AGENT_ADDITIONAL_TASK_MARKDOWN_BASES')))),

    'runner_executables' => [
        'claude' => $claudeExecutable,
        'codex' => $codexExecutable,
        'custom' => env('AGENT_RUNNER_CUSTOM_PATH', '/Users/garethdaine/Code/agent/bin/agent-runner'),
    ],
    'runner_models' => [
        'codex' => $codexModel,
    ],

    'default_templates' => [
        'claude' => trim($claudeExecutable.' --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}'),
        'codex' => trim($codexExecutable.$codexModelArgs.' exec --json {{task_markdown_path}}'),
    ],

    'interrogation' => [
        'codex_model' => trim((string) env('AGENT_INTERROGATION_CODEX_MODEL', $codexModel)),
        'build_execution_templates' => [
            'claude' => trim((string) env('AGENT_INTERROGATION_BUILD_TEMPLATE_CLAUDE', $claudeExecutable.' --dangerously-skip-permissions --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}')),
            'codex' => trim((string) env('AGENT_INTERROGATION_BUILD_TEMPLATE_CODEX', trim($codexExecutable.$codexModelArgs.' --dangerously-bypass-approvals-and-sandbox --search exec --json {{task_markdown_path}}'))),
        ],
        'build_task_generation_timeout_seconds' => (int) env('AGENT_INTERROGATION_BUILD_TASK_GENERATION_TIMEOUT_SECONDS', 7200),
        'build_task_generation_job_timeout_seconds' => (int) env('AGENT_INTERROGATION_BUILD_TASK_GENERATION_JOB_TIMEOUT_SECONDS', 7500),
        'max_text_length' => (int) env('AGENT_INTERROGATION_MAX_TEXT_LENGTH', 60000),
        'max_active_sessions' => (int) env('AGENT_INTERROGATION_MAX_ACTIVE_SESSIONS', 3),
        'codex_min_feature_answers' => (int) env('AGENT_INTERROGATION_CODEX_MIN_FEATURE_ANSWERS', 5),
        'codex_min_general_answers' => (int) env('AGENT_INTERROGATION_CODEX_MIN_GENERAL_ANSWERS', 3),
        'codex_plan_min_markdown_chars' => (int) env('AGENT_INTERROGATION_CODEX_PLAN_MIN_MARKDOWN_CHARS', 2500),
        'codex_plan_min_sections' => (int) env('AGENT_INTERROGATION_CODEX_PLAN_MIN_SECTIONS', 8),
        'codex_plan_min_concrete_references' => (int) env('AGENT_INTERROGATION_CODEX_PLAN_MIN_CONCRETE_REFERENCES', 6),
        'codex_plan_quality_retries' => (int) env('AGENT_INTERROGATION_CODEX_PLAN_QUALITY_RETRIES', 1),
        'plan_payload_retry_attempts' => (int) env('AGENT_INTERROGATION_PLAN_PAYLOAD_RETRY_ATTEMPTS', 2),
        'plan_guard_min_markdown_chars' => (int) env('AGENT_INTERROGATION_PLAN_GUARD_MIN_MARKDOWN_CHARS', 220),
        'adversarial_review_enabled' => (bool) env('AGENT_ADVERSARIAL_REVIEW_ENABLED', false),
        'summary_review_max_retries' => (int) env('AGENT_SUMMARY_REVIEW_MAX_RETRIES', 3),
        'plan_review_max_retries' => (int) env('AGENT_PLAN_REVIEW_MAX_RETRIES', 2),
        'review_warn_only' => (bool) env('AGENT_REVIEW_WARN_ONLY', false),
        'review_severity_threshold' => env('AGENT_REVIEW_SEVERITY_THRESHOLD', 'high'),
        'review_low_confidence_threshold' => 0.6,
        'review_max_clarification_questions' => 3,
        'reviewer_model_override' => env('AGENT_REVIEWER_MODEL_OVERRIDE', null),
    ],

    'allowed_placeholders' => [
        '{{run_id}}',
        '{{job_id}}',
        '{{task_markdown_path}}',
        '{{working_directory}}',
        '{{job_name}}',
    ],

    'forbidden_env_keys' => [
        'PATH',
        'HOME',
        'SHELL',
        'USER',
        'LOGNAME',
        'APP_KEY',
    ],

    'forbidden_env_key_pattern' => '/(SECRET|TOKEN|PASSWORD|PASS|PRIVATE|CREDENTIAL)/i',

    'env_max_keys' => 50,
    'env_max_value_length' => 1024,
    'env_max_payload_bytes' => 16 * 1024,

    'rate_limit_default_hold_minutes' => (int) env('AGENT_RATE_LIMIT_DEFAULT_HOLD_MINUTES', 15),

    'run_output_heartbeat_seconds' => (int) env('AGENT_RUN_OUTPUT_HEARTBEAT_SECONDS', 5),

    'compliance' => [
        'enabled' => (bool) env('AGENT_COMPLIANCE_ENABLED', false),
        'enforcement_mode' => env('AGENT_COMPLIANCE_ENFORCEMENT_MODE', 'advisory'),
        'plan_gate_enabled' => (bool) env('AGENT_COMPLIANCE_PLAN_GATE_ENABLED', true),
        'verification_gate_enabled' => (bool) env('AGENT_COMPLIANCE_VERIFICATION_GATE_ENABLED', true),
        'elegance_gate_enabled' => (bool) env('AGENT_COMPLIANCE_ELEGANCE_GATE_ENABLED', false),
        'lessons_enabled' => (bool) env('AGENT_COMPLIANCE_LESSONS_ENABLED', true),
        'lessons_token_budget' => (int) env('AGENT_COMPLIANCE_LESSONS_TOKEN_BUDGET', 2000),
        'complexity_thresholds' => [
            'file_count' => (int) env('AGENT_COMPLIANCE_FILE_COUNT_THRESHOLD', 3),
            'loc_count' => (int) env('AGENT_COMPLIANCE_LOC_THRESHOLD', 50),
            'directory_count' => (int) env('AGENT_COMPLIANCE_DIRECTORY_THRESHOLD', 2),
        ],
    ],

    'star_preamble' => [
        'enabled' => (bool) env('AGENT_STAR_PREAMBLE_ENABLED', true),
        'ab_test_enabled' => (bool) env('AGENT_STAR_AB_TEST_ENABLED', false),
        'ab_test_treatment_percent' => (int) env('AGENT_STAR_AB_TEST_PERCENT', 50),
    ],

    'targeted_retry' => [
        'enabled' => (bool) env('AGENT_TARGETED_RETRY_ENABLED', false),
        'max_retries' => (int) env('AGENT_TARGETED_RETRY_MAX', 1),
    ],

    'trust' => [
        'window_size' => (int) env('AGENT_TRUST_WINDOW_SIZE', 50),
        'default_score' => (float) env('AGENT_TRUST_DEFAULT_SCORE', 0.5),
        'min_job_runs' => (int) env('AGENT_TRUST_MIN_JOB_RUNS', 10),
        'recalc_interval_runs' => (int) env('AGENT_TRUST_RECALC_INTERVAL', 10),
    ],

    'nl_parse' => [
        'confidence_threshold' => (float) env('NL_PARSE_CONFIDENCE_THRESHOLD', 0.75),
        'llm_timeout_seconds' => (int) env('NL_PARSE_TIMEOUT_SECONDS', 30),
        'idempotency_window_seconds' => (int) env('NL_PARSE_IDEMPOTENCY_SECONDS', 60),
        'rate_limit_per_minute' => (int) env('NL_PARSE_RATE_LIMIT_PER_MINUTE', 10),
        'rate_limit_per_hour' => (int) env('NL_PARSE_RATE_LIMIT_PER_HOUR', 60),
        'max_input_length' => (int) env('NL_PARSE_MAX_INPUT_LENGTH', 200),
        'min_interval_minutes' => (int) env('NL_PARSE_MIN_INTERVAL_MINUTES', 1),
        'retention_days' => (int) env('NL_PARSE_RETENTION_DAYS', 90),
    ],

    'roles' => [
        'admin_user_ids' => $parseEnvCsvList('AGENT_ADMIN_USER_IDS'),
        'analytics_user_ids' => $parseEnvCsvList('AGENT_ANALYTICS_USER_IDS'),
    ],
];
