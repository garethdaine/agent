# Implementation Plan

Derived from discovery session 19.

# Messenger Agent Runtime Expansion Implementation Plan

## Phase 0: Foundations — Data Model and Command Router Split

### 0.1 Runtime Domain Schema and Models

**Migrations**
- Create `database/migrations/YYYY_MM_DD_HHMMSS_create_runtime_sessions_table.php`
  - Columns: `id` (uuid PK), `chat_session_id` (uuid nullable FK to chat_sessions), `user_id` (uuid FK to users), `status` (varchar enum: pending, active, completed, failed, stopped), `mode` (varchar enum: safe, standard, full), `title` (varchar nullable), `workspace_root` (varchar nullable), `browser_persistence_mode` (varchar enum: ephemeral, persistent), `started_at` (timestamp), `ended_at` (timestamp nullable)
  - Indexes: `(user_id, status)`, `(chat_session_id)`, `(status, started_at)`

- Create `database/migrations/YYYY_MM_DD_HHMMSS_create_runtime_turns_table.php`
  - Columns: `id` (uuid PK), `runtime_session_id` (uuid FK cascade), `sequence` (integer), `input_message_id` (uuid nullable), `output_message_id` (uuid nullable), `status` (varchar enum: pending, running, completed, failed), `summary` (text nullable), `created_at`, `updated_at`
  - Indexes: `(runtime_session_id, sequence)`, `(status)`
  - Unique constraint: `(runtime_session_id, sequence)`

- Create `database/migrations/YYYY_MM_DD_HHMMSS_create_runtime_tool_calls_table.php`
  - Columns: `id` (uuid PK), `runtime_turn_id` (uuid FK cascade), `tool_name` (varchar), `arguments_json` (jsonb), `result_json` (jsonb nullable), `status` (varchar enum: pending_approval, approved, denied, running, completed, failed), `duration_ms` (integer nullable), `requires_approval` (boolean), `approved_at` (timestamp nullable), `created_at`, `updated_at`
  - Indexes: `(runtime_turn_id)`, `(status)`, `(tool_name, status)`

- Create `database/migrations/YYYY_MM_DD_HHMMSS_create_runtime_approvals_table.php`
  - Columns: `id` (uuid PK), `runtime_tool_call_id` (uuid FK cascade unique), `requested_by` (uuid FK users), `state` (varchar enum: pending, approved, denied, expired), `decision_by` (uuid nullable FK users), `decision_reason` (text nullable), `expires_at` (timestamp nullable), `created_at`, `updated_at`
  - Indexes: `(state, expires_at)`, `(requested_by)`

- Create `database/migrations/YYYY_MM_DD_HHMMSS_create_runtime_artifacts_table.php`
  - Columns: `id` (uuid PK), `runtime_session_id` (uuid FK cascade), `type` (varchar: file, screenshot, log, report), `path` (varchar), `metadata_json` (jsonb nullable), `created_at`
  - Indexes: `(runtime_session_id, type)`

- Create `database/migrations/YYYY_MM_DD_HHMMSS_create_runtime_policy_snapshots_table.php`
  - Columns: `id` (uuid PK), `runtime_session_id` (uuid FK cascade), `snapshot_reason` (varchar enum: session_start, mode_change), `policy_json` (jsonb), `captured_at` (timestamp)
  - Indexes: `(runtime_session_id, captured_at)`

**Models (app/Models/Runtime/)**
- Create `RuntimeSession.php` with HasUuids, relationships to RuntimeTurn, RuntimeArtifact, RuntimePolicySnapshot, User, ChatSession; status constants; scopes for user filtering and status
- Create `RuntimeTurn.php` with HasUuids, relationship to RuntimeSession and RuntimeToolCall; sequence auto-increment logic
- Create `RuntimeToolCall.php` with HasUuids, relationship to RuntimeTurn and RuntimeApproval; status helpers (isPending, isApproved, requiresApproval)
- Create `RuntimeApproval.php` with HasUuids, relationships; state machine helpers
- Create `RuntimeArtifact.php` with HasUuids, relationship to RuntimeSession; type constants
- Create `RuntimePolicySnapshot.php` with HasUuids, relationship to RuntimeSession; immutable enforcement

**Enums (app/Enums/Runtime/)**
- Create `RuntimeSessionStatus.php`: pending, active, completed, failed, stopped
- Create `RuntimeMode.php`: safe, standard, full with capability methods
- Create `RuntimeToolCallStatus.php`: pending_approval, approved, denied, running, completed, failed
- Create `RuntimeApprovalState.php`: pending, approved, denied, expired
- Create `PolicySnapshotReason.php`: session_start, mode_change

### 0.2 Configuration

**Create config/runtime.php**
```php
return [
    'default_mode' => 'safe',
    'approval_model' => 'strict',
    'modes' => [...],
    'concurrent_session_limit_default' => 3,
    'session_timeout' => null,
    'audit_archive_after_days' => env('RUNTIME_AUDIT_ARCHIVE_DAYS', 30),
    'browser' => [...],
    'mcp' => ['enabled' => env('MCP_ENABLED', false), 'transport' => 'stdio'],
    'policy_snapshot_triggers' => ['session_start', 'mode_change'],
    'approval_ux' => ['interactive_providers' => ['slack', 'discord'], 'fallback_mode' => 'hybrid'],
];
```

**Extend config/messenger.php connector account schema**
- Add `runtime_settings` key with `concurrent_session_limit`, `group_channel_max_mode`, `full_mode_requires_reauth`

### 0.3 Command Router Implementation

**Create app/Services/Messenger/CommandRouter.php**
- Parse slash commands: `/status`, `/runs`, `/stop`, `/mode`, `/approve`, `/deny`, `/browser`
- Return `ParsedCommand` DTO with command type, arguments, validation errors
- Methods: `parse(string $content): ?ParsedCommand`, `isCommand(string $content): bool`
- Pattern matching for `/command [args]` format

**Create app/Services/Messenger/AgentRouter.php**
- Accept free-form prompts that aren't slash commands
- Return routing decision to delegate to `MessengerRuntimeOrchestrator`
- Methods: `shouldHandle(string $content): bool`, `route(ChatMessage $message, ChatSession $session): AgentRoutingResult`

**Create app/DTOs/Messenger/ParsedCommand.php**
- Properties: `commandType` (enum), `arguments` (array), `rawInput` (string), `validationErrors` (array)

**Create app/Enums/Messenger/SlashCommandType.php**
- Cases: STATUS, RUNS, STOP, MODE, APPROVE, DENY, BROWSER

**Modify app/Jobs/Messenger/ProcessChatIntent.php**
- Insert command router check before intent parsing
- If CommandRouter matches, dispatch to command handler instead of intent parser
- If no command match and no intent match, delegate to AgentRouter for runtime turn

### 0.4 Slash Command Handlers

**Create app/Messenger/SlashCommand/Handlers directory**
- Create `SlashCommandHandlerInterface.php` with `handle(ParsedCommand $command, ChatSession $session, User $user): SlashCommandResult`
- Create `StatusHandler.php`: Query active runtime sessions, current mode, tool state
- Create `RunsHandler.php`: List active runtime sessions for user with summary info
- Create `StopHandler.php`: Stop specified session by ID or current session; validate ownership
- Create `ModeHandler.php`: Change execution mode with policy snapshot capture
- Create `ApproveHandler.php`: Approve pending tool call by ID; validate ownership and state
- Create `DenyHandler.php`: Deny pending tool call by ID with optional reason
- Create `BrowserHandler.php`: start/stop/reset browser sidecar for current session

**Create app/DTOs/Messenger/SlashCommandResult.php**
- Properties: `success`, `message`, `data`, `requiresStreaming`

### 0.5 Foundation Tests

- Create `tests/Unit/Runtime/RuntimeSessionTest.php`: Model instantiation, relationships, status transitions
- Create `tests/Unit/Runtime/RuntimeModeTest.php`: Capability checks per mode
- Create `tests/Feature/Messenger/CommandRouterTest.php`: Command parsing for all slash commands
- Create `tests/Feature/Messenger/SlashCommand/StatusHandlerTest.php`
- Create `tests/Feature/Messenger/SlashCommand/RunsHandlerTest.php`
- Create `tests/Feature/Messenger/SlashCommand/StopHandlerTest.php`
- Create `tests/Feature/Messenger/SlashCommand/ModeHandlerTest.php`
- Create `tests/Feature/Messenger/SlashCommand/ApproveHandlerTest.php`
- Create `tests/Feature/Messenger/SlashCommand/DenyHandlerTest.php`
- Create `tests/Feature/Messenger/SlashCommand/BrowserHandlerTest.php`

---

## Phase 1: Tool Gateway and Policy Engine

### 1.1 Tool Adapter Interface and Base Contracts

**Create app/Contracts/Runtime/ToolAdapterInterface.php**
```php
interface ToolAdapterInterface {
    public function name(): string;
    public function schema(): array;
    public function authorize(RuntimeContext $context, array $args): AuthorizationResult;
    public function execute(RuntimeContext $context, array $args): ToolResult;
}
```

**Create app/DTOs/Runtime/RuntimeContext.php**
- Properties: `runtimeSession`, `runtimeTurn`, `user`, `mode`, `workspaceRoot`, `policySnapshot`

**Create app/DTOs/Runtime/AuthorizationResult.php**
- Properties: `authorized` (bool), `requiresApproval` (bool), `denialReason` (string nullable), `approvalType` (string nullable)

**Create app/DTOs/Runtime/ToolResult.php**
- Properties: `success` (bool), `data` (mixed), `error` (string nullable), `artifacts` (array), `durationMs` (int)

### 1.2 Policy Engine

**Create app/Services/Runtime/PolicyEngine.php**
- Load policy from session's connector account config merged with runtime config
- Evaluate tool authorization against mode capabilities
- Methods:
  - `evaluateToolAuthorization(ToolAdapterInterface $adapter, RuntimeContext $context, array $args): AuthorizationResult`
  - `getModeCapabilities(RuntimeMode $mode): array`
  - `isToolAllowedInMode(string $toolName, RuntimeMode $mode): bool`
  - `getApprovalRequirements(string $toolName, RuntimeMode $mode): array`

**Create app/Services/Runtime/PolicySnapshotService.php**
- Capture current policy state as immutable snapshot
- Methods:
  - `captureSnapshot(RuntimeSession $session, PolicySnapshotReason $reason): RuntimePolicySnapshot`
  - `getActiveSnapshot(RuntimeSession $session): ?RuntimePolicySnapshot`

### 1.3 Approval Gate

**Create app/Services/Runtime/ApprovalGate.php**
- Create approval requests for tool calls requiring approval
- Process approval/denial decisions
- Methods:
  - `requestApproval(RuntimeToolCall $toolCall, User $requestedBy): RuntimeApproval`
  - `approve(RuntimeApproval $approval, User $decisionBy, ?string $reason): void`
  - `deny(RuntimeApproval $approval, User $decisionBy, ?string $reason): void`
  - `isApprovalRequired(RuntimeToolCall $toolCall, RuntimeContext $context): bool`
  - `expireStaleApprovals(): int`

### 1.4 Tool Gateway

**Create app/Services/Runtime/ToolGateway.php**
- Registry of tool adapters
- Route tool calls through policy engine and approval gate
- Methods:
  - `registerAdapter(ToolAdapterInterface $adapter): void`
  - `getAdapter(string $toolName): ?ToolAdapterInterface`
  - `executeToolCall(string $toolName, array $args, RuntimeContext $context): ToolResult`
  - `listAvailableTools(RuntimeContext $context): array`

**Create app/Providers/RuntimeServiceProvider.php**
- Register tool adapters in service container
- Bind ToolGateway as singleton
- Register policy engine and approval gate

### 1.5 Core Tool Adapters (Safe Subset)

**Create app/Services/Runtime/Adapters/FsToolAdapter.php**
- Operations: read_file, list_directory, search_files (safe subset)
- Workspace boundary validation via `workspace_root`
- Path allowlist enforcement
- Implements `ToolAdapterInterface`

**Create app/Services/Runtime/Adapters/RuntimeToolAdapter.php**
- Operations: run_command (safe subset: read-only commands like `ls`, `cat`, `grep`)
- Command allowlist enforcement
- Output capture and truncation
- Implements `ToolAdapterInterface`

**Create app/Services/Runtime/Adapters/WebToolAdapter.php**
- Operations: web_search, fetch_url, summarize_content
- Content isolation (treat fetched content as untrusted data)
- Rate limiting per session
- Implements `ToolAdapterInterface`

### 1.6 Phase 1 Tests

- Create `tests/Unit/Runtime/PolicyEngineTest.php`: Mode capability evaluation, tool authorization
- Create `tests/Unit/Runtime/ApprovalGateTest.php`: Approval lifecycle, state transitions
- Create `tests/Unit/Runtime/ToolGatewayTest.php`: Adapter registration, routing
- Create `tests/Feature/Runtime/FsToolAdapterTest.php`: Read operations, boundary enforcement
- Create `tests/Feature/Runtime/RuntimeToolAdapterTest.php`: Command execution, allowlist
- Create `tests/Feature/Runtime/WebToolAdapterTest.php`: Search, fetch, content isolation

---

## Phase 2: Runtime Orchestration and Session Management

### 2.1 Runtime Session Manager

**Create app/Services/Runtime/RuntimeSessionManager.php**
- Create new runtime sessions with policy snapshot
- Enforce concurrent session limits per connector account
- Session lifecycle management
- Methods:
  - `createSession(ChatSession $chatSession, User $user, RuntimeMode $mode, ?string $workspaceRoot): RuntimeSession`
  - `getActiveSession(ChatSession $chatSession): ?RuntimeSession`
  - `stopSession(RuntimeSession $session, User $user): void`
  - `changeMode(RuntimeSession $session, RuntimeMode $newMode): void`
  - `countActiveSessions(User $user, ?string $connectorAccountId): int`

### 2.2 Runtime Orchestrator

**Create app/Services/Runtime/MessengerRuntimeOrchestrator.php**
- Coordinate runtime turns for free-form prompts
- Plan tool selection based on prompt and context
- Execute tool chains with approval gates
- Methods:
  - `processPrompt(ChatMessage $message, RuntimeSession $session): RuntimeTurn`
  - `planTools(string $prompt, RuntimeContext $context): array`
  - `executeTurn(RuntimeTurn $turn): void`
  - `handleApprovalDecision(RuntimeApproval $approval, bool $approved, ?string $reason): void`

### 2.3 Response Streaming

**Create app/Services/Runtime/RuntimeEventStreamer.php**
- Chunked response streaming to messenger providers
- Progress updates during long-running operations
- Methods:
  - `startStream(RuntimeTurn $turn, ChatSession $session): void`
  - `sendChunk(RuntimeTurn $turn, string $content): void`
  - `sendToolProgress(RuntimeToolCall $toolCall, string $status): void`
  - `finalizeStream(RuntimeTurn $turn, string $summary): void`

**Modify app/Support/Messenger/Adapters/SlackAdapter.php**
- Add streaming support via message updates
- Implement interactive button components for approvals

**Modify app/Support/Messenger/Adapters/DiscordAdapter.php**
- Add streaming support via message edits
- Implement button components for approvals

**Modify app/Support/Messenger/Adapters/TelegramAdapter.php**
- Add streaming support via message edits
- Implement inline keyboard for approvals

**Modify app/Support/Messenger/Adapters/WhatsAppAdapter.php**
- Add streaming support via message edits
- Text-based approval fallback (no interactive components)

### 2.4 Event System

**Create app/Events/Runtime/RuntimeSessionStarted.php**
**Create app/Events/Runtime/RuntimeSessionEnded.php**
**Create app/Events/Runtime/RuntimeTurnStarted.php**
**Create app/Events/Runtime/RuntimeTurnCompleted.php**
**Create app/Events/Runtime/RuntimeTurnFailed.php**
**Create app/Events/Runtime/RuntimeToolCallRequested.php**
**Create app/Events/Runtime/RuntimeToolCallApproved.php**
**Create app/Events/Runtime/RuntimeToolCallDenied.php**
**Create app/Events/Runtime/RuntimeToolCallCompleted.php**
**Create app/Events/Runtime/RuntimeToolCallFailed.php**
**Create app/Events/Runtime/RuntimeArtifactCreated.php**
**Create app/Events/Runtime/RuntimePolicySnapshotCaptured.php**

### 2.5 Phase 2 Tests

- Create `tests/Feature/Runtime/RuntimeSessionManagerTest.php`: Session lifecycle, concurrent limits
- Create `tests/Feature/Runtime/MessengerRuntimeOrchestratorTest.php`: Prompt processing, tool execution
- Create `tests/Feature/Runtime/RuntimeEventStreamerTest.php`: Streaming to providers
- Create `tests/Feature/Runtime/CrossDomainTaskTest.php`: End-to-end fs + web + runtime task

---

## Phase 3: Browser Lane Integration

### 3.1 Browser Sidecar Manager

**Create app/Services/Runtime/BrowserSidecarManager.php**
- Manage agent-browser process lifecycle
- Health checks and restart policies
- Session/profile mapping per runtime session
- Methods:
  - `start(RuntimeSession $session): bool`
  - `stop(RuntimeSession $session): void`
  - `reset(RuntimeSession $session): void`
  - `isRunning(RuntimeSession $session): bool`
  - `healthCheck(): array`
  - `executeCommand(RuntimeSession $session, string $command, array $args): BrowserCommandResult`

**Create app/DTOs/Runtime/BrowserCommandResult.php**
- Properties: `success`, `output`, `screenshot` (base64 nullable), `errors`, `durationMs`

### 3.2 Browser Tool Adapter

**Create app/Services/Runtime/Adapters/BrowserToolAdapter.php**
- Allowed commands: navigate, click, type, screenshot, extract
- Strict wrapper preventing arbitrary flag injection
- Session persistence configuration per runtime session
- Artifact capture for screenshots
- Implements `ToolAdapterInterface`
- Methods:
  - `navigate(RuntimeContext $context, string $url): ToolResult`
  - `click(RuntimeContext $context, string $selector): ToolResult`
  - `type(RuntimeContext $context, string $selector, string $text): ToolResult`
  - `screenshot(RuntimeContext $context, ?string $selector): ToolResult`
  - `extract(RuntimeContext $context, string $selector): ToolResult`

### 3.3 Browser Configuration

**Update config/runtime.php browser section**
```php
'browser' => [
    'sidecar_binary' => env('AGENT_BROWSER_PATH', '/usr/local/bin/agent-browser'),
    'default_persistence' => 'ephemeral',
    'allowed_commands' => ['navigate', 'click', 'type', 'screenshot', 'extract'],
    'session_timeout_seconds' => 300,
    'viewport' => ['width' => 1280, 'height' => 720],
    'user_agent' => env('AGENT_BROWSER_USER_AGENT'),
],
```

### 3.4 Phase 3 Tests

- Create `tests/Unit/Runtime/BrowserSidecarManagerTest.php`: Lifecycle, health checks
- Create `tests/Feature/Runtime/BrowserToolAdapterTest.php`: All allowed commands
- Create `tests/Feature/Runtime/BrowserTaskEndToEndTest.php`: Navigate, act, capture flow

---

## Phase 4: Discovery and Agent API Integration

### 4.1 Discovery Tool Adapter

**Create app/Services/Runtime/Adapters/DiscoveryToolAdapter.php**
- Full workflow operations:
  - `discovery.start` - Initialize requirements discovery for a feature
  - `discovery.answer` - Submit answer to discovery question
  - `discovery.generate_summary` - Generate feature summary
  - `discovery.generate_plan` - Generate implementation plan
  - `discovery.generate_build_tasks` - Generate build task breakdown
  - `discovery.start_build` - Initiate build execution
- Bridge to existing InterrogationSession infrastructure
- Implements `ToolAdapterInterface`

**Create app/Services/Runtime/DiscoveryBridge.php**
- Translate between runtime tool calls and interrogation session API
- State synchronization between runtime session and interrogation session
- Methods:
  - `createInterrogationSession(RuntimeSession $session, string $featureBrief): InterrogationSession`
  - `submitAnswer(InterrogationSession $interrogation, string $questionId, string $answer): void`
  - `generateSummary(InterrogationSession $interrogation): string`
  - `generatePlan(InterrogationSession $interrogation): string`
  - `generateBuildTasks(InterrogationSession $interrogation): array`
  - `startBuild(InterrogationSession $interrogation): void`

### 4.2 Agent API Tool Adapter

**Create app/Services/Runtime/Adapters/AgentApiToolAdapter.php**
- Wrap existing internal Agent API operations as tool calls
- Operations: list_jobs, run_job, stop_run, list_runs, get_run_status
- Per-user authorization and ownership checks
- Compact summaries by default
- Implements `ToolAdapterInterface`

### 4.3 Phase 4 Tests

- Create `tests/Feature/Runtime/DiscoveryToolAdapterTest.php`: All 6 discovery operations
- Create `tests/Feature/Runtime/AgentApiToolAdapterTest.php`: Job/run operations
- Create `tests/Feature/Runtime/DiscoveryWorkflowEndToEndTest.php`: Full discovery via messenger

---

## Phase 5: MCP Integration

### 5.1 MCP Tool Adapter

**Create app/Services/Runtime/Adapters/McpToolAdapter.php**
- Feature-flagged via `MCP_ENABLED` env variable
- Local stdio server management
- Tool registration from MCP server capabilities
- Argument validation against MCP schemas
- Implements `ToolAdapterInterface`

**Create app/Services/Runtime/McpServerManager.php**
- Manage MCP stdio server processes
- Server discovery and capability negotiation
- Methods:
  - `startServer(string $serverName): Process`
  - `stopServer(string $serverName): void`
  - `listCapabilities(string $serverName): array`
  - `callTool(string $serverName, string $toolName, array $args): mixed`

**Update config/runtime.php MCP section**
```php
'mcp' => [
    'enabled' => env('MCP_ENABLED', false),
    'transport' => 'stdio',
    'servers' => [
        // Server configurations loaded from separate config or discovery
    ],
    'tool_allowlist' => [],
    'tool_denylist' => [],
],
```

### 5.2 Phase 5 Tests

- Create `tests/Unit/Runtime/McpServerManagerTest.php`: Server lifecycle
- Create `tests/Feature/Runtime/McpToolAdapterTest.php`: Tool calls via MCP (with mock server)

---

## Phase 6: API and Web UI Extensions

### 6.1 API Controllers

**Create app/Http/Controllers/Api/V1/Runtime/RuntimeSessionController.php**
- `GET /agent/api/v1/chat/runtime/sessions` - List user's runtime sessions
- `GET /agent/api/v1/chat/runtime/sessions/{id}` - Session details with turns
- `POST /agent/api/v1/chat/runtime/sessions/{id}/stop` - Terminate session
- Authorization via auth:sanctum, user ownership checks

**Create app/Http/Controllers/Api/V1/Runtime/RuntimeToolCallController.php**
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/approve` - Approve pending call
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/deny` - Deny pending call
- Ownership validation, state machine enforcement

**Update routes/api.php**
- Add runtime session routes under `/agent/api/v1/chat/runtime/` prefix
- Apply auth:sanctum and throttle:agent-mutations middleware

### 6.2 Web UI Components

**Create resources/js/Pages/Tools/Runtime/Index.vue**
- List runtime sessions with status, mode, creation time
- Filter by status (active, completed, failed, stopped)
- Link to session detail view
- Route: `/tools/runtime`

**Create resources/js/Pages/Tools/Runtime/Show.vue**
- Session detail with turn history
- Tool call list with status indicators
- Pending approval cards with approve/deny buttons
- Artifact gallery (screenshots, files)
- Route: `/tools/runtime/{id}`

**Create resources/js/Components/Runtime/ApprovalCard.vue**
- Display pending approval details
- Approve/deny buttons with reason input
- Real-time status updates via polling

**Create resources/js/Components/Runtime/ToolCallTimeline.vue**
- Status badges, duration display
- Expandable argument/result details

**Update resources/js/Layouts/AppLayout.vue**
- Add "Runtime" link under Tools navigation
- Badge for pending approvals count

**Update resources/js/Pages/Tools/Index.vue**
- Add Runtime card with session count and active status

### 6.3 Navigation and Discoverability

**Update routes/web.php**
- Add runtime index route: `Route::get('/tools/runtime', [RuntimeController::class, 'index'])->name('runtime.index')`
- Add runtime show route: `Route::get('/tools/runtime/{id}', [RuntimeController::class, 'show'])->name('runtime.show')`

**Create app/Http/Controllers/RuntimeController.php**
- Inertia controller for web UI routes
- Pass session data and approval counts to views

### 6.4 Phase 6 Tests

- Create `tests/Feature/Api/V1/Runtime/RuntimeSessionControllerTest.php`: All API endpoints
- Create `tests/Feature/Api/V1/Runtime/RuntimeToolCallControllerTest.php`: Approval endpoints
- Create `tests/Feature/Runtime/WebUiAccessibilityTest.php`: Route accessibility, navigation presence

---

## Phase 7: Audit and Archival

### 7.1 Audit Log Archiver

**Create app/Services/Runtime/AuditLogArchiver.php**
- Archive aged audit events to cold storage
- Configurable threshold from `config/runtime.php`
- Methods:
  - `archiveOldEvents(int $olderThanDays = null): int`
  - `getArchiveStats(): array`

**Create app/Console/Commands/ArchiveRuntimeAuditLogsCommand.php**
- Artisan command: `runtime:archive-audit-logs`
- Schedule in `app/Console/Kernel.php` (daily)

### 7.2 Settings UI

**Create resources/js/Pages/Tools/Runtime/Settings.vue**
- Configure archive threshold (default 30 days)
- View archive statistics
- Manual archive trigger button
- Route: `/tools/runtime/settings`

**Update app/Http/Controllers/RuntimeController.php**
- Add settings action for runtime settings page

### 7.3 Phase 7 Tests

- Create `tests/Feature/Runtime/AuditLogArchiverTest.php`: Archive threshold, statistics

---

## Phase 8: Hardening and Integration Tests

### 8.1 Security Hardening

**Create app/Services/Runtime/SecurityGuards/WorkspaceBoundaryGuard.php**
- Validate all file paths against workspace_root
- Prevent path traversal attacks
- Symlink resolution and validation

**Create app/Services/Runtime/SecurityGuards/CommandInjectionGuard.php**
- Sanitize command arguments
- Validate against command allowlist
- Escape shell metacharacters

**Create app/Services/Runtime/SecurityGuards/ContentIsolationGuard.php**
- Isolate fetched web/browser content from control flow
- Prevent prompt injection via external content
- Content sanitization before processing

### 8.2 Integration Tests

- Create `tests/Feature/Runtime/EndToEndCrossDomainTest.php`: User requests task spanning fs + web + runtime
- Create `tests/Feature/Runtime/ApprovalFlowTest.php`: Standard mode mutation approval flow
- Create `tests/Feature/Runtime/FullModeSecurityTest.php`: Full mode requires approval for external calls
- Create `tests/Feature/Runtime/SafeModeBlockTest.php`: Safe mode hard-blocks all writes
- Create `tests/Feature/Runtime/GroupChannelSafetyTest.php`: Group channels restricted to safe mode
- Create `tests/Feature/Runtime/ConcurrentSessionLimitTest.php`: Enforce per-connector limits
- Create `tests/Feature/Runtime/PolicySnapshotTest.php`: Snapshots captured at session start and mode change
- Create `tests/Feature/Runtime/AuditTrailLinkageTest.php`: Message → turn → tool call → approval → output chain

### 8.3 Acceptance Tests

- Create `tests/Feature/Runtime/Acceptance/MessengerTaskCompletionTest.php`: User completes cross-domain task from messenger
- Create `tests/Feature/Runtime/Acceptance/CommandRouterPrecedenceTest.php`: Commands take precedence over free-form
- Create `tests/Feature/Runtime/Acceptance/BrowserArtifactReturnTest.php`: Browser tasks return artifacts
- Create `tests/Feature/Runtime/Acceptance/DiscoveryFullWorkflowTest.php`: All 6 discovery operations via messenger
- Create `tests/Feature/Runtime/Acceptance/HybridApprovalFallbackTest.php`: Text keywords work on non-button providers
- Create `tests/Feature/Runtime/Acceptance/WebUiNavigationTest.php`: Runtime link visible in nav, sessions accessible

## Sections

- Phase 0: Foundations — Data Model and Command Router Split
- Phase 1: Tool Gateway and Policy Engine
- Phase 2: Runtime Orchestration and Session Management
- Phase 3: Browser Lane Integration
- Phase 4: Discovery and Agent API Integration
- Phase 5: MCP Integration
- Phase 6: API and Web UI Extensions
- Phase 7: Audit and Archival
- Phase 8: Hardening and Integration Tests


## Risks

- Browser sidecar dependency: agent-browser version compatibility and availability may cause runtime failures; mitigate with version pinning, health checks, and graceful degradation to non-browser tools
- Prompt injection via fetched content: web/browser content may contain malicious prompts; mitigate with content isolation guard and treating all external content as untrusted data in tool outputs
- Concurrent session resource exhaustion: users creating many sessions may exhaust worker capacity; mitigate with per-connector limits (default 3) and active monitoring
- Approval UX latency: pending approvals may block task completion indefinitely; mitigate with optional expiration and clear user notification of pending state
- Command/intent ambiguity: borderline inputs may be misrouted between CommandRouter and AgentRouter; mitigate with command precedence rule and clear fallback prompts
- MCP server process management complexity: stdio server crashes or hangs may block tool calls; mitigate with timeouts, health checks, and restart policies
- Policy snapshot storage growth: indefinite retention may cause database bloat; mitigate with configurable archival to cold storage
- Group channel safety bypass: misconfigured connector accounts may allow elevated modes in groups; mitigate with default safe-mode restriction and admin-only mode configuration


## Assumptions

- Existing messenger gateway and identity infrastructure (ChatSession, ChatMessage, ConnectorAccount) remains stable and unchanged
- agent-browser binary is available at configured path with compatible CLI interface for navigate/click/type/screenshot/extract commands
- Laravel 12 / PHP 8.3 environment with PostgreSQL and Redis infrastructure as documented in project memory
- Existing Horizon supervisor-messenger queue capacity is sufficient for runtime tool job dispatch without new supervisor topology
- ConnectorAdapterInterface implementations (Slack, Discord, Telegram, WhatsApp) can be extended for interactive buttons and streaming without breaking changes
- MCP stdio servers will follow standard MCP protocol for tool discovery and invocation when MCP_ENABLED=true
- InterrogationSession infrastructure is stable and can be bridged for discovery tool operations
- Web UI uses existing Vue 3 + Inertia.js stack with established component library (ui/Card, ui/Button, etc.)
- Audit log cold storage destination (S3, local archive) is configured externally; archiver only moves records

