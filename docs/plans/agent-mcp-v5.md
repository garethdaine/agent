# Implementation Plan

Derived from discovery session 4.

## Scope Boundary
1. Deliver one Laravel MCP server module (`Agent MCP`) for authenticated machine-to-machine workflows: session lifecycle, discovery, interrogation, summary, plan generation, build-task generation, build start, and event consumption.
2. Keep v1 bounded to local Agent workspace surfaces only; no arbitrary shell, no unrestricted filesystem/resource access, no external writeback integrations.
3. Enforce locked scope model (`tenant`,`environment`,`role`) and permission-claim model (`permission=*`) with fixed evaluation order: scope first, permission second.
4. Preserve existing guardrails: command/path/env policies, ownership, authorization, mutation rate limits, immutable terminal runs, and audit logging.
5. Publish canonical in-repo manifest as source of truth; mirror upstream version metadata only for traceability.

## Architecture Changes
1. Add MCP module namespace under `app/AgentMcp/` with subdomains:
- `Auth/` (`AuthKeyLifecycleService`, key resolver, revocation checks)
- `Contracts/` (`ToolManifestRegistry`, `SchemaValidator`, `ErrorTransportMapper`)
- `Tools/` (one handler per canonical tool)
- `Resources/` (policy/config safe subset, health snapshot, audit summaries, interrogation guidance)
- `Prompts/` (triage failed runs, safe config drafting, interrogation kickoff)
- `Events/` (`EventPollService`, `EventStreamGateway`, `CursorAckService`)
- `Lifecycle/` (`SessionLifecycleService`, `RunTransitionPolicy`, `QuestionQualityFilter`)
2. Register module in a dedicated provider (for example `app/Providers/AgentMcpServiceProvider.php`) and bind interfaces to implementations.
3. Add MCP HTTP entrypoints (for example `routes/mcp.php`) behind Sanctum bearer auth middleware and API version header middleware.
4. Reuse existing app services/actions for all writes; MCP tool handlers act as adapters only and must not duplicate business logic.
5. Add dedicated config file (for example `config/agent_mcp.php`) containing all locked config keys and defaults.

## Data Model and Migrations
1. Introduce or align persisted entities with locked model set:
- `MCPClient`
- `APIKeyScope`
- `PermissionClaim`
- `Session`
- `Run`
- `RunEvent`
- `SummaryArtifact`
- `PlanArtifact`
- `BuildTask`
- `AuditRecord`
- `RetentionAggregateCounter`
2. Add migrations for key lifecycle and scoping:
- client table with status and metadata
- opaque key table (hashed key material, expires_at, disabled_at, rotated_from)
- scope table (`tenant`,`environment`,`role`) with uniqueness constraints
- permission claim table (string claim set)
3. Add migrations for workflow artifacts and event stream correctness:
- `runs` table with immutable terminal state constraints and parent linkage for retry/restart
- `run_events` table with per-run monotonic `seq`, unique `(run_id, seq)`, unique `event_id`
- cursor checkpoint storage for acknowledged cursors
- build task state field including `start_failed`
4. Add retention support:
- aggregate counter table for post-retention summaries
- scheduled cleanup worker state tracking
5. Add indexes for hot paths:
- session and run lookup by tenant/environment
- event polling by `(run_id, seq)`
- audit summaries by `(user_id, created_at)`

## API and Tool Contracts
1. Create canonical manifest file in-repo (for example `resources/agent-mcp/manifest.v1.json`) with exact fields: `tool_name`, `request_schema_version`, `response_schema_version`, `stability`, `aliases`.
2. Implement manifest-backed runtime registry (`ToolManifestRegistry`) so discovery, docs generation, and compatibility checks share one source.
3. Add request/response schemas per tool under versioned paths (for example `resources/agent-mcp/schemas/{tool}/{version}/request.json` and `response.json`).
4. Implement handlers for all locked tools:
- Session tools: `sessions.create/show/list/pause/resume/retry/restart/cleanup`
- Workflow tools: `discovery.start`, `interrogation.answer`, `summary.confirm`, `plan.generate`, `build_tasks.generate`, `build.start`
- Event tools: `events.poll`, `events.stream`
- Admin/control tools: `cleanup_invalid_questions`, `settings.update`, `policy.update`, `config.update`, `admin.destructive.execute`
5. Implement alias resolution with canonical tool dispatch and deprecation metadata in responses where alias is used.
6. Add tool annotations metadata (`readOnly`, `idempotent`, `destructive`, `openWorld`) in registry and server descriptors; validate that annotations match tool behavior.
7. Add MCP resources with safe, scoped payloads:
- policy/config reference (safe subset)
- system health snapshot
- recent user-scoped audit summaries
- interrogation guidance
8. Add MCP prompts:
- failed-run triage prompt
- safe job/session configuration draft prompt
- interrogation kickoff question prompt

## Event Contracts
1. Enforce event transport contract: poll plus WebSocket only.
2. Implement bootstrap and recovery through `events.poll` with cursor token semantics.
3. Implement realtime stream through `events.stream` gateway with heartbeat controls:
- server ping every 25s
- client pong timeout 10s
- disconnect after 2 missed pongs
- reconnect guidance using exponential backoff with jitter capped at 30s
4. Enforce strict ordering per run using monotonic `seq`; reject or quarantine out-of-order writes before publish.
5. Enforce idempotency using `event_id` uniqueness and cursor acknowledgment tracking.
6. Implement stale cursor detection with canonical `STALE_CURSOR` code and `cursor_hint` recovery payload.
7. Implement `cursor_ack(run_id,cursors,client_ts)` resume contract in WS and poll recovery flows.

## Authorization and Scope Enforcement
1. Implement opaque API key auth with Sanctum bearer pipeline and key hashing at rest.
2. Implement key lifecycle behaviors:
- default expiration 90 days
- manual/API rotation
- immediate disable revocation
3. Implement `ScopePermissionEvaluator` with fixed dimension checks (`tenant`,`environment`,`role`) and explicit deny default.
4. Encode per-tool policy matrix exactly as locked, including optional role rules for read/list/stream tools and strict required roles for mutating/admin tools.
5. Allow filter-on-mismatch only for `sessions.list`, `sessions.show`, `events.poll`, `events.stream`, and only within same tenant/environment; deny for role mismatch.
6. Run permission claim checks only after scope success, using separate claim set (for example `permission=build.execute`).
7. Enforce resource ownership checks on every entity lookup before action dispatch.

## Failure and Retry Behavior
1. Implement canonical error mapper (`ErrorTransportMapper`) for stable app codes:
- `AUTH_UNAUTHENTICATED`
- `VALIDATION_FAILED`
- `UNSUPPORTED_TRANSITION`
- `STALE_CURSOR`
- `ENCODING_INVALID_UTF8`
- `SCHEMA_PATH_INVALID`
2. Emit required transport payload fields:
- HTTP RFC7807 + `code`, `request_id`, `retryable`, and `errors` for validation
- WS error frame with `server_ts`, optional `cursor_hint`, optional `run_id`
- Queue failure payload with `job_id`, `attempt`, `max_attempts`, `failed_at`
3. Validate UTF-8 boundary before persistence for interrogation and event payloads; reject invalid input with canonical code.
4. Enforce run transition policy:
- terminal states immutable
- stop/kill on terminal => `UNSUPPORTED_TRANSITION`
- retry/restart always create new run id
- run-level pause/resume unsupported
5. Preserve session-level pause/resume as backward-compatible, explicitly session-scoped behavior.
6. Implement partial success contract for build start failures: persist task as `start_failed`, return `task_id` plus structured retry path.

## Observability and Auditability
1. Propagate `request_id` across HTTP, WS, queue, and persistence layers.
2. Add structured logs keyed by `tool_name`, canonical app code, tenant/environment/role, actor id, session id, run id.
3. Add metrics:
- tool invocation count/latency by canonical tool
- auth failures and scope-deny counts
- stale cursor recoveries
- UTF-8 reject counts
- schema-path validation failures
4. Ensure every mutating operation writes immutable audit records through existing audit paths.
5. Expose user-scoped recent audit summary resource with safe redaction policy.
6. Add health snapshot resource combining scheduler/queue/ws readiness and retention worker status.

## Test Strategy
1. Unit tests:
- `ScopePermissionEvaluator` matrix enforcement
- `SchemaValidator` path/version validation
- `RunTransitionPolicy` immutability and allowed transitions
- `QuestionQualityFilter` balanced rejection profile
- `ErrorTransportMapper` transport field completeness
2. Feature tests for each canonical tool:
- success path
- validation failure path
- authorization denied path
- annotation correctness and schema conformance
3. Integration tests for event system:
- poll bootstrap/recovery
- WS stream heartbeat and disconnect behavior
- cursor ack resume
- per-run sequence ordering and event dedup
4. CI suites exactly matching locked scenarios S1-S8 with required assertions and expected app codes.
5. Compatibility tests:
- canonical manifest completeness
- alias resolution to canonical tools
- request/response schema version compliance
- schema path invalid rejection behavior
6. E2E acceptance scenario:
- create interrogation session
- discovery starts
- first question emitted
- verify no stale-session, UTF-8, or schema-path regressions

## Backward Compatibility
1. Preserve alias support listed in canonical manifest while routing to canonical tool handlers.
2. Preserve session-level pause/resume behavior despite immutable run terminal semantics.
3. Keep app codes transport-invariant across HTTP/WS/queue.
4. Maintain compatibility matrix checks for `min_supported`, `latest-1`, `latest`, and `next_rc`, including upgrade-path checks.
5. Publish docs generated from canonical manifest only; block release if docs and manifest drift.

## Rollout and Rollback Controls
1. Gate MCP server with config flag (for example `agent_mcp.enabled`) and environment allowlist; default closed.
2. Rollout controls:
- enable read-only tools first via per-tool toggles
- then enable mutating tools by policy group
- keep admin destructive tool disabled by default outside controlled environments
3. Add kill switches:
- disable WS stream endpoint independently
- disable specific tool groups via config
- immediate key disable for compromised clients
4. Migration safety:
- additive schema changes first
- destructive retention operations only via explicit worker command
- rollback scripts for route/provider disable and feature flag reversion
5. Operational runbooks:
- stale cursor incident recovery
- key compromise response
- schema validation failure triage
- retention cleanup verification and aggregate integrity checks

## Sequence and Dependencies
1. Establish module scaffolding, config keys, provider wiring, and guarded routes.
2. Implement auth key lifecycle and scope-permission evaluator with locked policy matrix.
3. Implement canonical manifest registry and schema validator, then bind tool discovery to registry.
4. Implement session and workflow tool handlers by adapting existing lifecycle services and validations.
5. Implement event persistence contracts, poll endpoint, WS stream gateway, heartbeat, and cursor ack resume.
6. Implement failure mapping, UTF-8 guardrails, transition policy enforcement, and partial-success handling.
7. Implement resources/prompts and observability surfaces (logs, metrics, health, audit summaries).
8. Implement retention worker and aggregate counter behavior.
9. Finalize compatibility checks, CI gate enforcement for S1-S8 matrix, and end-to-end acceptance coverage.

## Sections

- Scope Boundary
- Architecture Changes
- Data Model and Migrations
- API and Tool Contracts
- Event Contracts
- Authorization and Scope Enforcement
- Failure and Retry Behavior
- Observability and Auditability
- Test Strategy
- Backward Compatibility
- Rollout and Rollback Controls
- Sequence and Dependencies


## Risks

- Policy matrix drift between config and runtime evaluator can silently over-permit or over-deny tool access.
- Manifest/schema drift can break compatibility assertions and invalidate alias routing behavior.
- Per-run sequence generation under concurrent workers can violate monotonic ordering guarantees if not transactionally enforced.
- WebSocket heartbeat thresholds can trigger false disconnects in noisy environments and degrade stream reliability.
- Cursor acknowledgment bugs can cause replay gaps or duplicate processing across poll/stream recovery paths.
- UTF-8 validation gaps at transport boundaries can allow malformed payload persistence and downstream parser failures.
- Run transition enforcement errors can reopen terminal runs or block valid retry/restart creation semantics.
- Partial-success (`start_failed`) handling can leave orphaned build tasks without a deterministic retry pathway.
- Retention worker hard-delete logic can remove records before aggregate counters are durably updated.
- Audit logging bypass in adapter layers can create non-repudiation gaps for mutating MCP operations.
- Error mapper inconsistencies across HTTP/WS/queue can break client retry logic and machine parsing.
- CI matrix expansion for compatibility gates can introduce flaky failures if environment/version fixtures are not deterministic.


## Assumptions

- Existing Agent services for discovery, interrogation, summary, planning, and build operations are callable from MCP adapters without re-implementing domain logic.
- Sanctum bearer authentication is already available and can be extended to opaque API key resolution with hashed storage.
- Redis/queue infrastructure and WebSocket stack used by Agent are available for poll-plus-stream event delivery.
- Database supports required constraints and indexes for event ordering, deduplication, and scoped lookups.
- Current authorization and ownership rules are exposed in reusable policies/services that MCP handlers can invoke directly.
- Audit log infrastructure exists and supports immutable append-only records for every mutating operation.
- MCP Inspector and target clients can consume schema-backed tool metadata, aliases, and annotation fields.
- Canonical manifest and schema files can be treated as repository-tracked artifacts and enforced in CI.
- Session-level pause/resume semantics are already represented in the domain and can remain independent of run-level transition rules.
- Retention policy execution can be scheduled through existing worker/scheduler mechanisms without introducing external orchestration systems.
- Compatibility test harness can execute locked scenario suites across required version matrix lanes in the existing CI platform.
- No additional tenant model redesign is required beyond enforcing locked dimensions (`tenant`,`environment`,`role`) and optional permission claims.

