<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Execution Mode
    |--------------------------------------------------------------------------
    |
    | The default execution mode for new runtime sessions. Safe mode provides
    | read-only access with no approvals required. Standard mode requires
    | approval for mutations. Full mode requires approval for all external
    | and elevated operations.
    |
    */

    'default_mode' => 'safe',

    /*
    |--------------------------------------------------------------------------
    | Approval Model
    |--------------------------------------------------------------------------
    |
    | The approval enforcement model. Strict mode enforces approvals based on
    | the current execution mode's requirements.
    |
    */

    'approval_model' => 'strict',

    /*
    |--------------------------------------------------------------------------
    | Execution Modes
    |--------------------------------------------------------------------------
    |
    | Configuration for each execution mode including capabilities and
    | approval requirements.
    |
    | safe: No writes allowed, read-only operations only
    | standard: Approve ALL mutations before execution
    | full: Approve ALL external calls + ALL mutations
    |
    */

    'modes' => [
        'safe' => [
            'capabilities' => ['read', 'query', 'browser_snapshot'],
            'approvals_required' => [],
        ],
        'standard' => [
            'capabilities' => ['read', 'write', 'query', 'browser_action', 'runtime_command'],
            'approvals_required' => ['mutations'],
        ],
        'full' => [
            'capabilities' => ['*'],
            'approvals_required' => ['mutations', 'external', 'elevated'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrent Session Limit
    |--------------------------------------------------------------------------
    |
    | The default maximum number of concurrent runtime sessions per connector
    | account. This can be overridden per connector account configuration.
    |
    */

    'concurrent_session_limit_default' => 3,

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Automatic session timeout in seconds. Set to null for no automatic
    | timeout (manual cleanup only via /stop command or API).
    |
    */

    'session_timeout' => null,

    /*
    |--------------------------------------------------------------------------
    | Audit Archive Threshold
    |--------------------------------------------------------------------------
    |
    | Number of days before audit log events are migrated to cold storage.
    |
    */

    'audit_archive_after_days' => env('RUNTIME_AUDIT_ARCHIVE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Browser Sidecar Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the agent-browser sidecar process that provides
    | browser automation capabilities.
    |
    */

    'browser' => [
        'sidecar_binary' => env('AGENT_BROWSER_PATH', '/usr/local/bin/agent-browser'),
        'default_persistence' => 'ephemeral',
        'allowed_commands' => ['navigate', 'click', 'type', 'screenshot', 'extract'],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Model Context Protocol integration. Feature-flagged
    | via MCP_ENABLED environment variable.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Web / HTTP Tool
    |--------------------------------------------------------------------------
    |
    | Allowed hosts for the web tool (HTTP fetch). Empty array means no
    | external HTTP calls. Hosts are matched by exact string (e.g. api.example.com).
    |
    */

    'web' => [
        'allowed_hosts' => array_filter(explode(',', env('RUNTIME_WEB_ALLOWED_HOSTS', ''))),
        'timeout_seconds' => (int) env('RUNTIME_WEB_TIMEOUT', 30),
    ],

    'mcp' => [
        'enabled' => env('MCP_ENABLED', false),
        'transport' => 'stdio',
        'server_command' => env('MCP_SERVER_COMMAND'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy Snapshot Triggers
    |--------------------------------------------------------------------------
    |
    | Events that trigger a policy snapshot capture for forensic audit.
    |
    */

    'policy_snapshot_triggers' => ['session_start', 'mode_change'],

    /*
    |--------------------------------------------------------------------------
    | Approval UX Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the approval user experience across different
    | messenger providers.
    |
    */

    'approval_ux' => [
        'interactive_providers' => ['slack', 'discord'],
        'fallback_mode' => 'hybrid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime LLM (Turn Execution)
    |--------------------------------------------------------------------------
    |
    | Configuration for the LLM used by the runtime orchestrator to process
    | turns with tool use. Uses Anthropic Messages API when api_key is set.
    |
    */

    'llm' => [
        'provider' => env('RUNTIME_LLM_PROVIDER', 'anthropic'),
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('RUNTIME_LLM_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => (int) env('RUNTIME_LLM_MAX_TOKENS', 8192),
        ],
    ],

];
