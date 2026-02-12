<?php

return [
    'allowed_working_directory_bases' => [
        '/Users/garethdaine/Code',
        '/Users/garethdaine/Code/agent',
    ],

    'allowed_task_markdown_bases' => [
        '/Users/garethdaine/Code/agent/tasks',
        '/Users/garethdaine/Code/agent/prompts',
    ],

    'runner_executables' => [
        'claude' => env('AGENT_RUNNER_CLAUDE_PATH', '/Users/garethdaine/.local/bin/claude'),
        'codex' => env('AGENT_RUNNER_CODEX_PATH', '/opt/homebrew/bin/codex'),
        'custom' => env('AGENT_RUNNER_CUSTOM_PATH', '/Users/garethdaine/Code/agent/bin/agent-runner'),
    ],

    'default_templates' => [
        'claude' => '/Users/garethdaine/.local/bin/claude -p {{task_markdown_path}}',
        'codex' => '/opt/homebrew/bin/codex exec {{task_markdown_path}}',
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
];
