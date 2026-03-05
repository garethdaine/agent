# Implementation Plan

Derived from discovery session 19.

# Messenger Agent Runtime Expansion Implementation Plan

## Overview

This plan evolves the Messenger Control Plane from a job/run command interface into a full conversational agent runtime. The implementation adds an Agent Runtime Session layer with policy-driven tool gateway, explicit approval rails, and deterministic slash-command controls while preserving the existing Laravel messenger ingress and identity model.

---

## Phase 1: Runtime Domain Foundation

### 1.1 Create Runtime Domain Models

**Location:** `app/Models/Runtime/`

Create the shared runtime domain models that will be reused by messenger, API, and web entry points.

**Files to create:**
- `app/Models/Runtime/RuntimeSession.php`
- `app/Models/Runtime/RuntimeTurn.php`
- `app/Models/Runtime/RuntimeToolCall.php`
- `app/Models/Runtime/RuntimeApproval.php`
- `app/Models/Runtime/RuntimeArtifact.php`
- `app/Models/Runtime/RuntimePolicySnapshot.php`

**RuntimeSession model attributes:**
- `id` (uuid PK)
- `chat_session_id` (uuid nullable FK to chat_sessions)
- `user_id` (uuid FK to users)
- `status` (enum: pending, active, completed, failed, stopped)
- `mode` (enum: safe, standard, full)
- `title` (string nullable)
- `workspace_root` (string nullable)
- `browser_persistence_mode` (enum: ephemeral, persistent)
- `started_at` (timestamp)
- `ended_at` (timestamp nullable)

**RuntimeTurn model attributes:**
- `id` (uuid PK)
- `runtime_session_id` (uuid FK)
- `sequence` (integer)
- `input_message_id` (uuid nullable)
- `output_message_id` (uuid nullable)
- `status` (enum: pending, running, completed, failed)
- `summary` (text nullable)

**RuntimeToolCall model attributes:**
- `id` (uuid PK)
- `runtime_turn_id` (uuid FK)
- `tool_name` (string)
- `arguments_json` (jsonb)
- `result_json` (jsonb nullable)
- `status` (enum: pending_approval, approved, denied, running, completed, failed)
- `duration_ms` (integer nullable)
- `requires_approval` (boolean)
- `approved_at` (timestamp nullable)

**RuntimeApproval model attributes:**
- `id` (uuid PK)
- `runtime_tool_call_id` (uuid FK)
- `requested_by` (uuid FK users)
- `state` (enum: pending, approved, denied, expired)
- `decision_by` (uuid nullable FK users)
- `decision_reason` (text nullable)
- `expires_at` (timestamp nullable)

**RuntimeArtifact model attributes:**
- `id` (uuid PK)
- `runtime_session_id` (uuid FK)
- `type` (string: file, screenshot, log, report)
- `path` (string)
- `metadata_json` (jsonb nullable)

**RuntimePolicySnapshot model attributes:**
- `id` (uuid PK)
- `runtime_session_id` (uuid FK)
- `snapshot_reason` (enum: session_start, mode_change)
- `policy_json` (jsonb)
- `captured_at` (timestamp)

### 1.2 Create Database Migrations

**Location:** `database/migrations/`

**Files to create:**
- `2026_03_05_000001_create_runtime_sessions_table.php`
- `2026_03_05_000002_create_runtime_turns_table.php`
- `2026_03_05_000003_create_runtime_tool_calls_table.php`
- `2026_03_05_000004_create_runtime_approvals_table.php`
- `2026_03_05_000005_create_runtime_artifacts_table.php`
- `2026_03_05_000006_create_runtime_policy_snapshots_table.php`

**Index requirements:**
- `runtime_sessions`: index on `(user_id, status)`, `(chat_session_id)`
- `runtime_turns`: index on `(runtime_session_id, sequence)`
- `runtime_tool_calls`: index on `(runtime_turn_id)`, `(status, requires_approval)`
- `runtime_approvals`: index on `(runtime_tool_call_id)`, `(state, expires_at)`
- `runtime_artifacts`: index on `(runtime_session_id, type)`
- `runtime_policy_snapshots`: index on `(runtime_session_id, captured_at)`

### 1.3 Create Runtime Enums

**Location:** `app/Enums/Runtime/`

**Files to create:**
- `app/Enums/Runtime/RuntimeSessionStatus.php` (pending, active, completed, failed, stopped)
- `app/Enums/Runtime/RuntimeMode.php` (safe, standard, full)
- `app/Enums/Runtime/RuntimeTurnStatus.php` (pending, running, completed, failed)
- `app/Enums/Runtime/RuntimeToolCallStatus.php` (pending_approval, approved, denied, running, completed, failed)
- `app/Enums/Runtime/RuntimeApprovalState.php` (pending, approved, denied, expired)
- `app/Enums/Runtime/RuntimeArtifactType.php` (file, screenshot, log, report)
- `app/Enums/Runtime/PolicySnapshotReason.php` (session_start, mode_change)
- `app/Enums/Runtime/BrowserPersistenceMode.php` (ephemeral, persistent)

### 1.4 Create Runtime Configuration

**Location:** `config/runtime.php`

```php
return [
    'default_mode' => 'safe',
    'approval_model' => 'strict',
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
        'fallback_mode' => 'hybrid',
    ],
];
```

### 1.5 Extend ConnectorAccount Config Schema

**Location:** `app/Models/ConnectorAccount.php`

Add support for runtime_settings in the config JSON:
- `concurrent_session_limit` (integer, default 3)
- `group_channel_max_mode` (string, default 'safe')
- `full_mode_requires_reauth` (boolean, default true)

---

## Phase 2: Tool Gateway Infrastructure

### 2.1 Create Tool Adapter Interface

**Location:** `app/Contracts/Runtime/ToolAdapterInterface.php`

```php
interface ToolAdapterInterface
{
    public function name(): string;
    public function schema(): array;
    public function authorize(RuntimeContext $context, array $args): bool;
    public function execute(RuntimeContext $context, array $args): ToolResult;
}
```

### 2.2 Create Runtime Context DTO

**Location:** `app/DTOs/Runtime/RuntimeContext.php`

Attributes:
- `RuntimeSession $session`
- `RuntimeTurn $turn`
- `User $user`
- `RuntimeMode $mode`
- `array $policy`
- `?string $workspaceRoot`

### 2.3 Create Tool Result DTO

**Location:** `app/DTOs/Runtime/ToolResult.php`

Attributes:
- `bool $success`
- `mixed $data`
- `?string $error`
- `int $durationMs`
- `array $artifacts`

### 2.4 Create Base Tool Adapters

**Location:** `app/Services/Runtime/Adapters/`

**Files to create:**
- `AbstractToolAdapter.php` (base class with common authorization logic)
- `FsToolAdapter.php` (filesystem operations)
- `RuntimeToolAdapter.php` (command execution)
- `WebToolAdapter.php` (web search and fetch)
- `BrowserToolAdapter.php` (agent-browser wrapper)
- `DiscoveryToolAdapter.php` (interrogation workflow)
- `AgentApiToolAdapter.php` (internal Agent API)
- `McpToolAdapter.php` (MCP stdio integration, feature-flagged)

**FsToolAdapter capabilities:**
- `fs.read` - Read file contents
- `fs.write` - Write file (requires approval in standard/full mode)
- `fs.edit` - Edit file with patch (requires approval in standard/full mode)
- `fs.delete` - Delete file (requires approval in standard/full mode)
- `fs.list` - List directory contents
- `fs.move` - Move/rename file (requires approval in standard/full mode)

**RuntimeToolAdapter capabilities:**
- `runtime.exec` - Execute command (requires approval in standard/full mode)
- `runtime.spawn` - Spawn background process (requires approval)
- `runtime.kill` - Kill process (requires approval)
- `runtime.logs` - Stream process logs

**WebToolAdapter capabilities:**
- `web.search` - Search web (requires approval in full mode)
- `web.fetch` - Fetch URL content (requires approval in full mode)
- `web.summarize` - Summarize web content

**BrowserToolAdapter capabilities:**
- `browser.navigate` - Navigate to URL (requires approval in standard/full mode)
- `browser.click` - Click element (requires approval in standard/full mode)
- `browser.type` - Type text (requires approval in standard/full mode)
- `browser.screenshot` - Capture screenshot
- `browser.extract` - Extract content from page

**DiscoveryToolAdapter capabilities (maps to existing InterrogationSession API):**
- `discovery.start` - Initialize discovery session
- `discovery.answer` - Submit answer to question
- `discovery.generate_summary` - Generate feature summary
- `discovery.generate_plan` - Generate implementation plan
- `discovery.generate_build_tasks` - Generate build tasks
- `discovery.start_build` - Start build execution

### 2.5 Create Tool Gateway Service

**Location:** `app/Services/Runtime/ToolGateway.php`

Responsibilities:
- Register and resolve tool adapters
- Route tool calls to appropriate adapter
- Enforce policy at gateway boundary
- Record tool call attempts and results

### 2.6 Create Policy Engine

**Location:** `app/Services/Runtime/PolicyEngine.php`

Responsibilities:
- Evaluate tool authorization against active policy and mode
- Determine if approval is required based on strict model
- Capture policy snapshots at session start and mode changes
- Enforce workspace boundary constraints

### 2.7 Create Approval Gate

**Location:** `app/Services/Runtime/ApprovalGate.php`

Responsibilities:
- Create RuntimeApproval records for pending tool calls
- Process approval/denial decisions
- Handle approval expiration
- Block tool execution until approval received (strict model)

---

## Phase 3: Router Architecture

### 3.1 Create Command Router

**Location:** `app/Services/Messenger/CommandRouter.php`

Handles all slash commands with deterministic behavior:
- `/status` - Return current runtime/session/tool state
- `/runs` - List active runtime sessions
- `/stop [session_id]` - Terminate session
- `/mode safe|standard|full` - Change execution mode
- `/approve <id>` - Approve pending tool call
- `/deny <id>` - Deny pending tool call
- `/browser start|stop|reset` - Browser sidecar control

**Implementation pattern:**
```php
class CommandRouter
{
    private array $commands = [
        '/status' => StatusCommandHandler::class,
        '/runs' => RunsCommandHandler::class,
        '/stop' => StopCommandHandler::class,
        '/mode' => ModeCommandHandler::class,
        '/approve' => ApproveCommandHandler::class,
        '/deny' => DenyCommandHandler::class,
        '/browser' => BrowserCommandHandler::class,
    ];
    
    public function isCommand(string $content): bool;
    public function route(string $content, ChatSession $session, User $user): ?CommandResult;
}
```

### 3.2 Create Slash Command Handlers

**Location:** `app/Messenger/SlashCommands/`

**Files to create:**
- `SlashCommandHandlerInterface.php`
- `StatusCommandHandler.php`
- `RunsCommandHandler.php`
- `StopCommandHandler.php`
- `ModeCommandHandler.php`
- `ApproveCommandHandler.php`
- `DenyCommandHandler.php`
- `BrowserCommandHandler.php`

### 3.3 Create Agent Router

**Location:** `app/Services/Messenger/AgentRouter.php`

Handles free-form prompts by delegating to runtime orchestrator:
- Parse user intent
- Create or continue runtime session
- Delegate to MessengerRuntimeOrchestrator

### 3.4 Modify ProcessChatIntent Job

**Location:** `app/Jobs/Messenger/ProcessChatIntent.php`

Modify the existing job to:
1. First check if message is a slash command via CommandRouter
2. If command, execute via CommandRouter and return
3. If not command, check if it matches legacy ChatActionType patterns
4. If legacy action, process via existing ChatActionExecutor
5. Otherwise, delegate to AgentRouter for runtime processing

This preserves backward compatibility with existing job/run commands.

---

## Phase 4: Runtime Orchestration

### 4.1 Create Runtime Session Manager

**Location:** `app/Services/Runtime/RuntimeSessionManager.php`

Responsibilities:
- Create new runtime sessions with policy snapshot
- Resume existing sessions
- Terminate sessions via /stop command or API
- Enforce concurrent session limits per connector account
- Validate workspace root against allowed paths

### 4.2 Create Messenger Runtime Orchestrator

**Location:** `app/Services/Runtime/MessengerRuntimeOrchestrator.php`

Responsibilities:
- Coordinate runtime turns for messenger entry point
- Select appropriate tools for user request
- Execute tool calls through ToolGateway
- Handle approval flow with ApprovalGate
- Stream responses via RuntimeEventStreamer

### 4.3 Create Runtime Event Streamer

**Location:** `app/Services/Runtime/RuntimeEventStreamer.php`

Responsibilities:
- Format runtime turn outputs as chunked updates
- Adapt output format to provider capabilities (rich vs text)
- Handle interactive button rendering for Slack/Discord
- Fall back to text keywords for other providers

### 4.4 Create Runtime Turn Processor Job

**Location:** `app/Jobs/Runtime/ProcessRuntimeTurnJob.php`

Queued on existing `messenger-default` queue (reusing messenger supervisor).

Responsibilities:
- Process a single runtime turn
- Execute tool plan via ToolGateway
- Handle approval gates
- Update RuntimeTurn status
- Dispatch response via RuntimeEventStreamer

---

## Phase 5: Browser Integration

### 5.1 Create Browser Sidecar Manager

**Location:** `app/Services/Runtime/BrowserSidecarManager.php`

Responsibilities:
- Manage agent-browser process lifecycle
- Health checks and version pinning
- Restart policies on failure
- Session/profile mapping per runtime session

### 5.2 Create Browser Command Wrapper

**Location:** `app/Services/Runtime/BrowserCommandWrapper.php`

Strict wrapper around agent-browser CLI:
- Validate commands against allowed_commands config
- Sanitize and escape arguments
- Parse JSON output
- Handle timeout and errors

### 5.3 Implement BrowserToolAdapter

Complete implementation of `app/Services/Runtime/Adapters/BrowserToolAdapter.php`:
- Call agent-browser via BrowserCommandWrapper
- Map session persistence mode (ephemeral/persistent)
- Store artifacts (screenshots) as RuntimeArtifact records
- Stream output to runtime events

---

## Phase 6: Discovery Tool Integration

### 6.1 Implement DiscoveryToolAdapter

Complete implementation of `app/Services/Runtime/Adapters/DiscoveryToolAdapter.php`:

**discovery.start operation:**
- Create InterrogationSession via existing InterrogationSessionController logic
- Return session ID for subsequent operations

**discovery.answer operation:**
- Call existing submitAnswer logic
- Return next question or completion status

**discovery.generate_summary operation:**
- Dispatch ExecuteInterrogationSummaryJob
- Return summary when complete

**discovery.generate_plan operation:**
- Dispatch ExecuteInterrogationPlanJob
- Return plan when complete

**discovery.generate_build_tasks operation:**
- Dispatch GenerateInterrogationBuildTasksJob
- Return build tasks when complete

**discovery.start_build operation:**
- Call existing startBuild logic
- Return build execution status

---

## Phase 7: API Layer

### 7.1 Create Runtime API Controller

**Location:** `app/Http/Controllers/Api/V1/Runtime/RuntimeSessionController.php`

Endpoints:
- `GET /agent/api/v1/chat/runtime/sessions` - List user's runtime sessions
- `GET /agent/api/v1/chat/runtime/sessions/{id}` - Session details with turns
- `POST /agent/api/v1/chat/runtime/sessions/{id}/stop` - Terminate session

### 7.2 Create Runtime Tool Call Controller

**Location:** `app/Http/Controllers/Api/V1/Runtime/RuntimeToolCallController.php`

Endpoints:
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/approve` - Approve pending call
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/deny` - Deny pending call

### 7.3 Register API Routes

**Location:** `routes/api.php`

Add runtime routes within the authenticated sanctum group:

```php
// Runtime session endpoints
Route::get('/chat/runtime/sessions', [RuntimeSessionController::class, 'index']);
Route::get('/chat/runtime/sessions/{id}', [RuntimeSessionController::class, 'show']);
Route::post('/chat/runtime/sessions/{id}/stop', [RuntimeSessionController::class, 'stop'])
    ->middleware('throttle:agent-mutations');

// Runtime tool call endpoints
Route::post('/chat/runtime/tool-calls/{id}/approve', [RuntimeToolCallController::class, 'approve'])
    ->middleware('throttle:agent-mutations');
Route::post('/chat/runtime/tool-calls/{id}/deny', [RuntimeToolCallController::class, 'deny'])
    ->middleware('throttle:agent-mutations');
```

---

## Phase 8: Web UI Layer

### 8.1 Create Runtime Sessions Page

**Location:** `resources/js/Pages/Messenger/Runtime/Index.vue`

Features:
- List active runtime sessions for current user
- Show session status, mode, tool calls pending approval
- Link to session detail view
- Stop session action

### 8.2 Create Runtime Session Detail Page

**Location:** `resources/js/Pages/Messenger/Runtime/Show.vue`

Features:
- Display session turns with tool call history
- Show pending approvals with approve/deny buttons
- Display artifacts (screenshots, files)
- Mode change controls
- Stop session action

### 8.3 Create Runtime Settings Page

**Location:** `resources/js/Pages/Messenger/Runtime/Settings.vue`

Features:
- Configure default execution mode
- Configure audit archive threshold
- View browser sidecar status
- Configure MCP enabled state

### 8.4 Register Web Routes

**Location:** `routes/web.php`

Add runtime routes:

```php
Route::get('/messenger/runtime', function () {
    return Inertia::render('Messenger/Runtime/Index');
})->name('messenger.runtime.index');

Route::get('/messenger/runtime/{id}', function (string $id) {
    return Inertia::render('Messenger/Runtime/Show', ['sessionId' => $id]);
})->name('messenger.runtime.show');

Route::get('/messenger/runtime/settings', function () {
    return Inertia::render('Messenger/Runtime/Settings');
})->name('messenger.runtime.settings');
```

### 8.5 Add Navigation Entry

**Location:** `resources/js/Layouts/AppLayout.vue` (or equivalent navigation component)

Add "Runtime Sessions" link under Messenger section in navigation.

---

## Phase 9: Event System

### 9.1 Create Runtime Events

**Location:** `app/Events/Runtime/`

**Files to create:**
- `RuntimeSessionStarted.php`
- `RuntimeSessionEnded.php`
- `RuntimeTurnStarted.php`
- `RuntimeTurnCompleted.php`
- `RuntimeTurnFailed.php`
- `RuntimeToolCallRequested.php`
- `RuntimeToolCallApproved.php`
- `RuntimeToolCallDenied.php`
- `RuntimeToolCallCompleted.php`
- `RuntimeToolCallFailed.php`
- `RuntimeArtifactCreated.php`
- `RuntimePolicySnapshotCaptured.php`

### 9.2 Register Broadcast Channels

**Location:** `routes/channels.php`

```php
Broadcast::channel('runtime.session.{sessionId}', function ($user, $sessionId) {
    $session = RuntimeSession::find($sessionId);
    return $session && $session->user_id === $user->id;
});
```

---

## Phase 10: Audit and Archival

### 10.1 Create Audit Log Archiver

**Location:** `app/Services/Runtime/AuditLogArchiver.php`

Responsibilities:
- Identify audit events older than configurable threshold
- Migrate to cold storage (S3 Glacier or equivalent)
- Maintain index for retrieval
- Clean up migrated records from hot storage

### 10.2 Create Archive Scheduler Command

**Location:** `app/Console/Commands/ArchiveRuntimeAuditLogsCommand.php`

Artisan command to run archival:
- `php artisan runtime:archive-audit-logs`
- Scheduled daily via Laravel scheduler

### 10.3 Update Kernel Schedule

**Location:** `app/Console/Kernel.php`

Add daily schedule for audit log archival:

```php
$schedule->command('runtime:archive-audit-logs')->daily();
```

---

## Phase 11: MCP Integration (Feature-Flagged)

### 11.1 Create MCP Process Manager

**Location:** `app/Services/Runtime/Mcp/McpProcessManager.php`

Responsibilities:
- Spawn MCP server processes via stdio transport
- Manage process lifecycle
- Handle JSON-RPC communication
- Health checks and restarts

### 11.2 Implement McpToolAdapter

Complete implementation of `app/Services/Runtime/Adapters/McpToolAdapter.php`:
- Register MCP tools from server discovery
- Route tool calls to appropriate MCP server
- Enforce policy at adapter boundary
- Parse and return results

### 11.3 Create MCP Configuration

Add to `config/runtime.php`:

```php
'mcp' => [
    'enabled' => env('MCP_ENABLED', false),
    'transport' => 'stdio',
    'servers' => [
        // Server configurations loaded from environment or database
    ],
    'discovery_on_boot' => true,
    'health_check_interval' => 30,
],
```

---

## Phase 12: Testing

### 12.1 Unit Tests

**Location:** `tests/Unit/Runtime/`

**Files to create:**
- `RuntimeSessionManagerTest.php`
- `ToolGatewayTest.php`
- `PolicyEngineTest.php`
- `ApprovalGateTest.php`
- `CommandRouterTest.php`
- `FsToolAdapterTest.php`
- `RuntimeToolAdapterTest.php`
- `WebToolAdapterTest.php`
- `BrowserToolAdapterTest.php`
- `DiscoveryToolAdapterTest.php`
- `RuntimeEventStreamerTest.php`

### 12.2 Feature Tests

**Location:** `tests/Feature/Runtime/`

**Files to create:**
- `RuntimeSessionApiTest.php`
- `RuntimeApprovalFlowTest.php`
- `SlashCommandsTest.php`
- `RuntimeModeEnforcementTest.php`
- `WorkspaceBoundaryTest.php`
- `ConcurrentSessionLimitTest.php`
- `AuditLogArchivalTest.php`

### 12.3 Integration Tests

**Location:** `tests/Feature/Runtime/Integration/`

**Files to create:**
- `MessengerRuntimeE2ETest.php` - Full flow from messenger to tool execution
- `BrowserSidecarIntegrationTest.php` - agent-browser wrapper integration
- `DiscoveryToolIntegrationTest.php` - Discovery workflow via runtime

---

## Acceptance Criteria Verification

### Functional Acceptance

1. **Cross-domain task execution:** User can request tasks spanning fs + web + runtime tools from messenger with appropriate approvals enforced
2. **Router behavior:** CommandRouter handles all slash commands; AgentRouter delegates free-form prompts
3. **Session persistence:** Runtime sessions persist in app/Models/Runtime/ tables with full turn and tool call history
4. **Browser execution:** Browser tasks execute through agent-browser sidecar with configurable persistence
5. **Approval enforcement:** ApprovalGate blocks mutations in standard mode and mutations+external in full mode until explicit approval
6. **Safe mode blocking:** Safe mode rejects write/mutation operations without approval prompt (hard block)
7. **Policy snapshots:** Captured at session start and mode changes, stored in runtime_policy_snapshots
8. **Discovery integration:** DiscoveryToolAdapter exposes all 6 operations
9. **Concurrent limits:** Enforced per connector account configuration (default 3)
10. **Session lifecycle:** Sessions remain active until explicit /stop or API termination
11. **Audit trail:** Links message → turn → tool calls → approvals → outputs with indefinite retention
12. **Archival:** Audit logs migrate to cold storage after configurable threshold
13. **MCP access:** McpToolAdapter accessible via stdio when MCP_ENABLED=true
14. **Approval UX:** Interactive buttons on Slack/Discord, hybrid fallback on other providers
15. **Workspace boundaries:** Enforced per-session via workspace_root with path validation
16. **Slash commands:** All commands functional from Phase 0 including /browser start|stop|reset

### UI Discoverability

1. **Runtime Sessions page** accessible via `/messenger/runtime` with navigation entry in Messenger section
2. **Session Detail page** accessible via `/messenger/runtime/{id}` with approve/deny buttons
3. **Runtime Settings page** accessible via `/messenger/runtime/settings` for configuration
4. **Approval buttons** rendered inline in Slack/Discord with text fallback for other providers

---

## Dependency Order

1. Phase 1 (Foundation) - No dependencies
2. Phase 2 (Tool Gateway) - Depends on Phase 1
3. Phase 3 (Routers) - Depends on Phase 2
4. Phase 4 (Orchestration) - Depends on Phase 2, Phase 3
5. Phase 5 (Browser) - Depends on Phase 2, Phase 4
6. Phase 6 (Discovery) - Depends on Phase 2, Phase 4
7. Phase 7 (API) - Depends on Phase 1, Phase 4
8. Phase 8 (Web UI) - Depends on Phase 7
9. Phase 9 (Events) - Depends on Phase 1, Phase 4
10. Phase 10 (Archival) - Depends on Phase 1
11. Phase 11 (MCP) - Depends on Phase 2, Phase 4
12. Phase 12 (Testing) - Parallel with all phases

## Sections

- Phase 1: Runtime Domain Foundation
- Phase 2: Tool Gateway Infrastructure
- Phase 3: Router Architecture
- Phase 4: Runtime Orchestration
- Phase 5: Browser Integration
- Phase 6: Discovery Tool Integration
- Phase 7: API Layer
- Phase 8: Web UI Layer
- Phase 9: Event System
- Phase 10: Audit and Archival
- Phase 11: MCP Integration (Feature-Flagged)
- Phase 12: Testing
- Acceptance Criteria Verification
- Dependency Order


## Risks

- Browser sidecar process management complexity: agent-browser may fail silently or produce unexpected output; mitigation via health checks, strict output parsing, and restart policies
- Prompt injection via web/browser content: fetched content could manipulate agent behavior; mitigation via content isolation, output sanitization, and requiring confirmation for side effects from fetched data
- Backward compatibility with existing ChatActionType handlers: new routing logic must not break existing job/run commands; mitigation via explicit precedence in ProcessChatIntent and comprehensive regression tests
- Policy enforcement gaps: complex tool chains may bypass approval gates; mitigation via gateway-level enforcement with deny-by-default and explicit approval audit trail
- Concurrent session limit enforcement race conditions: parallel requests may exceed limits; mitigation via database-level locking and atomic session creation
- MCP stdio server stability: local processes may crash or hang; mitigation via timeout handling, health checks, and graceful degradation when MCP unavailable
- Audit log archival data loss: cold storage migration may lose data; mitigation via two-phase commit pattern and verification before deletion
- Interactive button provider API changes: Slack/Discord may deprecate button APIs; mitigation via abstraction layer and text keyword fallback always available


## Assumptions

- agent-browser binary is available at configured path and supports JSON output mode
- Existing messenger Horizon supervisor has sufficient capacity for runtime tool jobs without new supervisor topology
- PostgreSQL jsonb type supports all required runtime configuration and result storage patterns
- Existing InterrogationSession API methods (submitAnswer, confirmSummary, generatePlan, etc.) are stable and can be wrapped by DiscoveryToolAdapter
- Users with messenger identity links have sufficient permissions for runtime session creation
- Cold storage system (S3 Glacier or equivalent) is available for audit log archival
- MCP server implementations follow the MCP stdio transport specification
- Laravel Reverb or equivalent WebSocket infrastructure is available for real-time event streaming to web UI
- Workspace root validation can reuse existing allowed_working_directory_bases and allowed_task_markdown_bases from config/agent.php

