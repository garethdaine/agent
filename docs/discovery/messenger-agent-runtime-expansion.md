# Requirements Discovery Summary

Session: 19

# Messenger Agent Runtime Expansion (OpenClaw-Style)

## Overview

Evolve the Messenger Control Plane from a job/run command interface into a full conversational agent runtime supporting local computer tasks via chat. The system adds an `Agent Runtime Session` layer with policy-driven tool gateway, explicit approval rails, and deterministic slash-command controls while preserving the existing Laravel messenger ingress and identity model.

## Capability Domains

1. **computer/fs**: Read, write, edit, patch, move, delete files (policy constrained)
2. **runtime**: Command execution, background process management, streaming logs
3. **web**: Search, fetch, summarize web content
4. **browser**: Navigate, inspect, act, capture, export via agent-browser sidecar
5. **discovery**: Run requirements-discovery workflow from chat (all 6 operations)
6. **orchestration**: Agent API actions and future MCP tool invocation

## Architecture Decisions

### Routing Model
- **Split router model**: Deterministic `CommandRouter` for slash commands + `AgentRouter` for free-form prompts
- Command router takes precedence to ensure operational reliability

### Tool Gateway
- **Base interface with domain-specific extensions**: `ToolAdapterInterface` defines core contract (`name()`, `schema()`, `authorize()`, `execute()`)
- Domain adapters: `FsToolAdapter`, `RuntimeToolAdapter`, `WebToolAdapter`, `BrowserToolAdapter`, `DiscoveryToolAdapter`, `AgentApiToolAdapter`, `McpToolAdapter`

### Schema Ownership
- **Shared agent-core domain**: Models live in `app/Models/Runtime/` to enable reuse by non-messenger entry points (API, web UI)

### Browser Integration
- **agent-browser sidecar with wrapper**: Run `agent-browser` as managed dependency with constrained Laravel wrapper service
- Session/profile mapping per runtime_session with output streamed to runtime_events
- **User-configurable persistence**: Browser sessions can be ephemeral or persistent per session preference

### MCP Transport
- **Local stdio servers first**: Prioritize local process management before remote HTTP/SSE servers
- Feature-flagged via `MCP_ENABLED` env variable

## Security Model

### Execution Modes and Approval Matrix (Strict Model)
- `safe`: No writes allowed, read-only operations, query, browser snapshot only (default mode)
- `standard`: Approve ALL mutations before execution (file writes, deletes, process spawns)
- `full`: Approve ALL external calls + ALL mutations (network requests, elevated commands)

### Workspace Boundaries
- **Per-session explicit workspace root**: Each runtime session declares allowed directory roots
- Enforced at adapter boundary with path allowlists

### Group Channel Policy
- **Configurable per connector account**: Admins set group channel mode limits
- Default: groups restricted to safe mode

### Policy Snapshots
- Capture at session start + each mode change for forensic audit

## Operations

### Concurrent Sessions
- **Configurable per connector account**: Hard cap set in connector config
- Default limit: 3 concurrent sessions per connector

### Session Timeout
- **No automatic timeout**: Manual cleanup only via `/stop` command or API
- Sessions remain active until explicitly terminated

### Horizon Supervisors
- **Reuse existing messenger supervisors**: No new supervisor topology
- Runtime tool jobs dispatched to existing messenger queues

### Audit Log Retention
- **Indefinite with archival tier**: Cold storage migration after configurable threshold
- **In-app configurable archive threshold**: Default 30 days before migration to cold storage

## Response UX

### Streaming
- **Always stream**: Chunked updates for all runtime turn outputs
- Provider-dependent formatting (rich where supported, text fallback otherwise)

### Approval UX
- **Interactive buttons** where provider supports (Slack, Discord)
- **Hybrid fallback**: Text keywords (`/approve {id}`, `/deny {id}`) as primary, web link for complex approvals as secondary

## Data Model

### New Tables (app/Models/Runtime/)

**runtime_sessions**
- `id` (uuid PK), `chat_session_id` (uuid nullable FK), `user_id` (uuid FK), `status` (enum: pending, active, completed, failed, stopped), `mode` (enum: safe, standard, full), `title` (string nullable), `workspace_root` (string nullable), `browser_persistence_mode` (enum: ephemeral, persistent), `started_at` (timestamp), `ended_at` (timestamp nullable)

**runtime_turns**
- `id` (uuid PK), `runtime_session_id` (uuid FK), `sequence` (integer), `input_message_id` (uuid nullable), `output_message_id` (uuid nullable), `status` (enum: pending, running, completed, failed), `summary` (text nullable)

**runtime_tool_calls**
- `id` (uuid PK), `runtime_turn_id` (uuid FK), `tool_name` (string), `arguments_json` (jsonb), `result_json` (jsonb nullable), `status` (enum: pending_approval, approved, denied, running, completed, failed), `duration_ms` (integer nullable), `requires_approval` (boolean), `approved_at` (timestamp nullable)

**runtime_approvals**
- `id` (uuid PK), `runtime_tool_call_id` (uuid FK), `requested_by` (uuid FK users), `state` (enum: pending, approved, denied, expired), `decision_by` (uuid nullable FK users), `decision_reason` (text nullable), `expires_at` (timestamp nullable)

**runtime_artifacts**
- `id` (uuid PK), `runtime_session_id` (uuid FK), `type` (string: file, screenshot, log, report), `path` (string), `metadata_json` (jsonb nullable)

**runtime_policy_snapshots**
- `id` (uuid PK), `runtime_session_id` (uuid FK), `snapshot_reason` (enum: session_start, mode_change), `policy_json` (jsonb), `captured_at` (timestamp)

## New Services

### Core Runtime Layer (app/Services/Runtime/)
- `MessengerRuntimeOrchestrator`: Coordinates runtime turns and tool selection
- `RuntimeSessionManager`: CRUD and lifecycle for runtime sessions
- `ToolGateway`: Routes tool calls to adapters with policy enforcement
- `ApprovalGate`: Manages approval requests and decisions per strict approval model
- `PolicyEngine`: Evaluates tool authorization against active policy and mode

### Routers (app/Services/Messenger/)
- `CommandRouter`: Handles all slash commands (`/status`, `/runs`, `/stop`, `/mode`, `/approve`, `/deny`, `/browser`)
- `AgentRouter`: Delegates free-form prompts to runtime orchestrator

### Tool Adapters (app/Services/Runtime/Adapters/)
- `ToolAdapterInterface`: Base contract with `name()`, `schema()`, `authorize(RuntimeContext, array): bool`, `execute(RuntimeContext, array): ToolResult`
- `FsToolAdapter`: Filesystem operations with workspace boundary enforcement
- `RuntimeToolAdapter`: Command execution with allowlist enforcement
- `WebToolAdapter`: Web search and fetch with content isolation
- `BrowserToolAdapter`: agent-browser wrapper with session management
- `DiscoveryToolAdapter`: Full workflow (start, answer, generate_summary, generate_plan, generate_build_tasks, start_build)
- `AgentApiToolAdapter`: Internal Agent API wrapper
- `McpToolAdapter`: MCP stdio server integration (feature-flagged)

### Support Services
- `BrowserSidecarManager`: agent-browser process lifecycle, health checks, version pinning
- `RuntimeEventStreamer`: Chunked response streaming to messenger providers
- `AuditLogArchiver`: Cold storage migration for aged audit events (configurable threshold)

## Configuration

### config/runtime.php
```php
return [
    'default_mode' => 'safe',
    'approval_model' => 'strict', // safe=no writes, standard=approve mutations, full=approve all
    'modes' => [
        'safe' => ['capabilities' => ['read', 'query', 'browser_snapshot'], 'approvals_required' => []],
        'standard' => ['capabilities' => ['read', 'write', 'query', 'browser_action', 'runtime_command'], 'approvals_required' => ['mutations']],
        'full' => ['capabilities' => ['*'], 'approvals_required' => ['mutations', 'external', 'elevated']],
    ],
    'concurrent_session_limit_default' => 3,
    'session_timeout' => null,
    'audit_archive_after_days' => env('RUNTIME_AUDIT_ARCHIVE_DAYS', 30),
    'browser' => [
        'sidecar_binary' => env('AGENT_BROWSER_PATH', '/usr/local/bin/agent-browser'),
        'default_persistence' => 'ephemeral',
        'allowed_commands' => ['navigate', 'click', 'type', 'screenshot', 'extract'],
    ],
    'mcp' => [
        'enabled' => env('MCP_ENABLED', false),
        'transport' => 'stdio',
    ],
    'policy_snapshot_triggers' => ['session_start', 'mode_change'],
    'approval_ux' => [
        'interactive_providers' => ['slack', 'discord'],
        'fallback_mode' => 'hybrid', // text keywords + web link
    ],
];
```

### Connector Account Config Extension
```php
'runtime_settings' => [
    'concurrent_session_limit' => 5,
    'group_channel_max_mode' => 'safe',
    'full_mode_requires_reauth' => true,
]
```

## API Extensions

- `GET /agent/api/v1/chat/runtime/sessions` - List user's runtime sessions
- `GET /agent/api/v1/chat/runtime/sessions/{id}` - Session details with turns
- `POST /agent/api/v1/chat/runtime/sessions/{id}/stop` - Terminate session
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/approve` - Approve pending call
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/deny` - Deny pending call

## Event Types

- `runtime.session.started`, `runtime.session.ended`
- `runtime.turn.started`, `runtime.turn.completed`, `runtime.turn.failed`
- `runtime.tool_call.requested`, `runtime.tool_call.approved`, `runtime.tool_call.denied`, `runtime.tool_call.completed`, `runtime.tool_call.failed`
- `runtime.artifact.created`
- `runtime.policy.snapshot_captured`

## Slash Commands (Full Set from Phase 0)

- `/status` - Current runtime/session/tool state
- `/runs` - List active runtime sessions
- `/stop [session_id]` - Terminate session
- `/mode safe|standard|full` - Change execution mode
- `/approve <id>` - Approve pending tool call
- `/deny <id>` - Deny pending tool call
- `/browser start|stop|reset` - Browser sidecar control

## Discovery Tool Integration (Full Workflow)

All 6 operations exposed via DiscoveryToolAdapter:
- `discovery.start` - Initialize requirements discovery for a feature
- `discovery.answer` - Submit answer to discovery question
- `discovery.generate_summary` - Generate feature summary
- `discovery.generate_plan` - Generate implementation plan
- `discovery.generate_build_tasks` - Generate build task breakdown
- `discovery.start_build` - Initiate build execution

## Goals

- Deliver messenger-native agent handling broad natural-language tasks with local execution via chat
- Implement split router model: deterministic CommandRouter for all slash commands + AgentRouter for free-form prompts
- Create unified tool capability model with adapters: FsToolAdapter, RuntimeToolAdapter, WebToolAdapter, BrowserToolAdapter, DiscoveryToolAdapter, AgentApiToolAdapter, McpToolAdapter
- Enforce strict approval model: safe=no writes, standard=approve all mutations, full=approve all external+mutations
- Enable browser-driven tasks through agent-browser sidecar with user-configurable session persistence
- Expose full discovery workflow (all 6 operations) via DiscoveryToolAdapter for end-to-end messenger-driven feature planning
- Bridge to MCP servers using local stdio transport first (feature-flagged)
- Implement always-streaming response UX with chunked updates to messenger providers
- Create shared Runtime domain in app/Models/Runtime/ for cross-entry-point reuse
- Implement indefinite audit log retention with in-app configurable archival threshold (default 30 days)
- Deliver full slash command set from Phase 0: /status, /runs, /stop, /mode, /approve, /deny, /browser
- Support hybrid approval UX fallback: text keywords primary with web link for complex approvals


## Constraints

- Schema must live in shared app/Models/Runtime/ domain, not messenger-specific namespace
- Browser integration must use agent-browser sidecar with strict command wrapper (allowed: navigate, click, type, screenshot, extract)
- MCP transport must prioritize local stdio servers before remote HTTP/SSE
- Runtime tool jobs must reuse existing messenger Horizon supervisors, no new supervisor topology
- Session timeout must be manual-only via /stop command or API (no automatic idle cleanup)
- Audit events must be retained indefinitely with cold storage archival after configurable threshold (default 30 days)
- Response streaming must always use chunked updates for all runtime turn outputs
- Strict approval model enforced: safe mode prohibits all writes, standard mode requires approval for all mutations, full mode requires approval for all mutations and external calls
- Policy snapshots must be captured at session start and each mode change
- All tool adapters must implement base ToolAdapterInterface with authorize() and execute() methods
- Default execution mode must be 'safe' (read-only, query, browser snapshot only)
- Concurrent session limits configurable per connector account with default of 3
- All slash commands must be implemented in Phase 0 including /browser start|stop|reset


## Acceptance Criteria

- User can request and complete cross-domain tasks (fs + web + runtime) from messenger with appropriate approvals enforced per strict model
- CommandRouter correctly handles /status, /runs, /stop, /mode, /approve, /deny, /browser commands; AgentRouter delegates free-form prompts
- Runtime sessions persist in app/Models/Runtime/ tables with full turn and tool call history
- Browser tasks execute through agent-browser sidecar with session persistence configurable per session (ephemeral or persistent)
- ApprovalGate blocks all mutations in standard mode and all mutations+external calls in full mode until explicit approval
- Safe mode rejects any write/mutation operations without approval prompt (hard block)
- Policy snapshots captured at session start and mode changes stored in runtime_policy_snapshots
- DiscoveryToolAdapter exposes all 6 operations: start, answer, generate_summary, generate_plan, generate_build_tasks, start_build
- Concurrent session limits enforced per connector account configuration (default 3)
- Sessions remain active until explicit /stop command or API termination (no auto-timeout)
- Audit trail links message → runtime turn → tool calls → approvals → outputs with indefinite retention
- Audit logs migrate to cold storage after configurable threshold (default 30 days, adjustable in-app)
- McpToolAdapter accessible via stdio transport when MCP_ENABLED=true
- Approval UX uses interactive buttons on Slack/Discord, hybrid fallback (text keywords + web link) on other providers
- Workspace boundaries enforced per-session via workspace_root with path validation in tool adapters
- All slash commands functional from Phase 0 including /browser start|stop|reset

