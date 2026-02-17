# Implementation Plan

Derived from discovery session 4.

> Restored after interrupted revision run that truncated this file to an interim status message. Baseline content restored from v2 plan for continuity.

# Agent MCP Implementation Plan (Rewrite)

## Scope Boundary
- Deliver one MCP server named `Agent MCP` that exposes tools, resources, and prompts for authenticated user-scoped operations over jobs, runs, and interrogation sessions.
- Keep all mutation logic on existing Laravel validation, authorization, and audit paths; MCP layer is an adapter/orchestrator, not a second business-logic implementation.
- Start authentication with Sanctum bearer tokens on MCP HTTP routes; isolate auth resolution so Passport OAuth 2.1 can be introduced without rewriting tool handlers.
- Explicitly exclude unrestricted filesystem access, arbitrary shell execution, and any bypass of `CommandPolicy`, `PathPolicy`, and `EnvPolicy`.

## Dependency-Ordered Delivery Plan
1. Create MCP transport skeleton, auth middleware wiring, and server capability discovery endpoints.
2. Introduce an MCP application layer (tool/resource/prompt registries + schema/error contracts).
3. Extract or wrap existing job/run/interrogation application services so MCP write paths call the same code paths as API/UI.
4. Implement read tools first (lower risk, validates authz/ownership and pagination).
5. Implement write tools with idempotency keys, mutation rate limiting, and deterministic error mapping.
6. Add MCP resources and prompts on top of the same service/query layer.
7. Add streaming progress notifications for long-running actions and run/interrogation event forwarding.
8. Add observability/audit enrichments and operational safeguards.
9. Complete unit, feature, and integration/e2e coverage including the required interrogation scenario.
10. Gate release behind config flags; ship with rollback switches and migration rollback steps.

## Architecture Changes
- Add MCP module namespace:
  - `app/Mcp/Http/McpController.php` (new): handles MCP JSON-RPC requests, handshake/capabilities, tool invocation dispatch.
  - `app/Mcp/Server/AgentMcpServer.php` (new): server metadata, registry composition.
  - `app/Mcp/Registry/ToolRegistry.php` (new), `app/Mcp/Registry/ResourceRegistry.php` (new), `app/Mcp/Registry/PromptRegistry.php` (new).
  - `app/Mcp/Contracts/ToolResult.php` (new), `app/Mcp/Contracts/McpError.php` (new), `app/Mcp/Contracts/ProgressEvent.php` (new).
  - `app/Mcp/Support/PrincipalResolver.php` (new): abstracts current Sanctum principal and future Passport token principal.
- Register MCP services in provider:
  - `app/Providers/AppServiceProvider.php` or `app/Providers/AgentMcpServiceProvider.php` (new) for bindings and registry bootstrap.
- Route setup:
  - `routes/api.php`: add `/agent/mcp` group with `auth:sanctum` and dedicated throttle middleware.
  - Add optional streaming endpoint `/agent/mcp/stream` for progress notifications/events.
- Config:
  - `config/agent.php`: add `mcp` section (`enabled`, `read_only_mode`, `write_rate_limit`, `streaming_enabled`, `max_page_size`).
  - Optional `config/mcp.php` (new) if separation from agent config is cleaner.

## Data Model and Migrations
- Reuse existing core models for jobs/runs/events/audit/heartbeat/system state:
  - `app/Models/AgentJob.php`
  - `app/Models/AgentJobRun.php`
  - `app/Models/AgentRunEvent.php`
  - `app/Models/AgentAuditLog.php`
  - `app/Models/SchedulerHeartbeat.php`
  - `app/Models/AgentSystemState.php`
- Interrogation persistence:
  - If interrogation tables/models already exist, add only additive columns needed for MCP-safe retries and status tracking (for example `idempotency_key`, `last_error_code`, `paused_at`, `resumed_at`, `retry_of_session_id`).
  - If absent, add new models and migrations:
    - `app/Models/InterrogationSession.php` (new)
    - `app/Models/InterrogationMessage.php` (new)
    - `database/migrations/*_create_interrogation_sessions_table.php` (new)
    - `database/migrations/*_create_interrogation_messages_table.php` (new)
- Add indexes for user-scoped retrieval and event ordering:
  - `(user_id, created_at)` on sessions.
  - `(session_id, sequence)` on interrogation messages.
  - `(agent_job_run_id, created_at, id)` on run events if not already present.
- Keep schema changes backward-safe: additive columns/tables only; no destructive mutation in initial rollout.

## API and Tool Contracts
- Tool response envelope (uniform, parseable):
  - Success: `{ok: true, data: <typed object>, meta: {request_id, tool, idempotency_key?}}`
  - Error: `{ok: false, error: {code, message, details, retryable}, meta: {request_id, tool}}`
- Read tools (annotations: `read_only=true`, `idempotent=true`, `destructive=false`, `open_world=false`):
  - `agent.jobs.list`, `agent.jobs.get`
  - `agent.runs.list`, `agent.runs.get`, `agent.runs.events`
  - `agent.dashboard.metrics`
  - `agent.scheduler.health`
  - `agent.interrogation.sessions.list`, `agent.interrogation.sessions.get`, `agent.interrogation.sessions.status`
- Write tools:
  - `agent.jobs.create` (`destructive=false`, `idempotent=false`)
  - `agent.jobs.update` (`destructive=false`, `idempotent=true` with idempotency key)
  - `agent.jobs.toggle` (`destructive=false`, `idempotent=true`)
  - `agent.jobs.run_now` (`destructive=false`, `idempotent=true` per job+key)
  - `agent.jobs.restore` (`destructive=false`, `idempotent=true`)
  - `agent.jobs.delete` (`destructive=true`, `idempotent=true` for already-deleted state)
  - `agent.runs.stop` (`destructive=false`, `idempotent=true`)
  - `agent.interrogation.create`, `agent.interrogation.answer`, `agent.interrogation.pause`, `agent.interrogation.resume`, `agent.interrogation.retry` (mutation annotations set per state transition; all `open_world=false`).
- Contract file targets:
  - `app/Mcp/Tools/Jobs/*.php` (new)
  - `app/Mcp/Tools/Runs/*.php` (new)
  - `app/Mcp/Tools/Interrogation/*.php` (new)
  - `app/Mcp/Schemas/*.php` or `app/Mcp/Schemas/*.json` (new input/output schemas)
- Validation and error behavior:
  - Validation errors map to `VALIDATION_FAILED` with field-level `details.field_errors`.
  - Ownership/authz denial maps to `AUTH_FORBIDDEN` without leaking existence details.
  - Missing entities return `NOT_FOUND` only when resource is user-visible; otherwise return forbidden-equivalent to prevent cross-user probing.
  - Policy violations return `POLICY_REJECTED` with actionable message (for example invalid placeholder, forbidden env key, disallowed path base).

## Resource Contracts
- Add MCP resources (read-only):
  - `agent://policy/config` -> sanitized policy/config reference from `config/agent.php` (never return secret values).
  - `agent://system/health` -> scheduler heartbeat, queue health summary, stale-worker indicators.
  - `agent://audit/recent` -> current-user recent audit summary only.
  - `agent://interrogation/guidance` -> guardrails and expected answer format for interrogation workflows.
- File targets:
  - `app/Mcp/Resources/PolicyConfigResource.php` (new)
  - `app/Mcp/Resources/SystemHealthResource.php` (new)
  - `app/Mcp/Resources/UserAuditSummaryResource.php` (new)
  - `app/Mcp/Resources/InterrogationGuidanceResource.php` (new)

## Prompt Contracts
- Add prompts with typed arguments and deterministic output sections:
  - `agent.prompt.triage_failed_run` (inputs: `run_id`, optional `max_events`; output sections: failure summary, likely root causes, next safe actions).
  - `agent.prompt.draft_safe_job_config` (inputs: runner type, task path, working directory; output: candidate config compliant with policies + validation checklist).
  - `agent.prompt.prepare_interrogation_kickoff` (inputs: session purpose/context; output: first-question set following interrogation guidance).
- File targets:
  - `app/Mcp/Prompts/TriageFailedRunPrompt.php` (new)
  - `app/Mcp/Prompts/DraftSafeJobConfigPrompt.php` (new)
  - `app/Mcp/Prompts/PrepareInterrogationKickoffPrompt.php` (new)

## Event and Streaming Contracts
- Streaming endpoint emits ordered notifications for long-running operations and live run/interrogation updates.
- Event envelope contract:
  - `{event_id, request_id, type, created_at, payload}`
- Event types:
  - `progress.started`, `progress.updated`, `progress.completed`, `progress.failed`
  - `run.event` (normalized stdout/stderr/lifecycle slices)
  - `interrogation.discovery_started`, `interrogation.question_emitted`, `interrogation.state_changed`
- Sequence guarantees:
  - Monotonic per-request event sequence number.
  - Resume support via `after_event_id` cursor.
- File targets:
  - `app/Mcp/Streaming/ProgressPublisher.php` (new)
  - `app/Mcp/Streaming/EventNormalizer.php` (new)
  - `app/Mcp/Http/McpStreamController.php` (new)

## Authorization and Scope Enforcement
- Enforce `auth:sanctum` for all MCP routes and resolve authenticated user through one principal resolver abstraction.
- Tool handlers must always filter by ownership before lookup where possible (for example `where('user_id', $principal->id)`).
- Reuse existing policy checks and gates for mutating actions; do not inline alternate authorization logic.
- Reuse existing validation classes/services where available:
  - `app/Http/Requests/Agent/StoreAgentJobRequest.php`
  - `app/Support/Agent/CommandPolicy.php`
  - `app/Support/Agent/PathPolicy.php`
  - `app/Support/Agent/EnvPolicy.php`
- Preserve and extend audit logging via existing `AgentAuditLog` flows for every write tool invocation with actor, target, action, and correlation id.
- Prepare OAuth 2.1 compatibility:
  - Add token capability mapping abstraction now (for example `McpAbility::JOBS_WRITE`, `McpAbility::RUNS_STOP`, `McpAbility::INTERROGATION_WRITE`).
  - Keep Sanctum ability checks compatible with future Passport scope names.

## Failure, Retry, and Idempotency Behavior
- Standardize MCP error taxonomy and mapping:
  - `AUTH_REQUIRED`, `AUTH_FORBIDDEN`, `NOT_FOUND`, `VALIDATION_FAILED`, `POLICY_REJECTED`, `RATE_LIMITED`, `STATE_CONFLICT`, `INTERNAL_ERROR`, `UPSTREAM_UNAVAILABLE`.
- Idempotency:
  - Support optional `idempotency_key` for write tools.
  - Persist recent keys in cache/store keyed by user+tool+target+key; replay same result on duplicate request.
- Retry rules:
  - Retry only retryable infrastructure failures (`UPSTREAM_UNAVAILABLE`, transient DB/queue issues).
  - Never auto-retry validation/auth/policy/state errors.
- State transition guards:
  - Stop run only from stoppable states.
  - Interrogation pause/resume/retry enforce legal state transitions and return `STATE_CONFLICT` when invalid.
- Rate limits:
  - Apply dedicated throttle middleware for mutation tools and preserve existing mutation guardrails.

## Observability and Auditability
- Structured logging channel `agent_mcp` with fields: `request_id`, `tool`, `resource`, `prompt`, `user_id`, `target_id`, `result_code`, `latency_ms`.
- Metrics:
  - Counters per tool invocation outcome.
  - Histogram for tool latency and stream event lag.
  - Gauge for active streaming sessions.
- Health snapshot resource composes:
  - Scheduler recency from `SchedulerHeartbeat`.
  - Queue processing indicators for `agent` queue.
  - Recent failure spikes from `AgentJobRun` statuses.
- Ensure audit completeness for write tools by asserting log creation in tests.
- File targets:
  - `config/logging.php` (add channel)
  - `app/Listeners/*` or `app/Mcp/Telemetry/*` (new)

## Test Strategy (Unit, Feature, Integration)
- Unit tests (`tests/Unit/Mcp/*`):
  - Schema validation for each tool/resource/prompt input and output shape.
  - Annotation correctness test (read-only/idempotent/destructive/open-world flags).
  - Error mapper test for all standardized codes.
  - State transition guard tests for interrogation and run stop actions.
- Feature tests (`tests/Feature/Mcp/*`):
  - Auth required and authz denied coverage on each write tool.
  - Ownership enforcement: cross-user access returns forbidden-safe behavior.
  - Validation failures with field-level details.
  - Policy enforcement for command/path/env restrictions through MCP create/update job tools.
- Integration tests (`tests/Integration/Mcp/*` or feature-level with real DB/queue fakes):
  - `agent.jobs.run_now` dispatch path and progress event emission.
  - `agent.runs.events` stream ordering and cursor resume.
  - Required e2e path: `interrogation.create` -> discovery starts -> first question emitted (assert event type and content contract).
- Update existing suites where needed:
  - Extend `tests/*AgentJob*` and `tests/*Validation*` to include MCP entrypoint parity checks.

## Backward Compatibility
- No breaking changes to existing REST API routes under `/agent/api/v1`.
- MCP layer should call shared application services to keep behavior parity between REST/UI and MCP clients.
- Config defaults keep MCP disabled unless explicitly enabled; existing deployments remain unchanged until activated.
- Keep database migration strategy additive so current application behavior remains intact without MCP client traffic.

## Rollout and Rollback Controls
- Add feature toggles:
  - `AGENT_MCP_ENABLED`
  - `AGENT_MCP_WRITE_ENABLED`
  - `AGENT_MCP_STREAMING_ENABLED`
- Route registration and tool registry should honor toggles so read-only mode can be enforced without code changes.
- Rollout controls:
  - Start with read tools/resources/prompts enabled.
  - Enable write tools once authz/audit/validation tests pass in target environment.
- Rollback controls:
  - Disable MCP routes/tools via config flags immediately.
  - Keep schema rollback scripts for interrogation migrations if newly introduced.
  - Preserve audit records regardless of MCP enablement state.

## Concrete File-Level Targets Summary
- Existing files to integrate:
  - `routes/api.php`
  - `config/agent.php`
  - `app/Http/Requests/Agent/StoreAgentJobRequest.php`
  - `app/Support/Agent/CommandPolicy.php`
  - `app/Support/Agent/PathPolicy.php`
  - `app/Support/Agent/EnvPolicy.php`
  - `app/Models/AgentJob.php`
  - `app/Models/AgentJobRun.php`
  - `app/Models/AgentRunEvent.php`
  - `app/Models/AgentAuditLog.php`
  - `app/Models/SchedulerHeartbeat.php`
  - `app/Models/AgentSystemState.php`
- New MCP module targets:
  - `app/Mcp/**` (controllers, registries, tools, resources, prompts, schemas, streaming, telemetry)
  - `tests/Unit/Mcp/**`
  - `tests/Feature/Mcp/**`
  - `tests/Integration/Mcp/**`
  - `database/migrations/*interrogation*` (only if missing/required)

## Sections

- Scope Boundary
- Dependency-Ordered Delivery Plan
- Architecture Changes
- Data Model and Migrations
- API and Tool Contracts
- Resource Contracts
- Prompt Contracts
- Event and Streaming Contracts
- Authorization and Scope Enforcement
- Failure, Retry, and Idempotency Behavior
- Observability and Auditability
- Test Strategy (Unit, Feature, Integration)
- Backward Compatibility
- Rollout and Rollback Controls
- Concrete File-Level Targets Summary


## Risks

- MCP adapter could accidentally bypass existing request validators/policies if write tools call repositories directly instead of shared application services.
- Transport-level incompatibility with MCP Inspector capability negotiation may block discovery despite correct backend logic.
- High-volume run event streaming can create backpressure and memory growth if buffering is not bounded and cursor resume is not implemented.
- Idempotency implementation defects can cause duplicate job runs or repeated interrogation transitions under client retries.
- Ownership checks implemented after entity lookup may leak existence metadata across users.
- Mutation throttling tuned too aggressively can block legitimate automation clients; tuned too loosely can permit abuse.
- Interrogation schema additions may conflict with pre-existing unofficial tables/columns in some environments.
- Audit logging coverage gaps on some write paths can violate traceability requirements.
- Sanctum ability names chosen now may not map cleanly to Passport OAuth scopes later without an explicit mapping layer.


## Assumptions

- Sanctum bearer token authentication is already operational for API routes and can be applied to `/agent/mcp` route group.
- Existing domain logic for job/run/interrogation mutations is available in controllers/services/actions that can be reused or extracted without changing behavior.
- Current models maintain a reliable user ownership relation (`user_id` or equivalent) for all entities exposed by MCP tools.
- Redis/queue infrastructure required for run dispatch and event flow is available in target environments.
- `AgentAuditLog` can store additional MCP-origin metadata (for example request correlation id) without destructive schema changes.
- Existing guardrails in `CommandPolicy`, `PathPolicy`, and `EnvPolicy` are the canonical validation sources and should remain authoritative.
- Scheduler health can be derived from current heartbeat/state models without introducing external monitoring dependencies.
- MCP clients can consume structured JSON error envelopes and optional streaming notifications from the chosen HTTP transport.
- No requirement exists to expose arbitrary filesystem or shell execution capabilities through MCP, and these remain explicitly prohibited.

