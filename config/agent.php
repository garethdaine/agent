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
        'claude' => env('AGENT_RUNNER_CLAUDE_PATH', '/Users/garethdaine/.local/bin/claude'),
        'codex' => $codexExecutable,
        'custom' => env('AGENT_RUNNER_CUSTOM_PATH', '/Users/garethdaine/Code/agent/bin/agent-runner'),
    ],

    'default_templates' => [
        'claude' => '/Users/garethdaine/.local/bin/claude -p {{task_markdown_path}}',
        'codex' => trim($codexExecutable.$codexModelArgs.' exec {{task_markdown_path}}'),
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
