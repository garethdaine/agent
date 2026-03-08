<?php

$parseEnvCsvList = static function (string $key): array {
    $raw = env($key, '');

    if (! is_string($raw) || trim($raw) === '') {
        return [];
    }

    $values = str_getcsv($raw, ',', '"', '');

    return array_values(array_filter(array_map(
        static fn ($value): string => trim((string) $value),
        $values
    ), static fn (string $value): bool => $value !== ''));
};

$claudeExecutable = env('AGENT_RUNNER_CLAUDE_PATH', '');
$codexExecutable = env('AGENT_RUNNER_CODEX_PATH', '');
$codexModel = trim((string) env('AGENT_RUNNER_CODEX_MODEL', 'gpt-5.3-codex'));
$codexModelArgs = $codexModel !== '' ? ' -m '.$codexModel : '';
$claudeModel = trim((string) env('AGENT_RUNNER_CLAUDE_MODEL', 'claude-opus-4-6'));
$claudeModelArgs = $claudeModel !== '' ? ' --model '.$claudeModel : '';

return [
    'version' => '1.0.0',

    'default_runner_type' => env('AGENT_DEFAULT_RUNNER_TYPE', 'claude'),

    'streaming' => [
        'enabled' => (bool) env('AGENT_STREAMING_ENABLED', false),
        'chunk_size' => (int) env('AGENT_STREAMING_CHUNK_SIZE', 1800),
    ],

    'license' => [
        'key' => env('AGENT_LICENSE_KEY', ''),
        'validation_url' => env('AGENT_LICENSE_VALIDATION_URL', 'https://agent-ops.com/api/license/validate'),
        'cache_ttl_seconds' => (int) env('AGENT_LICENSE_CACHE_TTL', 3600),
        'bypass_domains' => [
            '.test', '.local', '.localhost', '.example', '.invalid',
            'localhost', '127.0.0.1', '::1',
        ],
        'bypass_subdomains' => [
            'staging.', 'stage.', 'test.', 'testing.', 'dev.', 'development.',
        ],
    ],

    'allowed_working_directory_bases' => array_values(array_unique(array_filter(array_merge(
        $parseEnvCsvList('AGENT_WORKING_DIRECTORY_BASES'),
        $parseEnvCsvList('AGENT_ADDITIONAL_WORKING_DIRECTORY_BASES'),
    )))),

    'allowed_task_markdown_bases' => array_values(array_unique(array_filter(array_merge(
        $parseEnvCsvList('AGENT_TASK_MARKDOWN_BASES'),
        $parseEnvCsvList('AGENT_ADDITIONAL_TASK_MARKDOWN_BASES'),
        [storage_path('app/skills'), storage_path('app/memory/context')],
    )))),

    'runner_executables' => [
        'claude' => $claudeExecutable,
        'codex' => $codexExecutable,
        'custom' => env('AGENT_RUNNER_CUSTOM_PATH', ''),
    ],
    'runner_models' => [
        'claude' => $claudeModel,
        'codex' => $codexModel,
    ],

    'workflow_key' => [
        'regex' => '^[a-z0-9._-]+[.]v[1-9][0-9]*$',
        'route_pattern' => '[a-z0-9]+(?:[._-][a-z0-9]+)*\\.v[1-9][0-9]*',
    ],

    'telemetry' => [
        'terminal_event_catalog_version' => 'v1',
        'terminal_event_types' => [
            'run.completed',
            'run.failed',
            'run.aborted',
            'run.cancelled',
            'policy_blocked_terminal',
            'guardrail_blocked_terminal',
        ],
        'synthetic_gap_event_type' => 'telemetry.synthetic.terminal_gap_audit',
        'schema_registry' => [
            'registry_revision' => (string) env('AGENT_TELEMETRY_REGISTRY_REVISION', 'registry-2026-03-04'),
            'schemas' => [
                'agent.telemetry.event' => [
                    '1.0.0' => [
                        'schema_hash' => str_repeat('a', 64),
                        'normalizer_version' => 'telemetry-normalizer.v1',
                    ],
                ],
            ],
        ],
    ],

    'projections' => [
        'stale_after_seconds' => (int) env('AGENT_PROJECTIONS_STALE_AFTER_SECONDS', 900),
    ],

    'reliability' => [
        'weighted_reliability_threshold' => (float) env('AGENT_RELIABILITY_WEIGHTED_THRESHOLD', 95.0),
        'degraded_rate_threshold' => (float) env('AGENT_RELIABILITY_DEGRADED_THRESHOLD', 3.0),
        'low_volume_min_scored_runs' => (int) env('AGENT_RELIABILITY_LOW_VOLUME_MIN_RUNS', 5),
        'assisted_sla_hours' => (int) env('AGENT_RELIABILITY_ASSISTED_SLA_HOURS', 24),
    ],

    'cost_governance' => [
        'system_actor_id' => (string) env('AGENT_COST_GOVERNANCE_SYSTEM_ACTOR_ID', 'system:budget-enforcer'),
        'default_warning_threshold_percent' => (float) env('AGENT_COST_WARNING_THRESHOLD_PERCENT', 80.0),
        'default_enforcement_threshold_percent' => (float) env('AGENT_COST_ENFORCEMENT_THRESHOLD_PERCENT', 100.0),
        'rate_cards' => [
            'default' => [
                'models' => [
                    '*' => [
                        'input_cost_per_thousand_usd' => 0.0,
                        'output_cost_per_thousand_usd' => 0.0,
                    ],
                ],
            ],
        ],
    ],

    'default_templates' => [
        'claude' => trim($claudeExecutable.$claudeModelArgs.' --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}'),
        'codex' => trim($codexExecutable.$codexModelArgs.' exec --json {{task_markdown_path}}'),
    ],

    'interrogation' => [
        'claude_model' => trim((string) env('AGENT_INTERROGATION_CLAUDE_MODEL', $claudeModel)),
        'codex_model' => trim((string) env('AGENT_INTERROGATION_CODEX_MODEL', $codexModel)),
        'build_execution_templates' => [
            'claude' => trim((string) env('AGENT_INTERROGATION_BUILD_TEMPLATE_CLAUDE', $claudeExecutable.$claudeModelArgs.' --dangerously-skip-permissions --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}')),
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
        'duplicate_recovery_max_depth' => (int) env('AGENT_INTERROGATION_DUPLICATE_RECOVERY_MAX_DEPTH', 4),
        'dynamic_question_bank_enabled' => (bool) env('AGENT_INTERROGATION_DYNAMIC_QUESTION_BANK_ENABLED', true),
        'dynamic_question_bank_max_questions' => (int) env('AGENT_INTERROGATION_DYNAMIC_QUESTION_BANK_MAX_QUESTIONS', 18),
        'semantic_dedupe_similarity_threshold' => (float) env('AGENT_INTERROGATION_SEMANTIC_DEDUPE_SIMILARITY_THRESHOLD', 0.88),
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
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_URL',
        'DB_CHARSET',
        'DB_SSLMODE',
        'DB_SEARCH_PATH',
        'TEST_DB_HOST',
        'TEST_DB_PORT',
        'TEST_DB_DATABASE',
        'TEST_DB_USERNAME',
        'TEST_DB_PASSWORD',
        'TEST_DB_CHARSET',
        'TEST_DB_SEARCH_PATH',
        'TEST_DB_SSLMODE',
        'TEST_DB_URL',
    ],

    'forbidden_env_key_pattern' => '/(SECRET|TOKEN|PASSWORD|PASS|PRIVATE|CREDENTIAL)/i',

    'env_max_keys' => 50,
    'env_max_value_length' => 1024,
    'env_max_payload_bytes' => 16 * 1024,

    'rate_limit_default_hold_minutes' => (int) env('AGENT_RATE_LIMIT_DEFAULT_HOLD_MINUTES', 15),

    'run_output_heartbeat_seconds' => (int) env('AGENT_RUN_OUTPUT_HEARTBEAT_SECONDS', 5),

    'log_filtering' => [
        'suppress_machine_noise' => (bool) env('AGENT_SUPPRESS_MACHINE_NOISE', true),
    ],

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

    'engineering_rules' => [
        'enabled' => (bool) env('AGENT_ENGINEERING_RULES_ENABLED', false),
        'source_path' => base_path('docs/refactoring/agent-ops-engineering-rules.md'),
        'cache_ttl_seconds' => (int) env('AGENT_ENGINEERING_RULES_CACHE_TTL', 3600),
        'default_profile' => env('AGENT_ENGINEERING_RULES_DEFAULT_PROFILE', 'full'),
        'max_tokens' => [
            'full' => 8000,
            'core' => 4000,
            'interrogation' => 3000,
            'build' => 6000,
        ],
        'profiles' => [
            'sub_agent' => 'core',
            'interrogation_discovery' => 'interrogation',
            'interrogation_round' => 'interrogation',
            'interrogation_build' => 'build',
        ],
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

    'nl_org' => [
        'max_input_length' => (int) env('NL_ORG_MAX_INPUT_LENGTH', 2000),
        'confidence_threshold' => (float) env('NL_ORG_CONFIDENCE_THRESHOLD', 0.7),
        'max_chat_history_turns' => (int) env('NL_ORG_MAX_CHAT_HISTORY_TURNS', 10),
    ],

    'roles' => [
        'admin_user_ids' => $parseEnvCsvList('AGENT_ADMIN_USER_IDS'),
        'analytics_user_ids' => $parseEnvCsvList('AGENT_ANALYTICS_USER_IDS'),
        'central_on_call_user_ids' => $parseEnvCsvList('AGENT_CENTRAL_ON_CALL_USER_IDS'),
        'workflow_delegate_user_ids' => $parseEnvCsvList('AGENT_WORKFLOW_DELEGATE_USER_IDS'),
        'workflow_delegate_user_ids_by_workflow' => [],
    ],

    'office_3d_enabled' => env('AGENT_OFFICE_3D_ENABLED', true),

    'org' => [
        'enabled' => env('ORG_LAYER_ENABLED', false),
        'features' => [
            'profiles' => env('ORG_PROFILES_ENABLED', true),
            'rituals' => env('ORG_RITUALS_ENABLED', true),
            'councils' => env('ORG_COUNCILS_ENABLED', true),
            'cost_governance' => env('ORG_COST_ENABLED', true),
        ],
        'hierarchy_max_depth' => 3,
        'reviewer_max_attempts' => 3,
        'retention_days' => 90,
        'default_notification_level' => 'escalations_only',
        'budget_hard_stop_behavior' => 'block_undispatched',
    ],

    'webhooks' => [
        'enabled' => env('AGENT_WEBHOOKS_ENABLED', false),
        'url' => env('AGENT_WEBHOOK_URL'),
        'secret' => env('AGENT_WEBHOOK_SECRET'),
        'events' => $parseEnvCsvList('AGENT_WEBHOOK_EVENTS'),
    ],

    'skills' => [
        'storage_path' => storage_path('app/skills'),
        'global_storage_path' => storage_path('app/skills/global'),
        'library_manifest_path' => base_path('skill-library/manifest.json'),
        'max_skills_per_invocation' => (int) env('AGENT_SKILLS_MAX_PER_INVOCATION', 5),
        'max_skills_per_team' => (int) env('AGENT_SKILLS_MAX_PER_TEAM', 50),
        'max_skill_archive_size_bytes' => 10 * 1024 * 1024,
        'max_skill_file_count' => 50,
        'max_skill_body_lines_warning' => 500,
        'risk_level_trust_thresholds' => [
            'low' => 0.0,
            'standard' => 0.5,
            'elevated' => 0.7,
            'critical' => 0.9,
        ],
        'validation' => [
            'weights_without_llm' => [
                'pattern_match' => 0.35,
                'boundary_analysis' => 0.25,
                'authority_escalation' => 0.25,
                'exfiltration' => 0.15,
            ],
            'weights_with_llm' => [
                'pattern_match' => 0.25,
                'boundary_analysis' => 0.20,
                'authority_escalation' => 0.20,
                'exfiltration' => 0.10,
                'llm_review' => 0.25,
            ],
            'thresholds' => [
                'clean' => 0.2,
                'warn' => 0.5,
                'admin_confirm' => 0.8,
            ],
            'script_language_allowlist' => ['python', 'bash', 'javascript', 'php'],
            'dangerous_functions' => ['eval', 'exec', 'system', 'shell_exec', 'passthru'],
        ],
        'drift_check' => [
            'enabled' => (bool) env('AGENT_SKILLS_DRIFT_CHECK_ENABLED', true),
        ],
        'trigger_keyword_extraction' => [
            'max_keywords' => 20,
            'min_keyword_length' => 3,
        ],
    ],
];
