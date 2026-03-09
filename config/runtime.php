<?php

declare(strict_types=1);

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

    'default_mode' => env('RUNTIME_DEFAULT_MODE', 'safe'),

    /*
    |--------------------------------------------------------------------------
    | Approval Model
    |--------------------------------------------------------------------------
    |
    | The approval enforcement model. Strict mode enforces approvals based on
    | the current execution mode's requirements.
    |
    */

    'approval_model' => env('RUNTIME_APPROVAL_MODEL', 'strict'),

    /*
    |--------------------------------------------------------------------------
    | Tool Deny / Allow List (Enterprise)
    |--------------------------------------------------------------------------
    |
    | tool_deny: Tool names (adapter name or qualified e.g. fs.write) that are
    | never allowed. Applied after mode capabilities.
    | tool_allow: If non-empty, only these tools are allowed (allowlist mode).
    | When set, tool_deny is ignored; tools not in this list are denied.
    |
    */

    'tool_deny' => array_filter(explode(',', env('RUNTIME_TOOL_DENY', ''))),
    'tool_allow' => array_filter(explode(',', env('RUNTIME_TOOL_ALLOW', ''))),

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
            'capabilities' => ['read', 'write', 'query', 'browser_snapshot', 'browser_action', 'runtime_command'],
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

    'concurrent_session_limit_default' => (int) env('RUNTIME_CONCURRENT_SESSION_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Automatic session timeout in seconds. Set to null for no automatic
    | timeout (manual cleanup only via /stop command or API).
    |
    */

    'session_timeout' => env('RUNTIME_SESSION_TIMEOUT') ? (int) env('RUNTIME_SESSION_TIMEOUT') : null,

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
    | browser automation capabilities. Set headed=true or AGENT_BROWSER_HEADED=1
    | to show a visible browser window so you can watch what the agent is doing.
    |
    */

    'browser' => [
        'sidecar_binary' => env('AGENT_BROWSER_PATH', '/usr/local/bin/agent-browser'),
        'default_persistence' => 'ephemeral',
        'denied_commands' => ['install', 'connect', 'record', 'trace', 'profiler'],
        'headed' => (bool) env('AGENT_BROWSER_HEADED', false),
        'timeout' => (int) env('AGENT_BROWSER_TIMEOUT', 60),
        'session_name' => env('AGENT_BROWSER_SESSION'),
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
    | Runtime Execution: CLI vs In-App LLM
    |--------------------------------------------------------------------------
    |
    | When use_cli is true (default), the runtime runs the normal CLI (claude/codex)
    | with the user message as a task. The CLI has the same abilities (browser, MCP,
    | etc.) and uses the API key from the credential manager. When false, turns are
    | executed in-app via the Anthropic API; the key is still from the credential
    | manager, not from .env.
    |
    */

    'use_cli' => env('RUNTIME_USE_CLI', true),

    'cli' => [
        'runner_type' => env('RUNTIME_CLI_RUNNER', 'claude'),
        'timeout_seconds' => (int) env('RUNTIME_CLI_TIMEOUT', 1800),
        'session_resume' => env('RUNTIME_SESSION_RESUME', true),
        'wrapper_enabled' => (bool) env('RUNTIME_WRAPPER_ENABLED', false),
        'wrapper_idle_timeout' => (int) env('RUNTIME_WRAPPER_IDLE_TIMEOUT', 3600),
        'tool_approval_timeout' => (int) env('RUNTIME_TOOL_APPROVAL_TIMEOUT', 300),
        'tool_approval_poll_interval' => 2,
        'yield_enabled' => (bool) env('RUNTIME_YIELD_ENABLED', false),
        'yield_after_seconds' => (int) env('RUNTIME_YIELD_AFTER', 120),
    ],

    'queue' => env('RUNTIME_QUEUE', 'agent'), // Use "default" to process runtime with `php artisan queue:work` (no Horizon).

    /*
    |--------------------------------------------------------------------------
    | Sub-Agent Dispatch
    |--------------------------------------------------------------------------
    |
    | Controls sub-agent spawning from messenger runtime sessions. Sub-agents
    | run independently and announce results back to the parent's channel.
    |
    */

    'subagents' => [
        'enabled' => (bool) env('RUNTIME_SUBAGENTS_ENABLED', false),
        'max_concurrent_per_session' => (int) env('RUNTIME_SUBAGENT_MAX_CONCURRENT', 4),
        'max_spawn_depth' => (int) env('RUNTIME_SUBAGENT_MAX_DEPTH', 1),
        'default_timeout_seconds' => (int) env('RUNTIME_SUBAGENT_TIMEOUT', 1800),
        'queue' => env('RUNTIME_SUBAGENT_QUEUE', 'subagent'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime LLM (In-App Turn Execution Fallback)
    |--------------------------------------------------------------------------
    |
    | Only used when use_cli is false. API key is resolved from the credential
    | manager for the session user; env('ANTHROPIC_API_KEY') is not used.
    |
    */

    'llm' => [
        'provider' => env('RUNTIME_LLM_PROVIDER', 'anthropic'),
        'anthropic' => [
            'api_key' => null,
            'model' => env('RUNTIME_LLM_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => (int) env('RUNTIME_LLM_MAX_TOKENS', 8192),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
        ],
    ],

];
