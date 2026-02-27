# Consolidated Implementation Brief — Agent Platform v1

**Date:** 2026-02-25 (updated)
**Status:** Implementation In Progress — Delegation Engine & Messenger Control Plane built
**Scope:** All unimplemented discovery plans + multi-agent coordination enhancements

---

## 1. Executive Summary

This brief consolidates all outstanding discovery plans for the Agent platform into a single implementation roadmap. It covers seven feature systems at varying stages of completion, plus multi-agent coordination enhancements informed by Claude Code Agent Teams and OpenAI Codex multi-agent patterns.

### Current State (updated 2026-02-25)

| System | Completion | Next Action |
|--------|-----------|-------------|
| Messenger Control Plane | ~60% | Local-first runtime gap + Phase B adapters |
| Requirements Discovery | ~90% | Build lifecycle proven; minor hardening remaining |
| **Delegation Engine** | **~95%** | **Built via discovery session 2 (2026-02-25). All models, services, controllers, Vue pages, events, listeners, tests created. Remaining: production validation, DAG visualisation (deferred)** |
| NL Scheduling | ~50% | Parser service, UI tab, active-hours |
| Adversarial Reviewer | 0% | Full build |
| Memory Architecture | 0% | Full build |
| Org Layer | 0% | Full build (Delegation dependency now satisfied) |
| Multi-Agent Enhancements | 0% | New — integrates into Org Layer |

### Dependency Graph

```
Requirements Discovery (existing, ~90%)
    └── Adversarial Reviewer (quality gates for summary/plan) ← NEXT PRIORITY

Delegation Engine (foundation, ~95% BUILT)  ✅
    ├── Memory Architecture (Layer 4 deferred, but context injection needed)
    ├── NL Scheduling (ritual schedule authoring)
    ├── Org Layer (composition over delegation) ← NOW UNBLOCKED
    │   └── Multi-Agent Enhancements (council deliberation, ad-hoc teams)
    └── Messenger Control Plane Phase B adapters (org ritual controls)

Messenger Control Plane (parallel track — no delegation dependency, ~60%)
    ├── Local-First Gateway Runtime (critical gap — W1/W2)
    ├── Webhook Ingress Productization (W3)
    └── Phase B Adapters: Discord + WhatsApp (W4)
```

---

## 2. Delegation Engine — ✅ BUILT (Discovery Session 2, 2026-02-25)

**Source:** `reconstructed-intelligent-delegation-for-agent-v3.md` (Session 99)
**Previously Implemented:** 10 migrations, 1 model (DelegationCapability), config/delegation.php, DelegationFeatureGate middleware
**Session 2 Build (2026-02-25):** All remaining components built via 13 automated build tasks:
- 10 Eloquent models with relationships, casts, scopes, and factories
- 17 support services (state transitions, graph builder, contract validator/enforcer, delegatee assigner, attempt spawner, verification pipeline with 3 step types, recovery handler, reconciler, metrics recomputer, event writer, broadcast subscriber)
- 3 API controllers with authorization policies and full route registration
- 7 Vue pages for delegation management with feature flag gating
- 6 broadcast events + 3 listeners (coordinator, recovery handler, broadcast subscriber)
- Horizon supervisor-delegation queue, scheduler commands, capability seeder
- Unit and feature tests across all components
- Feature flags enabled: `DELEGATION_ENABLED=true`, `DELEGATION_UI_ENABLED=true`
**Remaining:** Production validation, DAG visualisation (deferred to follow-up)
**Remaining:** 9 models, 14 services, 3 verification steps, 3 controllers, 2 policies, 8 Vue pages, broadcast events, queue topology, seeder

### 2.1 Models (9 remaining)

Build in dependency order:

1. **DelegateeProfile** — SoftDeletes; runner_type (string 16), command_template (string 2000), working_directory (string 1024), env_json (json nullable), config_json (json nullable); belongsTo User; belongsToMany capabilities via pivot; hasOne metrics
2. **DelegateeCapabilityPivot** — pivot model with unique constraint on (delegatee_profile_id, delegation_capability_id)
3. **DelegateeMetric** — belongsTo profile (unique FK); window_24h_json, window_7d_json; last_recomputed_at
4. **DelegationGraph** — SoftDeletes; statuses: draft/validating/ready/running/succeeded/failed/partial/cancelled; ACTIVE_STATUSES = [running]; TERMINAL_STATUSES = [succeeded, failed, partial, cancelled]; belongsTo User; hasMany tasks/events; cancellation_policy, max_parallel_tasks, metadata_json, error_code, error_summary, started_at, finished_at
5. **DelegationTask** — statuses: pending/blocked/ready/assigned/running/verifying/succeeded/failed/cancelled; belongsTo graph; hasMany attempts/dependencies/dependents/verificationResults; belongsTo assignedProfile (nullable); sequence_order, contract_json, assignment_reason_json, metadata_json
6. **DelegationTaskDependency** — belongsTo task + dependsOnTask; unique constraint on (task_id, depends_on_task_id)
7. **DelegationAttempt** — statuses: running/succeeded/failed; belongsTo task/profile; belongsTo agentJobRun (nullable, nullOnDelete); attempt_number, duration_ms, error_code, error_summary
8. **DelegationVerificationResult** — step_type (automated_check/ai_critic/human_approval), step_order, verdict (passed/failed/skipped/pending), evidence_json; belongsTo task/attempt
9. **DelegationEvent** — event_type (string 64), auto-incrementing sequence per graph, payload_json, event_ts; belongsTo graph/task(nullable)

### 2.2 Core Services (14)

**State Management:**

1. **GraphStateTransitionService** — atomic status transitions using `whereKey($id)->whereIn('status', $from)->update($payload)` pattern (mirrors RunStateTransitionService)
2. **TaskStateTransitionService** — same pattern for DelegationTask

**Graph Building & Validation:**

3. **DelegationGraphBuilder** — accepts DAG JSON or linear-chain shorthand; creates graph+tasks+dependencies in DB transaction; Kahn's algorithm cycle detection; max 25 tasks; auto-assigns sequence_order from topological depth
4. **ContractValidator** — validates contract_json: required_capability references active capability; authority_scope.max_runtime_seconds ≤ 86400; criticality enum; time_constraints cap enforcement; verification_strategy with valid check profiles; prompt or task_markdown_path present
5. **ContractEnforcer** — intersects authority_scope with PathPolicy/EnvPolicy boundaries; caps max_runtime_seconds; returns narrowed config or validation error

**Assignment & Execution:**

6. **DelegateeAssigner** — matches required_capability; ranks by success_rate from 24h sliding window; tiebreaks by lowest current load (fewest active attempts)
7. **AttemptSpawner** — creates DelegationAttempt; uses ContractEnforcer for narrowed config; creates transient AgentJob from DelegateeProfile; dispatches ExecuteAgentRunJob on 'agent' queue via Bus::chain with callback on 'delegation' queue; links attempt to AgentJobRun
8. **DelegationCoordinator** — EventSubscriberInterface for happy-path flow: GraphStarted → root tasks ready → assign → spawn → AttemptFinished(success) → verify → TaskVerified → complete → check graph completion → fire next ready tasks

**Verification Pipeline:**

9. **VerificationPipeline** — executes ordered steps from verification_strategy; tracks current step for resumability; short-circuits on first failure; resumes from next step on DelegationTaskVerified
10. **AutomatedCheckStep** — resolves check profile from config; executes commands sequentially; captures stdout/stderr as evidence_json
11. **AiCriticStep** — spawns dedicated AgentJobRun with review prompt; dispatches via Bus::chain callback; returns 'pending' immediately; callback writes result and fires DelegationTaskVerified
12. **HumanApprovalStep** — creates pending DelegationVerificationResult; returns immediately; resolution via API endpoint fires DelegationTaskVerified; reconciler enforces timeout

**Recovery & Metrics:**

13. **RecoveryHandler** — separate listener for failed attempts; combined heuristic classification (timed_out=transient, skipped=non-transient, failed/killed=error_code config lookup); decision chain: retry → re-delegate → escalate → abort; criticality influences escalation threshold
14. **DelegateeMetricsRecomputer** — event-triggered with 60s cache-lock throttle; scheduled fallback every 15 min; computes sliding window stats (24h, 7d)

**Supporting:**

15. **DelegationEventWriter** — follows RunEventWriter pattern; auto-increments sequence per graph
16. **DelegationReconciler** — scheduled every 2 min; detects stuck tasks, missed completions, expired human approvals; fires missed events
17. **DelegationBroadcastSubscriber** — selective broadcast dispatch with enriched payloads on per-graph and per-user private channels

### 2.3 Controllers & API

**Controllers (3):**

1. **DelegationGraphController** — CRUD + restore/validate/start/cancel/clone + events listing
2. **DelegationTaskController** — list/show tasks with attempts and verification results; POST verification resolution
3. **DelegateeProfileController** — CRUD + soft delete/restore

**API Endpoints** — all under `agent/api/v1/delegation/`, gated by DelegationFeatureGate, auth:sanctum:

- Graphs: GET/POST /graphs, GET/PUT/DELETE /graphs/{id}, POST restore/validate/start/cancel/clone
- Tasks: GET /graphs/{graphId}/tasks, GET /graphs/{graphId}/tasks/{taskId}, POST verification resolve
- Profiles: GET/POST /delegatee-profiles, GET/PUT/DELETE /delegatee-profiles/{id}
- Events: GET /graphs/{id}/events
- Clone accepts optional `mode`: 'all' | 'failed_subtree'

**Policies (2):** DelegationGraphPolicy (ownership + state guards), DelegateeProfilePolicy (ownership)

### 2.4 Frontend (8 Vue pages)

1. **DelegationIndex** — graph listing with status filters
2. **DelegationGraphShow** — graph detail with task status overview
3. **DelegationGraphCreate** — JSON editor + linear-chain mode with inline validation
4. **DelegationTaskDetail** — task detail with attempts and verification history
5. **DelegationVerificationApproval** — human approval UI
6. **DelegateeProfileIndex** — profile listing
7. **DelegateeProfileForm** — profile CRUD form
8. **DelegationGraphVisualization** — custom SVG + dagre layout, native SVG zoom/pan/click

**New npm dependency:** `dagre`

### 2.5 Queue & Horizon

- Add `supervisor-delegation`: queue ['delegation'], timeout 900, maxProcesses env('HORIZON_DELEGATION_MAX_PROCESSES', 2), auto balance
- Register DelegationReconciler (every 2 min) and DelegateeMetricsRecomputer (every 15 min) in scheduler
- Add DelegationCapabilitySeeder to DatabaseSeeder

### 2.6 Broadcast Events

- **DelegationGraphBroadcast** — PrivateChannel `delegation.graph.{graphId}`
- **DelegationUserSummaryBroadcast** — PrivateChannel `delegation.user.{userId}`
- Channel auth in routes/channels.php

---

## 3. Memory Architecture — Full Build

**Source:** `agent-memory-delegation.md` (Session 3)
**Implemented:** pgvector extension migration only
**Remaining:** 7 migrations, 7 models, 11 services, 2 contracts, 4 jobs, 4 artisan commands, config, provider, API endpoints

### 3.1 Operating Modes

- **No-API Mode** (zero provider keys): Core Memory (L1) + Working Memory (L2) + BM25 retrieval + conversation logs
- **API Mode** (provider keys + api_features_enabled): All above + LLM extraction/summarization + semantic embeddings + full hybrid retrieval (RRF) + Neo4j graph population
- **Degraded Mode** (Anthropic-only): text extraction enabled but no embeddings → BM25 + graph retrieval only

### 3.2 Database (7 new migrations)

1. **memory_settings** — user/workspace-level settings with encrypted cast for secrets
2. **memory_core_blocks** — hybrid blocks (freeform text identity + schema-enforced JSON operational); versioned; classified (public/internal/confidential)
3. **memory_embeddings** — pgvector 1536d HNSW + tsvector BM25; importance_score; access_count; content_hash dedup
4. **memory_conversation_logs** — always populated; role/content/sequence/event_type
5. **memory_consolidation_log** — checkpoint-resumable processing log
6. **memory_formation_failures** — retry payload for backfill
7. **memory_provider_usage** — provider/model/operation/token tracking

### 3.3 Services (11)

1. **MemoryContextBuilder** — wrapper markdown: Core preamble + retrieved context + task content; adaptive budget (5% runner context window, ceiling 8K tokens)
2. **CoreMemoryManager** — CRUD with versioning for 5 block keys; JSON schema validation for operational blocks
3. **WorkingMemoryBuffer** — Redis sorted sets (DB 2); ZADD/ZRANGEBYSCORE/ZREMRANGEBYRANK; summarization trigger; TTL 2h
4. **LongTermMemoryWriter** — embeddings + graph extraction + conv logs; adapts to mode
5. **HybridRetriever** — parallel source queries; RRF fusion (k=60); partial results on source unavailability
6. **ConsolidationService** — periodic + event-driven; Working→Core merge; vector dedup; failure backfill; resumable checkpoints
7. **ForgettingService** — tiered pruning: TTL/decay/importance; dry-run support
8. **MemoryAdapterFactory** — resolves EmbeddingProvider/ExtractionProvider via Laravel AI SDK; Redis token-bucket rate limiter
9. **Neo4jGraphStore** — Cypher wrapper; bi-temporal CRUD; app-level access control
10. **MemoryCapabilityResolver** — inspects providers + pgvector availability; determines mode
11. **MemorySettingsService** — read/write with encryption, masking, validation

### 3.4 Contracts

- **EmbeddingProvider** — embed(), embedBatch(), dimensions()
- **ExtractionProvider** — extractEntities(), scoreImportance(), summarize()

### 3.5 Jobs (Horizon supervisor-memory)

1. **MemoryWorkingBufferJob** — fire-and-forget from RunEventWriter::appendOutput()
2. **MemoryFormationJob** — from finalizeTerminal(); 5× retry [10,30,60,120,300]s
3. **MemoryConsolidationJob** — scheduled every 2h; checkpoint-resumable
4. **MemoryForgettingJob** — scheduled daily; dry-run support

### 3.6 Artisan Commands

- `memory:consolidate` (0 */2 * * *)
- `memory:prune` (0 3 * * *)
- `memory:stats` (manual)
- `memory:graph-snapshot` (manual)

### 3.7 Integration Points (existing code modifications)

1. **ExecuteAgentRunJob::handle()** — insert MemoryContextBuilder::build() before renderTokens()
2. **ExecuteAgentRunJob::finalizeTerminal()** — dispatch MemoryFormationJob
3. **ExecuteAgentRunJob::mergedEnvironment()** — add MEMORY_API_BASE_URL
4. **RunEventWriter::appendOutput()** — fire-and-forget MemoryWorkingBufferJob
5. **config/horizon.php** — add supervisor-memory
6. **config/database.php** — add Redis memory connection (DB 2)
7. **config/agent.php** — add memory context base to allowed_task_markdown_bases
8. **composer.json** — add laravel/ai and laudis/neo4j-php-client

### 3.8 HTTP API (/memory/v1/)

Settings: GET/PUT + test-connection + capabilities
Core blocks: GET list + GET/PUT/DELETE by key
Retrieval: POST /retrieve (hybrid, adapts to mode)
Working: POST /working/append + GET /working/{runId}
Diagnostics: GET /stats

### 3.9 Config: config/memory.php

Full config with: enabled, api_features_enabled, providers (extraction/summarization/embeddings with failover), working_memory (redis, max_messages, ttl, summarization_threshold), retrieval (rrf_k, weights), forgetting (decay, thresholds, retention), consolidation, formation (retries, backoff), neo4j connection, classification_default, context_injection (budget, ceiling), rate_limiting, security

---

## 4. Adversarial Reviewer — Full Build

**Source:** `adversarial-reviewer-discovery-requirements-brief.md`
**Implemented:** 0%
**Dependency:** Integrates into existing Requirements Discovery interrogation flow

### 4.1 High-Level Flow

**Summary Phase:**
1. Generate summary candidate (existing)
2. Run adversarial review against candidate + full context package
3. Pass → persist + emit `summary_ready`
4. Revise → regenerate with reviewer changes, re-review (bounded loop)
5. Needs clarification → enqueue questions to existing open-question queue → interrogation → regenerate → re-review

**Planning Phase:**
1. Generate plan candidate (existing)
2. Review against plan + locked summary + full context
3. Pass → persist + emit `plan_ready`
4. Revise → regenerate, re-review (bounded loop)
5. No clarification allowed in planning phase

### 4.2 Reviewer Contract (JSON Schema)

```json
{
  "verdict": "pass | revise | needs_clarification",
  "issues": [{"type": "string", "severity": "low|medium|high|critical", "message": "string", "evidence": "string"}],
  "required_changes": ["string"],
  "clarification_questions": ["string"],
  "confidence": 0.0-1.0,
  "review_notes": "string"
}
```

### 4.3 Context Package

Each review invocation includes: session metadata snapshot, feature/session brief, discovery findings, full Q&A transcript, current candidate artifact

### 4.4 State & Events

Metadata tracking: `summary.review_status`, `summary.review_attempts`, `summary.last_review` (and plan equivalents)

Events: summary_review_started/passed/failed/clarification_needed, plan_review_started/passed/failed

### 4.5 Config & Controls

Under interrogation config:
- `adversarial_review_enabled` (default false)
- `summary_review_max_retries`
- `plan_review_max_retries`
- `review_warn_only` (shadow mode)
- Optional reviewer model override

### 4.6 Delivery Phases

A: Reviewer schema + guard + shadow-mode integration
B: Summary gating + clarification integration
C: Plan gating integration
D: Hard-enable via config in controlled rollout

---

## 5. NL Scheduling — Complete Remaining 50%

**Source:** `natural-language-scheduling.md` (Session 5, F-011)
**Implemented:** Partial (ChatIntentParser provides some NL parsing in messenger context)
**Remaining:** Dedicated NaturalLanguageScheduleParser service, rule-based patterns, LLM fallback, JobForm.vue NL tab, active_hours_config column, nl_parse_attempts table, DispatchDueService active-hours evaluation

### 5.1 Service: NaturalLanguageScheduleParser

Orchestrates: rule-based parse → confidence check → LLM fallback if needed → NumericCronExpression validation → run preview generation

### 5.2 Rule-Based Patterns (v1)

1. `every X minutes` / `every X hours`
2. `daily at TIME`
3. `weekdays at TIME` / `weekends at TIME`
4. `every Monday/.../Sunday at TIME`
5. `every day between TIME and TIME`
6. `hourly`
7. `twice a day` → ambiguous, defaults 9am/5pm, requires confirmation

### 5.3 Async LLM Fallback

- POST /internal/api/schedule/parse → sync if rule-based ≥75%, else queued with parse_attempt_id
- GET /internal/api/schedule/parse/{id} → polling (2s interval, max 20 polls)
- WebSocket: NlParseCompleted on private-user.{user_id}
- 30s timeout; 60s idempotency window
- Rate limits: 10/min/user, 60/hour/user (LLM path only)

### 5.4 Database Changes

1. **agent_jobs.active_hours_config** — nullable JSON column; `{"start": "09:00", "end": "17:00", "days": [1,2,3,4,5]}` (ISO 1=Mon..7=Sun)
2. **nl_parse_attempts** — UUID PK, user_id FK, input_text, parser_path, confidence, cron_result, user_confirmed, parse_attempt_id; 90-day retention

### 5.5 DispatchDueService Changes

Evaluation order: cron due check → active_hours_config window/day check (if present, using Carbon::dayOfWeekIso) → dispatch or skip with structured reason metadata

### 5.6 UI

New "Natural Language" tab in JobForm.vue alongside Basic/Advanced; 200-char max input; confirmation dialog showing cron, explanation, next 5 runs, confidence

---

## 6. Org Layer — Full Build

**Source:** `agent-org-layer-requirements-brief.md`
**Implemented:** 0%
**Dependencies:** Delegation Engine must be substantially complete; reuses NL Scheduling, Messenger, MCP, Memory, Adversarial Reviewer

### 6.1 Core Concepts

- **Org Agent Profiles** — named AI employees mapped to delegatee profiles + capabilities; role_slug, role_description, narrowing-only authority overrides, optional parent/manager relation, default output schema
- **Ritual Templates** — recurring workflows with trigger (cron + tz), phase graph (ordered or DAG), phase-to-role mapping, context inputs, verification strategy, delivery targets
- **Council Templates** — member list with perspective labels, shared evidence payload, member response schema, synthesis mode (majority/weighted/chair_decides), final report structure
- **Cost & Governance** — per-agent token/runtime rollups, per-ritual cost snapshots, threshold policies (warn/approve/stop), escalation events

### 6.2 Reuse Contracts (Locked)

The Org Layer must compose over existing systems without modifying their internals:

1. **Delegation Runtime** — graph/task states, retries, recovery, verification pipeline remain delegated
2. **MCP** — scope dimensions: tenant/environment/role; transport: poll + websocket; versioned tools
3. **Memory** — no-API vs API mode; failures never block; context injection via wrapper files
4. **Messenger** — Slack + Telegram Phase A (webhook implemented; local-first runtime pending per gap brief); signed token identity linking; destructive action confirmation
5. **NL Scheduling** — cron canonical; rule-based first, LLM fallback for low confidence
6. **Adversarial Reviewer** — bounded review loops; schema-validated JSON output; feature flags

### 6.3 Data Model (8 additive tables)

1. **org_agent_profiles** — name (unique per user), role_slug, role_description, delegatee_profile_id FK, capability bindings, authority overrides, parent_id (self-referential nullable FK), default_output_schema_json
2. **org_reporting_edges** — from_agent_id, to_agent_id, edge_type (reports_to/advises/reviews)
3. **org_ritual_templates** — trigger_config (cron + tz + NL source), phase_graph_json, role_mapping_json, context_config_json, verification_requirements_json, delivery_targets_json
4. **org_ritual_runs** — ritual_template_id FK, delegation_graph_id FK, status (draft/scheduled/queued/running/waiting_approval/reviewing/succeeded/failed/cancelled/partial), lifecycle timestamps
5. **org_council_templates** — member_config_json (agents + perspective labels), evidence_schema_json, response_schema_json, synthesis_mode, report_sections_json
6. **org_cost_ledgers** — agent_profile_id FK, ritual_run_id nullable FK, token_count, runtime_seconds, cost_estimate_usd, period_start/end
7. **org_escalations** — source_type/id (polymorphic), escalation_type, threshold_config_json, status (pending/approved/rejected/expired), resolution_json, resolved_by, resolved_at
8. **org_artifact_reviews** — artifact_type (summary/plan), artifact_id, reviewer_verdict, issues_json, evidence_json, review_attempt_number

### 6.4 API Surface (/agent/api/v1/org/)

- Agents: GET/POST/PUT
- Rituals: GET/POST + POST run/pause/resume
- Ritual runs: GET
- Councils: GET/POST
- Costs: GET summary
- Escalations: POST resolve
- Reviews: GET

### 6.5 Events (14)

org_ritual_scheduled, org_ritual_started, org_ritual_phase_completed, org_ritual_escalation_requested, org_ritual_escalation_resolved, summary_review_started/passed/failed/clarification_needed, plan_review_started/passed/failed, org_budget_threshold_exceeded, org_ritual_completed

### 6.6 Delivery Phases

A: Org Foundation (profiles, edges, flags, CRUD APIs, base events)
B: Ritual Runtime Integration (templates, scheduler trigger, DelegationGraph mapping, lifecycle APIs)
C: Council and Quality Gates (templates, synthesis flow, adversarial reviewer integration)
D: Governance and Surfaces (cost ledgers, thresholds, escalation UX, messenger/MCP parity)
E: Hardening and Compatibility (CI gates, rollout controls, resilience testing, observability)

### 6.7 Open Questions (Require Resolution in Discovery)

1. Org agent profiles user-scoped in v1 or workspace-shared immediately?
2. Council synthesis: deterministic rules-first or model-mediated with deterministic fallback?
3. Hard-stop budget: terminate in-flight branches or only block undispatched?
4. Default messenger notification granularity to avoid alert fatigue?
5. Ritual template import/export JSON in v1?
6. Session-to-session orchestration exposed as user tools or internal only?

---

## 7. Multi-Agent Coordination Enhancements — New

**Source:** Analysis of Claude Code Agent Teams + OpenAI Codex multi-agent patterns
**Integration point:** Primarily enriches Org Layer councils and delegation execution

### 7.1 Council Deliberation Phase (from Agent Teams mailbox pattern)

**Current design gap:** Council workflows fan out evidence, collect responses, synthesize. No inter-member communication during execution.

**Enhancement:** Add optional `deliberation_rounds` to council templates. Between fan-out and synthesis, council members can challenge each other's findings through a structured message exchange.

**Implementation:**
- Add `deliberation_config` to org_council_templates: `{"enabled": false, "max_rounds": 2, "challenge_prompt_template": "..."}`
- When enabled, after initial member responses are collected, spawn a deliberation phase where each member sees others' responses and can revise
- Deliberation runs as additional DelegationTasks within the same graph (member response → deliberation → synthesis)
- Track deliberation in org_artifact_reviews with `review_type: 'council_deliberation'`
- This maps to the "competing hypotheses" pattern from Agent Teams where teammates explicitly disprove each other's theories

### 7.2 Self-Claiming from Ready Pool (from Agent Teams shared task list)

**Current design:** DelegateeAssigner assigns centrally. Coordinator dispatches ready tasks when parallel slots open.

**Enhancement:** Allow delegatees that finish early to pull from the ready pool without waiting for Coordinator round-trip.

**Implementation:**
- Add `self_claim_enabled` boolean to DelegationGraph (default false for backward compatibility)
- When enabled, AttemptSpawner's completion callback checks for ready tasks with `sequence_order` priority before reporting back to Coordinator
- File-lock-style concurrency: use TaskStateTransitionService atomic transition `ready→assigned` to prevent double-claim
- Coordinator remains the safety net via reconciler
- Reduces latency between task completion and next task dispatch

### 7.3 Monitor Role Type (from Codex role types)

**Current design gap:** DelegateeProfiles have runner_type and capabilities but no concept of long-running observational tasks.

**Enhancement:** Add `monitor` capability to the delegation capability seed list and support extended runtime semantics.

**Implementation:**
- Add `monitor` to capabilities_seed in config/delegation.php
- Monitor-capable profiles get extended max_runtime_seconds (configurable, default 3600)
- Monitor tasks can emit periodic heartbeat events (DelegationEvent with event_type 'monitor_heartbeat')
- Reconciler treats monitor tasks differently: heartbeat timeout instead of completion timeout
- Use cases: CI pipeline watching, deployment monitoring, log tailing during org rituals

### 7.4 In-Place Revision Loop (from Agent Teams TeammateIdle hook pattern)

**Current design gap:** VerificationPipeline either passes or fails. Failed verification triggers RecoveryHandler (retry → re-delegate → escalate → abort).

**Enhancement:** Before entering the recovery chain, allow a single "revise in place" attempt where the same delegatee receives feedback and tries again within the same verification context.

**Implementation:**
- Add `revision_allowed` boolean to verification_strategy steps (default false)
- When a verification step fails with revision_allowed=true, instead of triggering RecoveryHandler:
  1. Create a new DelegationAttempt with the original prompt + verification feedback
  2. Dispatch to the same delegatee (no re-assignment)
  3. Re-run verification pipeline from the beginning
  4. If revision also fails, then trigger normal RecoveryHandler chain
- This reduces churn from full retry cycles for minor issues caught by AutomatedCheckStep or AiCriticStep
- Limit: 1 revision attempt per verification step per task (configurable)

### 7.5 Ad-Hoc Team Formation (from both Agent Teams and Codex)

**Current design gap:** Both external tools let users describe a team in natural language and have it materialize. Agent requires: define DelegateeProfiles → create DelegationGraph JSON → validate → start. No conversational team formation.

**Enhancement:** Add an `ad_hoc_ritual` capability to the Org Layer that takes a natural language description and auto-generates a DelegationGraph.

**Implementation:**
- New endpoint: `POST /agent/api/v1/org/rituals/ad-hoc` accepting `{"description": "...", "team_size_hint": 3}`
- Uses existing LLM adapter (InterrogationRunnerAdapter pattern) to decompose description into tasks
- LLM output must conform to DelegationGraphBuilder's linear-chain or DAG JSON format
- Auto-assigns from org roster based on DelegateeAssigner matching
- Output goes through ContractValidator and GraphBuilder — all policy guarantees preserved
- Returns created graph_id; user can review in DelegationGraphShow before starting
- Messenger integration: "spin up a team for X" → ad-hoc ritual creation
- Phase 1: linear-chain only; Phase 2: DAG decomposition

---

## 8. Messenger Control Plane — Local-First Runtime + Phase B

**Source:** `messenger-control-plane-v6.md` (Session 1) + `local-first-messaging-gap-brief.md`
**Implemented:** ~60% — webhook-mode adapters (Slack + Telegram), core services, UI, models, controllers
**Gap:** Current implementation is webhook-centric only; no local connector runtime exists despite discovery specifying local-first as default
**Remaining:** Local-first gateway runtime (critical), mode fidelity enforcement, webhook ingress productization, Discord/WhatsApp adapters, org ritual commands

### 8.1 Local-First Runtime Gap (Critical — W1)

The discovery spec defined local connector mode as the default for Slack (Socket Mode) and Telegram (long polling). The current implementation only supports webhook mode. This is the primary gap.

**Missing capabilities:**

1. **MessengerGatewayManager** — persistent process manager for long-running gateway workers, one per active local-mode connector
2. **Provider gateway workers:**
   - **SlackSocketWorker** — Slack Socket Mode WebSocket client (outbound connection, no public URL required)
   - **TelegramPollingWorker** — Telegram Bot API long-polling loop (`getUpdates` with offset tracking)
   - **DiscordGatewayWorker** — Discord Gateway WebSocket client (Phase B, once adapter built)
3. **Provider lifecycle interface** — `start()`, `stop()`, `health()`, reconnect with exponential backoff
4. **Runtime state persistence** — connector session states: connected/reconnecting/failed/stopped; last_heartbeat_at timestamp; surfaced in UI and health endpoints
5. **Supervisor integration** — per-connector long-running workers managed alongside Horizon; graceful shutdown on `agent:restart`

**Implementation approach:**
- New Artisan command: `messenger:gateway {connector_id?}` — starts gateway worker(s) for local-mode connectors
- Workers run as persistent processes (like `horizon` or `reverb:start`), not queue jobs
- Each worker maintains its own reconnection loop with configurable backoff (base 1s, max 300s, jitter ±20%)
- Inbound messages from gateway workers are dispatched to the same processing pipeline as webhook messages (ChatIntentParser → ChatActionExecutor)
- `agent:restart` must include gateway workers in its managed services list

### 8.2 Mode Fidelity Enforcement (Critical — W2)

Current implementation allows selecting "local" mode in config but has no runtime to execute it.

**Required changes:**

1. **Mode-aware provider schema** — update ConnectorAccount validation to reflect actually supported modes per provider; prevent selecting unavailable modes
2. **Mode-dependent credential validation** — local mode requires bot token only; webhook mode requires bot token + webhook secret + public URL; validate at connector creation and test-connection
3. **Mode-dependent install/test flows** — `agent:install` connector setup must test actual connectivity for selected mode (socket handshake for local, webhook challenge/verification for webhook)
4. **UI truth enforcement** — connector status in UI must reflect real worker state (from gateway manager health), not static config status; disable local mode option until gateway runtime is available for that provider

### 8.3 Webhook Ingress Productization (High — W3)

When webhook mode is selected, networking setup is currently left entirely to the user.

**Required changes:**

1. **Ingress profile in install flow** — `agent:install` asks for ingress strategy: reverse proxy (user-managed) or tunnel (built-in support, if productized)
2. **Callback readiness checks** — provider-specific webhook validation probes: TLS verification, URL reachability, challenge/verification response testing
3. **Actionable diagnostics** — `messenger:diagnose {connector_id}` command that tests webhook endpoint reachability, TLS, signature verification, and reports specific failures
4. **Provider-specific webhook registration helpers** — automated Slack app webhook URL configuration, Telegram `setWebhook` API call, etc.

### 8.4 Discord Adapter (Phase B — W4)

- Gateway WebSocket connector (local mode default via DiscordGatewayWorker)
- Webhook mode fallback with Ed25519 signature verification
- Timestamp-based replay protection (300s window)
- Native thread support in channels, no threading in DMs (fallback: edit → single)

### 8.5 WhatsApp Adapter (Phase B — W4)

**Required decision before implementation:** WhatsApp protocol strategy.

- **Option A: Cloud API webhook-first** (current discovery spec) — webhook-only, HMAC-SHA256 signature verification, event ID deduplication, quote reply threading. No local mode possible.
- **Option B: WhatsApp Web session (OpenClaw-like)** — local-first via Baileys or equivalent library, session-based connection, no public URL required. Significant implementation complexity; would require Node.js sidecar process.

This decision must be resolved in discovery before implementation begins. Current brief assumes Option A unless overridden.

### 8.6 Control Plane Completeness (Medium — W5)

**Reliability hardening for existing implementation:**

1. **Action handler integration verification** — end-to-end testing of all 9 action handlers (jobs.create/update/delete, runs.list_active/stop/retry/run_now/steer, jobs.list) against real job/run operations
2. **Status propagation alignment** — connector and action state transitions must be consistently emitted as events and surfaced in UI, queues, and audit log
3. **Queue/health metric consistency** — align queue names in health telemetry with actual Horizon queue names; verify backlog depth reporting

### 8.7 Org Ritual Messenger Commands

Once Org Layer is implemented, add messenger commands for:
- Listing rituals and their schedules
- Triggering manual ritual runs
- Pausing/resuming ritual schedules
- Resolving escalation approvals
- Viewing ritual run status and cost summaries

### 8.8 Acceptance Criteria for Local-First Readiness

Feature is local-first ready **only** when all are true:

1. Slack runs in Socket Mode without requiring a public webhook URL
2. Telegram runs in long-poll mode without requiring a public webhook URL
3. Selected webhook mode includes validated ingress with passing provider callback checks
4. Mode shown in UI equals the active runtime mode (not just config)
5. Connector health reflects real worker state (connected/reconnecting/failed), not static config status
6. End-to-end tests prove create/list/control-run flows per supported provider per mode
7. `agent:install` correctly configures and starts the appropriate mode
8. `agent:restart` gracefully restarts gateway workers alongside Horizon/Reverb

### 8.9 Required Decisions Before Implementation

1. **WhatsApp architecture:** Cloud API webhook-first (Option A) vs WhatsApp Web session (Option B)
2. **Local-first Phase 1 scope:** Which providers must support no-public-URL operation before claiming local-first readiness (proposed: Slack + Telegram)
3. **Webhook ingress productization:** Built-in tunnel support vs documented reverse-proxy-only support

---

## 9. Requirements Discovery — Build Lifecycle Hardening

**Source:** `requirements-discovery-feature.md`
**Implemented:** ~85% (core discovery, interrogation, summary, planning, build task generation)
**Remaining:** Build execution edge cases, approval/rate-limit prompt handling, pause/resume robustness

### 9.1 Outstanding Items

- Build execution approval prompt detection and resolution flow
- Rate-limit prompt detection and automated backoff
- Pause/resume state preservation across process restarts
- Clarification submission during active build execution
- Session completion when all tasks finish (end-to-end lifecycle)
- Integration with Adversarial Reviewer when available (quality gates on summary/plan)

---

## 10. MCP Server — Org Endpoint Extensions

**Source:** `agent-mcp.md` (Session 4) + `agent-org-layer-requirements-brief.md`
**Implemented:** Base MCP server with Session 4 tool manifest
**Remaining:** Org-specific MCP endpoints once Org Layer ships

### 10.1 New Org MCP Tools (Proposed)

Following v5 schema/version discipline:

| tool_name | stability | role |
|-----------|-----------|------|
| org.agents.list | stable | viewer/operator/admin |
| org.rituals.list | stable | viewer/operator/admin |
| org.rituals.run | stable | operator/admin |
| org.rituals.pause | stable | operator/admin |
| org.rituals.resume | stable | operator/admin |
| org.ritual_runs.show | stable | viewer/operator/admin |
| org.escalations.resolve | stable | operator/admin |
| org.costs.summary | stable | viewer/operator/admin |

Scope enforcement: required tenant + environment, role-based access per tool, deny on mismatch.

---

## 11. Implementation Phases — Consolidated Roadmap

### Phase 1: Delegation Engine Completion (Foundation)

**Priority:** Critical — all other systems depend on this
**Estimated scope:** ~60 files

1. Build all 9 remaining Eloquent models with relationships, casts, scopes
2. Build GraphStateTransitionService + TaskStateTransitionService
3. Build DelegationGraphBuilder (DAG + linear-chain) with Kahn's algorithm
4. Build ContractValidator + ContractEnforcer
5. Build DelegateeAssigner with sliding-window metrics ranking
6. Build AttemptSpawner with Bus::chain completion callbacks
7. Build DelegationCoordinator event subscriber (happy path)
8. Build VerificationPipeline + 3 step implementations
9. Build RecoveryHandler with heuristic classification
10. Build DelegationReconciler + DelegateeMetricsRecomputer
11. Build DelegationEventWriter + DelegationBroadcastSubscriber
12. Build 3 controllers + 2 policies + routes
13. Build 8 Vue pages including dagre DAG visualization
14. Configure Horizon supervisor-delegation + scheduler registrations
15. Run DelegationCapabilitySeeder
16. **Verification:** Full feature test suite; all existing tests remain green

### Phase 2: NL Scheduling Completion

**Priority:** High — needed for Org Layer ritual scheduling
**Estimated scope:** ~15 files

1. Build NaturalLanguageScheduleParser service with rule-based patterns
2. Build async LLM fallback with polling + websocket
3. Add active_hours_config migration to agent_jobs
4. Add nl_parse_attempts table
5. Extend DispatchDueService with active-hours evaluation
6. Build JobForm.vue NL tab with confirmation dialog
7. Add rate limiting middleware for LLM path
8. Add telemetry (DB table + log redaction)
9. **Verification:** 18-scenario test suite per discovery spec

### Phase 3: Memory Architecture

**Priority:** High — enables context-aware execution across all systems
**Estimated scope:** ~45 files

1. Build 7 migrations + 7 models
2. Build MemoryServiceProvider with feature-flag gating
3. Build CoreMemoryManager + WorkingMemoryBuffer
4. Build LongTermMemoryWriter with mode-adaptive behavior
5. Build HybridRetriever with RRF fusion
6. Build MemoryAdapterFactory + EmbeddingProvider/ExtractionProvider adapters
7. Build Neo4jGraphStore
8. Build MemoryCapabilityResolver + MemorySettingsService
9. Build ConsolidationService + ForgettingService
10. Build 4 Horizon jobs
11. Build 4 Artisan commands
12. Apply 4 integration point modifications to existing code
13. Build Memory API endpoints
14. Build config/memory.php
15. **Verification:** Both modes tested; memory failures never block runs

### Phase 4: Adversarial Reviewer

**Priority:** Medium — quality gates for Org Layer
**Estimated scope:** ~12 files

1. Build reviewer contract schema + JSON validator
2. Build summary review integration with bounded loop
3. Build clarification routing to existing open-question queue
4. Build plan review integration (no clarification)
5. Add metadata tracking + events
6. Add config/controls (feature flags, retry caps, shadow mode)
7. Shadow-mode rollout before hard-enable
8. **Verification:** Pass-through, revise loop, clarification path, retry cap, flag-off regression

### Phase 5: Org Layer Foundation + Rituals (Phases A-B)

**Priority:** Medium — core org functionality
**Estimated scope:** ~35 files
**Depends on:** Delegation, NL Scheduling

1. Build 8 new migrations + models
2. Build OrgAgentProfile CRUD with authority narrowing validation
3. Build reporting edges with graph validation
4. Build ritual template CRUD with phase graph validation
5. Build ritual scheduler trigger → DelegationGraph instantiation mapping
6. Build ritual run lifecycle (status transitions, events)
7. Build Org API endpoints
8. Build org feature flags and middleware
9. **Verification:** Ritual scheduling → delegation graph → execution → completion end-to-end

### Phase 6: Org Layer Councils + Quality Gates (Phase C)

**Priority:** Medium
**Estimated scope:** ~15 files
**Depends on:** Org Foundation, Adversarial Reviewer

1. Build council template CRUD
2. Build council execution flow (fan-out → collect → synthesize)
3. Implement synthesis modes (majority/weighted/chair_decides)
4. Integrate adversarial reviewer gates on ritual artifacts
5. Build council deliberation phase (Section 7.1 enhancement)
6. **Verification:** Council ritual with deliberation; reviewer gate pass/fail

### Phase 7: Org Layer Governance + Surfaces (Phase D)

**Priority:** Medium
**Estimated scope:** ~20 files
**Depends on:** Org Councils

1. Build cost ledger tracking (per-agent, per-ritual)
2. Build threshold policies (warn/approve/stop)
3. Build escalation management (creation, resolution, expiration)
4. Build messenger commands for org rituals
5. Build MCP org endpoints
6. Implement self-claiming from ready pool (Section 7.2)
7. Implement monitor role type (Section 7.3)
8. Implement in-place revision loop (Section 7.4)
9. **Verification:** End-to-end cost tracking; escalation resolution; messenger + MCP parity

### Phase 8: Messenger Local-First Runtime

**Priority:** High — current implementation does not deliver planned local-first behavior
**Estimated scope:** ~25 files
**Can parallelize with:** Phases 5-7 (no dependency on Org Layer)

1. Build MessengerGatewayManager with provider lifecycle interface
2. Build SlackSocketWorker (Socket Mode WebSocket client)
3. Build TelegramPollingWorker (long-polling with offset tracking)
4. Build `messenger:gateway` Artisan command with supervisor integration
5. Update `agent:restart` to include gateway workers
6. Enforce mode-aware provider schema + credential validation on ConnectorAccount
7. Update `agent:install` connector flow with mode-specific tests (socket handshake / webhook challenge)
8. Build `messenger:diagnose` command for webhook readiness probes
9. Build callback validation probes (TLS, reachability, provider challenge/verification)
10. Update UI to reflect real worker state from gateway manager health (not static config)
11. Add end-to-end tests per mode per provider (local + webhook × Slack + Telegram)
12. **Verification:** Slack Socket Mode works without public URL; Telegram long-poll works without public URL; mode in UI matches runtime; health reflects real state

### Phase 9: Ad-Hoc Teams + Messenger Phase B Adapters

**Priority:** Lower
**Estimated scope:** ~20 files
**Depends on:** Org Governance, Messenger Local-First Runtime

1. Build ad-hoc ritual endpoint with LLM decomposition (Section 7.5)
2. Build Discord adapter with DiscordGatewayWorker (local mode) + webhook fallback
3. Build WhatsApp adapter per resolved architecture decision (Section 8.5)
4. Register Discord/WhatsApp in MessengerGatewayManager
5. Control plane completeness hardening (Section 8.6): action handler integration, status propagation, metric alignment
6. Integration test: "spin up a team for X" via messenger
7. **Verification:** Ad-hoc team from NL; Discord/WhatsApp end-to-end; all 4 providers in both modes

### Phase 10: Hardening + Observability (Phase E)

**Priority:** Lower
**Estimated scope:** ~10 files

1. CI compatibility gates for org MCP endpoints
2. Structured correlation IDs across messenger → org → delegation → review
3. Observability dashboards: ritual lifecycle, escalation frequency, reviewer metrics, cost thresholds, per-agent contribution
4. Queue depth and dead-letter visibility
5. Resilience testing (degraded memory, provider outages, reconciler recovery, gateway reconnection)
6. Requirements Discovery build lifecycle hardening
7. **Verification:** Full regression suite; load testing at expected volume

---

## 12. Cross-Cutting Concerns

### 12.1 Feature Flags

All new systems gated behind feature flags for incremental rollout:

| Flag | Default | System |
|------|---------|--------|
| `delegation.enabled` | false | Delegation Engine |
| `delegation.ui_enabled` | false | Delegation UI |
| `MEMORY_ENABLED` | false | Memory Architecture |
| `MEMORY_API_ENABLED` | false | Memory LLM/Embeddings |
| `adversarial_review_enabled` | false | Adversarial Reviewer |
| `review_warn_only` | true | Shadow mode for reviewer |
| `org.enabled` | false | Org Layer |
| `org.councils_enabled` | false | Council workflows |
| `org.governance_enabled` | false | Cost/escalation governance |
| `messenger.local_mode_enabled` | false | Gateway worker runtime |

### 12.2 Queue Topology

| Supervisor | Queue | Timeout | MaxProcesses |
|-----------|-------|---------|-------------|
| supervisor-agent | agent | 600 | env-driven |
| supervisor-delegation | delegation | 900 | env(HORIZON_DELEGATION_MAX_PROCESSES, 2) |
| supervisor-memory | memory | 300 | env(HORIZON_MEMORY_MAX_PROCESSES, 3) |
| supervisor-interrogation | interrogation | 600 | existing |
| messenger-gateway | N/A (persistent process) | N/A | 1 per local-mode connector |

### 12.3 Additive-Only Contract

All implementations must be additive-only:
- No modifications to existing models, services, or migrations
- New tables with foreign keys referencing existing entities
- Integration via events, callbacks, and service injection
- Existing test suite must remain green at every phase

### 12.4 Audit Strategy

- User-initiated actions → AuditLogger (recordUserAction) + domain events
- Automated coordination → domain events only
- Summary audit log entry at terminal status for graphs, rituals, reviews
- Structured correlation IDs across all subsystems

### 12.5 Security Invariants

- Authority scopes only narrow, never widen (ContractEnforcer)
- CommandPolicy/PathPolicy/EnvPolicy boundaries enforced at every execution boundary
- No raw command payloads from messenger, MCP, or org endpoints
- Encrypted secrets (memory provider keys, connector credentials) via Laravel encrypted cast
- Memory failures never block agent runs

---

## 13. Testing Strategy

### Per-Phase

Each phase includes its own test suite as specified in the source discovery documents. Tests must be written alongside implementation, not deferred.

### Integration Tests (Cross-Phase)

After Phases 5-6:
1. One council ritual end-to-end (schedule → delegation → council → review → complete)
2. One non-council ritual end-to-end
3. One escalation-resume flow
4. One degraded-memory path that still completes execution

### Regression

- All existing tests green at every commit
- Feature-flag-off behavior verified at each phase
- Backward compatibility for all nullable column additions

---

## 14. Estimated Total Scope

| Category | Files | Models | Services | Migrations | API Endpoints |
|----------|-------|--------|----------|------------|---------------|
| Delegation completion | ~60 | 9 | 17 | 0 (done) | ~20 |
| NL Scheduling | ~15 | 1 | 3 | 2 | 3 |
| Memory | ~45 | 7 | 11 | 7 | 12 |
| Adversarial Reviewer | ~12 | 0 | 3 | 0 | 0 |
| Org Layer | ~70 | 8 | ~10 | 8 | 14 |
| Multi-Agent Enhancements | ~15 | 0 | 4 | 1 | 1 |
| Messenger Local-First Runtime | ~25 | 0 | 5 | 0 | 2 |
| Messenger Phase B Adapters | ~15 | 0 | 4 | 0 | 0 |
| Hardening | ~10 | 0 | 0 | 0 | 8 (MCP) |
| **Total** | **~267** | **25** | **~57** | **18** | **~60** |

---

## 15. Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Delegation complexity delays Org Layer | High | Delegation Phase 1 is the critical path; parallelize Memory (Phase 3) |
| Messenger local-first runtime stability | High | Gateway workers are long-running processes with reconnection complexity; extensive testing per provider/mode; graceful degradation to webhook mode |
| WhatsApp architecture decision delays Phase B | Medium | Resolve in discovery before Phase 9; Cloud API (Option A) is lower-risk default |
| Neo4j operational burden | Medium | No-API mode fully functional without Neo4j; graph features degrade gracefully |
| LLM provider rate limits in production | Medium | Proactive token-bucket rate limiting; degradation to rule-based/BM25-only |
| Queue congestion across 4 supervisors | Low | Dedicated queues with independent scaling; reconcilers as safety nets |
| Org Layer open questions unresolved | Medium | Must resolve in discovery session before Phase 5 starts |
| Gateway worker process management | Medium | Workers must survive alongside Horizon/Reverb; `agent:restart` must manage all; supervisor integration tested |

---

## Appendix A: Source Document Index

| Document | Session | Status | Version |
|----------|---------|--------|---------|
| reconstructed-intelligent-delegation-for-agent-v3.md | 99 | Latest | v3 |
| agent-org-layer-requirements-brief.md | — | Latest | v1 |
| adversarial-reviewer-discovery-requirements-brief.md | — | Latest | v1 |
| agent-mcp.md | 4 | Baseline (all versions identical) | v1 |
| agent-memory-delegation.md | 3 | Baseline (both versions identical) | v1 |
| messenger-control-plane-v6.md | 1 | Latest | v6 |
| local-first-messaging-gap-brief.md | — | Latest (gap analysis) | v1 |
| natural-language-scheduling.md | 5 | Latest | v1 |
| requirements-discovery-feature.md | — | Latest | v1 |

## Appendix B: External Reference Analysis

| Source | Key Patterns Adopted | Patterns Not Applicable |
|--------|---------------------|------------------------|
| Claude Code Agent Teams | Council deliberation (mailbox), self-claiming (shared task list), quality gate hooks (TeammateIdle/TaskCompleted), ad-hoc team formation | Session-based ephemeral teams, split-pane display, permission inheritance without narrowing |
| OpenAI Codex Multi-Agent | Monitor role type (long-running observation), concurrency limits (confirmed existing design), role-based configuration | Thread management (Agent uses persistent graphs), auto-spawning (Agent uses explicit orchestration) |
