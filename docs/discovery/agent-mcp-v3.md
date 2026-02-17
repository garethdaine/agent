# Requirements Discovery Summary

Session: 4

## Agent MCP v1 Discovery Finalized Baseline
### Primary Outcome
Deliver a production-ready Laravel MCP server for Agent so external MCP clients execute discovery, interrogation, summary, planning, and build-task workflows end-to-end without manual intervention.

### Success Definition
- Codex and Claude connect and authenticate reliably with scoped opaque API keys.
- Fresh sessions complete discovery to build.start without stale-session, UTF-8, or schema-path failures.
- Discovery and interrogation outputs are structured, domain-specific, and actionable.
- Regression and compatibility suites pass in CI under the locked version matrix.

### Canonical Auth and Scope Model
- Canonical scope dimensions are exactly: `tenant`, `environment`, `role`.
- `capability` is removed as a scope dimension.
- If capability granularity is needed, it is modeled only as a separate permission claim (for example `permission=build.execute`).
- Evaluation order is fixed: 1) scope check (`tenant`,`environment`,`role`) 2) permission claim check.
- Key lifecycle: opaque API keys per MCP client, optional expiration default 90 days, rotation manual or API, revoke via immediate key disable.

### Canonical v1 Tool Manifest (Publish Source)
This in-repo manifest is the publish source for documentation and compatibility checks. Upstream version fields are mirrored for traceability only.

| tool_name | request_schema_version | response_schema_version | stability | aliases |
| --- | --- | --- | --- | --- |
| sessions.create | 1.0.0 | 1.0.0 | stable | [create] |
| sessions.show | 1.0.0 | 1.0.0 | stable | [sessions.read, read] |
| sessions.list | 1.0.0 | 1.0.0 | stable | [list] |
| sessions.pause | 1.0.0 | 1.0.0 | stable | [pause] |
| sessions.resume | 1.0.0 | 1.0.0 | stable | [resume] |
| sessions.retry | 1.0.0 | 1.0.0 | stable | [retry] |
| sessions.restart | 1.0.0 | 1.0.0 | stable | [restart] |
| sessions.cleanup | 1.0.0 | 1.0.0 | stable | [cleanup] |
| discovery.start | 1.0.0 | 1.0.0 | stable | [] |
| interrogation.answer | 1.0.0 | 1.0.0 | stable | [] |
| summary.confirm | 1.0.0 | 1.0.0 | stable | [] |
| plan.generate | 1.0.0 | 1.0.0 | stable | [] |
| build_tasks.generate | 1.0.0 | 1.0.0 | stable | [build.generate] |
| build.start | 1.0.0 | 1.0.0 | stable | [] |
| events.poll | 1.0.0 | 1.0.0 | stable | [] |
| events.stream | 1.0.0 | 1.0.0 | stable | [events.subscribe] |
| cleanup_invalid_questions | 1.0.0 | 1.0.0 | stable | [] |
| settings.update | 1.0.0 | 1.0.0 | stable | [] |
| policy.update | 1.0.0 | 1.0.0 | stable | [] |
| config.update | 1.0.0 | 1.0.0 | stable | [] |
| admin.destructive.execute | 1.0.0 | 1.0.0 | stable | [] |

### Per-Tool Scope Policy (Locked)
Allowed values: `tenant` equals key-bound tenant slug or id, `environment` in `dev|staging|prod`, `role` in `viewer|operator|admin`.
Default mismatch action is deny. Filter is allowed only for read/list/stream tools and only within same tenant and environment.

| tool_name | tenant | environment | role | mismatch_action |
| --- | --- | --- | --- | --- |
| sessions.list | required | required | optional: viewer|operator|admin | filter for tenant or environment mismatch; deny for role mismatch |
| sessions.show | required | required | optional: viewer|operator|admin | filter for tenant or environment mismatch; deny for role mismatch |
| events.poll | required | required | optional: viewer|operator|admin | filter for tenant or environment mismatch; deny for role mismatch |
| events.stream | required | required | optional: viewer|operator|admin | filter for tenant or environment mismatch; deny for role mismatch |
| sessions.create | required | required | required: operator|admin | deny |
| discovery.start | required | required | required: operator|admin | deny |
| interrogation.answer | required | required | required: operator|admin | deny |
| summary.confirm | required | required | required: operator|admin | deny |
| plan.generate | required | required | required: operator|admin | deny |
| build_tasks.generate | required | required | required: operator|admin | deny |
| build.start | required | required | required: operator|admin | deny |
| sessions.pause | required | required | required: operator|admin | deny |
| sessions.resume | required | required | required: operator|admin | deny |
| sessions.retry | required | required | required: operator|admin | deny |
| sessions.restart | required | required | required: operator|admin | deny |
| sessions.cleanup | required | required | required: operator|admin | deny |
| cleanup_invalid_questions | required | required | required: operator|admin | deny |
| settings.update | required | required | required: admin | deny |
| policy.update | required | required | required: admin | deny |
| config.update | required | required | required: admin | deny |
| admin.destructive.execute | required | required | required: admin | deny |

### Event, Transition, and Retention Contracts
- Event delivery is locked to cursor poll plus WebSocket.
- Poll is required for bootstrap and recovery; stream is for realtime.
- Ordering is strict per run by monotonic `seq`; idempotency is `event_id` dedup plus client-acked cursor.
- WebSocket ops: server ping every 25s, client pong within 10s, disconnect after 2 misses, reconnect exponential backoff with jitter from 1s to max 30s, resume via `cursor_ack(run_id,cursors,client_ts)`.
- Run states: `queued|starting|running|stopping|succeeded|failed|killed|timed_out|skipped`.
- Terminal runs are immutable; retry and restart always create a new run; run-level pause and resume unsupported.
- Session-level pause and resume remain supported for backward compatibility and are explicitly session-scoped.
- Question filtering uses balanced profile: reject malformed, duplicate or near-duplicate, missing entity-plus-intent specificity, and non-actionable.
- Retention: full session and event data for 90 days, then hard-delete sessions and events, retain aggregate counters only.
- Partial success contract: if build task generation succeeds but start fails, persist task in `start_failed` and return `task_id` plus structured error for explicit retry.

### CI Matrix and Release Gate (Locked)
Mandatory release gate: all required suites below must pass.

| scenario_id | definition | required assertions | expected error codes |
| --- | --- | --- | --- |
| S1 create-valid | Session creation and authentication baseline | valid scoped key creates session; disabled or missing key is rejected; audit record emitted | AUTH_UNAUTHENTICATED for auth-fail subcase |
| S2 cron-validation-reject | Invalid schedule and payload validation | invalid cron or invalid required fields return validation error; no session or run side effects | VALIDATION_FAILED |
| S3 dispatch-enqueue | discovery.start dispatch behavior | exactly one run enqueued; initial run state queued; first lifecycle event persisted | none on happy path |
| S4 run-success-lifecycle | End-to-end successful run and cursor recovery | lifecycle order queued to starting to running to succeeded; per-run seq monotonic; duplicate event_id ignored; stale cursor returns recovery instruction | STALE_CURSOR for stale-cursor subcase |
| S5 run-failure-capture | Failure capture and UTF-8 boundary safety | forced runner failure ends in failed with stderr capture; invalid UTF-8 payload rejected before persistence | ENCODING_INVALID_UTF8 for invalid-encoding subcase |
| S6 stop-kill-transition | Transition contract enforcement | illegal stop or kill on immutable terminal run denied; retry or restart creates new run id | UNSUPPORTED_TRANSITION |
| S7 timeout | Timeout lifecycle behavior | forced timeout ends in timed_out; terminal cannot reopen; retry or restart creates new run id | UNSUPPORTED_TRANSITION for reopen attempt |
| S8 api-backward-compat | Contract compatibility and schema path validation | every manifest tool validates request and response schema versions; aliases resolve to canonical tools with deprecation metadata; schema path violations rejected | SCHEMA_PATH_INVALID |

Required version matrix:
- PR: S1 to S4 on `min_supported` and `latest`.
- Nightly and Release: S1 to S8 on `min_supported`, `latest-1`, `latest`, `next_rc`.
- Upgrade checks: `min_supported` to `latest`, and `latest-1` to `latest`.

### Error Taxonomy and Transport Mapping (Canonical)
RFC7807 plus stable app codes is canonical. App codes are transport-invariant: `AUTH_UNAUTHENTICATED`, `VALIDATION_FAILED`, `UNSUPPORTED_TRANSITION`, `STALE_CURSOR`, `ENCODING_INVALID_UTF8`, `SCHEMA_PATH_INVALID`.

Required payload fields by transport:
- HTTP: `type`, `title`, `status`, `code`, `detail`, `instance`, `request_id`, `retryable`; plus `errors` for field validation.
- WebSocket: `type=error`, `code`, `message`, `retryable`, `request_id`, `server_ts`; optional `run_id`, `cursor_hint`, `transport_hint`.
- Queue and worker: `code`, `message`, `retryable`, `job_id`, `attempt`, `max_attempts`, `failed_at`; optional `run_id`, `transport_hint`.

| app_code | HTTP mapping | WS mapping | Queue mapping | retryability semantics | example |
| --- | --- | --- | --- | --- | --- |
| AUTH_UNAUTHENTICATED | 401 problem details | error frame then close with auth hint | command rejected before processing | retryable only after credential refresh or key re-enable | unauthenticated sessions.create |
| VALIDATION_FAILED | 422 problem details with field errors | error frame on request correlation id | validation reject, no side effects | retryable after payload correction | invalid cron on sessions.create |
| UNSUPPORTED_TRANSITION | 409 problem details | error frame with current_state and allowed_transitions hint | control command rejected for run state | retryable only after state changes or by creating new run where allowed | stop on succeeded run |
| STALE_CURSOR | 409 problem details | error frame with cursor_hint for reset | stream resume job rejects stale ack cursor | retryable with cursor reset and replay | poll or stream resume with stale after cursor |
| ENCODING_INVALID_UTF8 | 400 problem details | error frame or close with invalid-encoding hint | worker input rejected pre-exec | retryable after UTF-8 normalization | invalid UTF-8 in interrogation.answer payload |
| SCHEMA_PATH_INVALID | 400 problem details | error frame with schema_path hint | contract validation reject pre-dispatch | retryable after schema path fix | invalid schema path or version in tool call |

### Entities, Services, and Config Locked for Implementation
Entities: MCPClient, APIKeyScope, PermissionClaim, Session, Run, RunEvent, SummaryArtifact, PlanArtifact, BuildTask, AuditRecord, RetentionAggregateCounter.
Services: AuthKeyLifecycleService, ScopePermissionEvaluator, ToolManifestRegistry, SchemaValidator, SessionLifecycleService, RunTransitionPolicy, EventPollService, EventStreamGateway, CursorAckService, QuestionQualityFilter, ErrorTransportMapper, RetentionCleanupWorker, CompatibilityCISuite.
Locked config keys:
- `auth.token_type=opaque_api_key`
- `auth.default_expiration_days=90`
- `auth.rotation=manual_or_api`
- `auth.revocation=key_disable_immediate`
- `auth.scope_dimensions=tenant,environment,role`
- `auth.permission_claim_enabled=true`
- `auth.eval_order=scope_then_permission`
- `manifest.publish_source=in_repo_canonical`
- `manifest.traceability=mirror_upstream_versions`
- `events.transport=poll_plus_websocket`
- `events.ordering=per_run_monotonic_seq`
- `events.idempotency=event_id_dedup_plus_acked_cursor`
- `ws.ping_interval_seconds=25`
- `ws.pong_timeout_seconds=10`
- `ws.disconnect_after_missed_pongs=2`
- `ws.reconnect_backoff=exponential_with_jitter_1_to_30_seconds`
- `runs.terminals_immutable=true`
- `runs.retry_restart=create_new_run`
- `runs.pause_resume_supported=false`
- `sessions.pause_resume_supported=true_session_scoped`
- `questions.filter_profile=balanced`
- `retention.full_data_days=90`
- `retention.post_window=hard_delete_sessions_and_events_keep_aggregates`
- `build.partial_start_failure_state=start_failed`
- `errors.standard=rfc7807_http_first_with_stable_app_codes`
- `ci.release_gate=mandatory`
- `ci.matrix.pr=S1-S4:min_supported,latest`
- `ci.matrix.nightly_release=S1-S8:min_supported,latest-1,latest,next_rc`
- `ci.matrix.upgrade=min_supported_to_latest,latest-1_to_latest`

## Goals

- Ship a production-ready Agent MCP server using Laravel MCP that executes discovery, interrogation, summary, planning, and build-task workflows end-to-end without manual intervention.
- Guarantee deterministic and safe machine-to-machine behavior through versioned tool contracts, canonical scope enforcement, and stable transport-agnostic error codes.
- Publish and enforce a canonical in-repo tool manifest as the single source for docs and compatibility checks, with mirrored upstream version metadata for traceability.
- Harden reliability at known failure boundaries: stale cursor recovery, UTF-8 safety, schema-path validation, and noisy-question rejection.
- Enforce immutable run terminal semantics, explicit partial-success handling for build start failures, and session-scoped backward-compatible pause/resume APIs.
- Gate releases on the locked CI scenario and version matrix, including backward compatibility and upgrade-path checks.


## Constraints

- Local-first boundary only: read and write operations are limited to this Agent app and workspace.
- No bypass of existing command, path, environment, and auth guardrails.
- No new external writeback integrations beyond current Agent surface for v1.
- No multi-tenant cloud orchestration or cross-workspace federation in v1.
- No memory or RAG architecture redesign in v1.
- No broad UI redesign outside fixes required for MCP-driven flow correctness.
- Long-running operations are async and phase-driven; clients must use poll or stream patterns.
- Scope dimensions are fixed to tenant, environment, and role; default mismatch action is deny.
- Filter on mismatch is permitted only for read, list, and stream tools and only within same tenant and environment.
- Run-level pause and resume remain unsupported; session-level pause and resume are supported for backward compatibility only.
- Terminal run states are immutable; retry and restart must create new runs.
- Error model is fixed to RFC7807 plus stable app codes reused across HTTP, WebSocket, and queue boundaries.


## Acceptance Criteria

- Canonical scope model is implemented as tenant, environment, and role only, with capability handled solely as an optional permission claim.
- Scope evaluation executes before permission evaluation for every tool invocation.
- A canonical in-repo manifest exists and includes tool_name, request_schema_version, response_schema_version, stability, and aliases for every v1 tool.
- Documentation and compatibility checks are generated from or validated against the canonical manifest.
- Per-tool scope policy is implemented exactly as locked, including required or optional dimensions, required role values, and mismatch actions.
- Mismatch default is deny, and filter behavior appears only on sessions.list, sessions.show, events.poll, and events.stream.
- Event delivery implements poll plus WebSocket with strict per-run monotonic seq ordering and event_id dedup idempotency.
- WebSocket operational defaults are enforced: ping 25s, pong timeout 10s, disconnect after 2 misses, reconnect exponential backoff with jitter to max 30s, cursor_ack resume contract.
- Run transition policy enforces immutable terminal states, and retry or restart always creates a new run.
- Session-level pause and resume remain available and documented as session-scoped behavior independent of run-level immutability.
- Balanced interrogation filter rejects malformed, duplicate or near-duplicate, specificity-missing, and non-actionable questions.
- Retention policy enforces full data retention for 90 days then hard-deletes sessions and events while keeping aggregate counters.
- Build task partial-success behavior persists start_failed state and returns task_id plus structured error payload with explicit retry path.
- Error taxonomy and mapping include AUTH_UNAUTHENTICATED, VALIDATION_FAILED, UNSUPPORTED_TRANSITION, STALE_CURSOR, ENCODING_INVALID_UTF8, and SCHEMA_PATH_INVALID with fixed HTTP, WS, and queue mappings.
- Each transport emits required payload fields, including retryability semantics and transport hints.
- CI release gate is mandatory and enforces PR S1-S4 on min_supported and latest.
- Nightly and release CI enforce S1-S8 on min_supported, latest-1, latest, and next_rc.
- Upgrade checks enforce min_supported to latest and latest-1 to latest compatibility paths.
- S1 through S8 test definitions include required assertions and expected error codes exactly as locked in the baseline.
- Fresh-session end-to-end flow passes without stale-session, UTF-8, or schema-path failures and produces structured actionable outputs without raw code dumps or meta/system interrogation prompts.

