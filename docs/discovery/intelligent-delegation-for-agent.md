# Requirements Discovery Summary

Session: 2

# Delegation Engineering — Complete Requirements Discovery

Build a first-class delegation layer on top of Agent's existing Laravel 12 + Inertia + Vue job orchestration platform, enabling complex work to be decomposed into verifiable delegated tasks with explicit authority, accountability, trust-aware assignment, and adaptive re-assignment.

## Architecture Decisions

1. **Integration** — Orchestration layer above AgentJob. DelegationGraph/DelegationTask/DelegationAttempt spawn AgentJobRun as primitives. Existing pipeline untouched.
2. **Execution** — Event-driven reactive. Stateless handlers dispatch downstream on task completion.
3. **Recovery** — RecoveryEvaluator: hardcoded logic, config thresholds, runtime context + trust metrics. Actions: retry, re-delegate, escalate, abort.
4. **Delegatees** — Runner type + config profile. type column (ai_runner/human), MVP ai_runner only.
5. **Verification** — Ordered pipeline: automated_check, ai_critic, human_approval. Each produces DelegationVerificationResult.
6. **Graph Creation** — API-first: raw JSON DAG + linear-chain shorthand. DelegationGraphBuilder normalises. UI draft review/edit.
7. **Contracts** — JSON contract_json. Authority enforced by ContractEnforcer narrowing PathPolicy/CommandPolicy/EnvPolicy.
8. **Events** — Internal domain events + DelegationBroadcastSubscriber. Dual Reverb/Echo channels: per-user summary + per-graph detail.
9. **Trust Metrics** — Sliding window snapshots (24h/7d). Hybrid: event-triggered 60s throttle + 15min scheduled fallback.
10. **Capabilities** — Seeded reference table: code_generation, code_review, testing, linting, file_editing, refactoring, documentation.
11. **Concurrency** — Per-graph max_parallel_tasks + per-user cap (global default 3, per-user override). Max 25 tasks/graph.
12. **Cancellation** — Per-graph kill/drain (default drain). Cancelling intermediate state.
13. **Soft Deletes** — Graphs soft-deleted. Children retained permanently.

## State Machines

### DelegationGraph States
- **draft** — created via API, editable in UI. Transitions: draft->approved (on approve action after validation passes).
- **approved** — validated and ready. Transitions: approved->running (on start action, dispatches root tasks), approved->cancelled (on cancel).
- **running** — tasks actively dispatching/executing. Transitions: running->completed (all tasks completed), running->failed (unrecoverable task failure, abort), running->cancelling (on cancel action).
- **cancelling** — drain mode: no new dispatches, waiting for active tasks. kill mode: SIGTERM/SIGKILL active runs then immediate transition. Transitions: cancelling->cancelled (all active tasks terminal).
- **completed** — terminal success.
- **failed** — terminal failure.
- **cancelled** — terminal cancellation.

### DelegationTask States
- **pending** — awaiting dependency resolution. Transitions: pending->ready (all upstream dependencies completed).
- **ready** — dependencies met, awaiting dispatch slot (parallelism cap). Transitions: ready->running (dispatched to delegatee, AgentJobRun spawned), ready->cancelled (graph cancelled).
- **running** — AgentJobRun in progress. Transitions: running->verifying (run succeeded, verification pipeline starts), running->failed (run failed, recovery evaluator invoked).
- **verifying** — verification pipeline processing steps. Transitions: verifying->pending_human_approval (human_approval step reached), verifying->completed (all steps passed), verifying->failed (step failed, recovery evaluator invoked).
- **pending_human_approval** — blocked on operator action. Transitions: pending_human_approval->verifying (approved, pipeline continues), pending_human_approval->failed (rejected).
- **completed** — terminal success, all verification passed.
- **failed** — terminal failure after recovery exhausted.
- **cancelled** — terminal, graph cancelled.

### DelegationAttempt States
- **running** — AgentJobRun dispatched and active. Transitions: running->succeeded, running->failed.
- **succeeded** — run completed with exit code 0.
- **failed** — run failed (non-zero exit, timeout, killed, error).

## API Contracts

### POST /api/v1/delegation/graphs — Create Graph (Raw DAG)
Request: `{"name": "Deploy feature X", "config": {"max_parallel_tasks": 3, "cancellation_policy": "drain"}, "tasks": [{"key": "lint", "label": "Run linter", "delegatee_id": 1, "contract": {"required_capabilities": ["linting"], "authority": {"allowed_paths": ["/Users/dev/project/src"]}, "criticality": "low", "budget": {"max_duration_seconds": 120}, "verification_steps": [{"type": "automated_check", "command": "phpstan analyse"}]}}, {"key": "test", "label": "Run tests", "delegatee_id": 2, "contract": {"required_capabilities": ["testing"], "authority": {"allowed_paths": ["/Users/dev/project"]}, "criticality": "high", "budget": {"max_duration_seconds": 600}, "verification_steps": [{"type": "automated_check", "command": "phpunit"}]}}], "edges": [{"from": "lint", "to": "test"}]}`
Response: `{"data": {"id": 1, "status": "draft", ...}}`

### POST /api/v1/delegation/graphs — Create Graph (Linear Chain Shorthand)
Request: `{"name": "Sequential pipeline", "config": {"max_parallel_tasks": 1}, "chain": [{"label": "Step 1: Lint", "delegatee_id": 1, "contract": {...}}, {"label": "Step 2: Test", "delegatee_id": 2, "contract": {...}}, {"label": "Step 3: Deploy", "delegatee_id": 3, "contract": {...}}]}`
GraphBuilder auto-generates edges: step1->step2->step3. Response same as DAG format.

### PUT /api/v1/delegation/graphs/{id} — Edit Draft Graph
Only allowed when status=draft. Accepts same body as create. Returns updated graph.

### POST /api/v1/delegation/graphs/{id}/approve — Approve Graph
Validates: contract schemas, capabilities exist, DAG integrity (no cycles/orphans), task count <=25, delegatee capability match. Transitions draft->approved. Returns 422 with validation errors if invalid.

### POST /api/v1/delegation/graphs/{id}/start — Start Execution
Only when status=approved. Checks per-user graph concurrency cap. Transitions approved->running. Dispatches root tasks (no upstream dependencies). Returns 409 if concurrency cap exceeded.

### POST /api/v1/delegation/graphs/{id}/cancel — Cancel Graph
Allowed when status in (approved, running). If approved: immediate transition to cancelled. If running: applies cancellation_policy (kill or drain). Idempotent — repeated calls return current state without error.

### GET /api/v1/delegation/graphs/{id} — Show Graph with Tasks
Returns graph, all tasks with current status, edges, active attempts, latest verification results.

### GET /api/v1/delegation/graphs — List Graphs
Supports filters: status, created_at range. Pagination. Excludes soft-deleted by default.

### POST /api/v1/delegation/graphs/{id}/tasks/{taskId}/approve-verification — Human Approval
Operator approves or rejects a pending human_approval verification step. Body: `{"verdict": "approved"}` or `{"verdict": "rejected", "reason": "..."}`.

## Non-Functional Limits

- **Max tasks per graph**: 25 (hard limit, enforced at creation, edit, and approval)
- **Default max_parallel_tasks**: 3 (per-graph, configurable 1-25)
- **Default max_concurrent_graphs_per_user**: 3 (global config, per-user override via delegation_user_settings)
- **Cancellation semantics**: kill = SIGTERM + 10s grace + SIGKILL on all active runs, immediate transition; drain = block new dispatches, wait for running tasks to reach terminal, then transition. Both are idempotent.
- **Metrics throttle**: 60s per delegatee via cache lock; scheduled fallback every 15min
- **API idempotency**: approve, start, cancel are all idempotent — repeated calls on already-transitioned graphs return current state

## Observability and Ops

- **Key metrics to expose**: graphs_running_total, graphs_completed_total, graphs_failed_total, tasks_dispatched_total, tasks_failed_total, recovery_actions_total (by type: retry/redelegate/escalate/abort), verification_pass_rate, avg_graph_completion_duration_ms, delegatee_success_rate (from snapshots), pending_human_approvals_count
- **Failure alerts**: graph entered failed state, recovery exhausted (max_recovery_attempts reached), delegatee trust below min_trust_for_retry, human approval pending > 30min, graph running > expected duration
- **Reconciliation safeguard**: lightweight periodic DelegationReconciler (scheduled every 2 minutes) scans for stuck tasks — tasks in running state whose AgentJobRun has reached terminal but task was not advanced (missed event). Transitions them appropriately and logs reconciliation. Modelled after existing ReconcileActiveRunsService.
- **All delegation actions logged**: via existing AgentAuditLog with target_type values: delegation_graph, delegation_task, delegation_attempt, delegation_verification, delegatee

## Rollout Plan

- **Phase 1 — Backend foundation** (feature flag: delegation.enabled=false): Migrations, models, services (GraphBuilder, ContractValidator, ContractEnforcer). No UI, no execution. Flag gates all API routes.
- **Phase 2 — Execution engine** (delegation.enabled=true, delegation.ui_enabled=false): DelegationCoordinator, RecoveryEvaluator, VerificationPipeline, DelegateeMetricsRecomputer, DelegationReconciler. API endpoints live. Graphs can be created and executed via API only.
- **Phase 3 — Broadcast and UI** (delegation.ui_enabled=true): DelegationBroadcastSubscriber, Reverb/Echo channels, Vue graph monitoring view, draft review/edit screen. Full operator experience.
- **Phase 4 — Hardening**: Load testing, threshold tuning, alert configuration, documentation. Remove feature flag gates after confidence period.

## MVP Out of Scope

- Human-as-delegatee execution (schema-ready, not implemented)
- Advanced rules DSL or configurable recovery rule definitions
- Marketplace or decentralised delegation
- Blockchain or on-chain reputation
- Zero-knowledge cryptographic proofs
- Game-theoretic auction mechanisms for delegatee selection
- Graph branching/merging (conditional paths based on task output)
- Cross-user delegation (graphs always owned by single user)
- Cost tracking integration with external billing APIs
- Graph templates or reusable graph definitions
- Graph versioning or diff history
- Automated graph generation from AI decomposition (future integration with interrogation plan_json is manual/API)

## Database Entities

delegation_graphs, delegation_tasks, delegation_task_edges, delegation_attempts, delegation_verification_results, delegatees, capabilities, delegatee_capabilities (pivot), delegatee_metrics, delegation_user_settings

## Services

DelegationGraphBuilder, ContractValidator, ContractEnforcer, DelegationCoordinator, RecoveryEvaluator, VerificationPipeline, DelegationBroadcastSubscriber, DelegateeMetricsRecomputer, DelegateeAssigner, DelegationReconciler

## Config (config/delegation.php)

max_concurrent_graphs_per_user: 3, max_tasks_per_graph: 25, default_max_parallel_tasks: 3, default_cancellation_policy: drain, min_trust_for_retry: 0.4, min_trust_for_redelegation: 0.6, escalation_criticality_threshold: high, max_recovery_attempts: 5, metrics_recomputation_interval_minutes: 15, metrics_event_throttle_seconds: 60, reconciliation_interval_minutes: 2

## Goals

- Introduce delegation graph model (DelegationGraph, DelegationTask, DelegationAttempt) as orchestration layer above existing AgentJob/AgentJobRun with no modifications to existing pipeline
- Build delegatee registry with runner type + config profiles, seeded capabilities reference table (code_generation, code_review, testing, linting, file_editing, refactoring, documentation), and trust-aware ranking via pre-aggregated sliding window metric snapshots
- Implement contract-first tasks with JSON contracts declaring capabilities, authority scope, budget/time constraints, reversibility, criticality, and verification — authority enforced at execution time by ContractEnforcer narrowing PathPolicy/CommandPolicy/EnvPolicy
- Add event-driven reactive coordination with RecoveryEvaluator using hardcoded decision logic and config-driven thresholds for failure recovery (retry, re-delegate, escalate, abort) with periodic DelegationReconciler as missed-event safety net
- Build verification pipeline with pluggable ordered steps (automated_check, ai_critic, human_approval) producing auditable DelegationVerificationResult records with verdict and evidence
- Implement API-first graph creation with explicit endpoints for create (raw DAG JSON + chain shorthand), edit, approve, start, cancel, and human-approval — all idempotent where applicable
- Deliver UI draft review/edit screen with inline per-field validation and summary panel, plus read-only graph monitoring with realtime updates via dual Reverb/Echo channels, gated by delegation.ui_enabled flag
- Add configurable per-graph max_parallel_tasks and per-user graph concurrency caps (global default with per-user override), with hard 25-task graph limit
- Implement observability: key delegation metrics, failure alerts (recovery exhausted, trust degradation, stale approvals), and DelegationReconciler safeguard for stuck tasks
- Roll out incrementally via three feature flags (delegation.enabled, delegation.ui_enabled) across four phases: backend foundation, execution engine, broadcast/UI, hardening


## Constraints

- Existing AgentJob/AgentJobRun pipeline remains untouched — delegation layers above as pure orchestration
- Local-first architecture — no external services, marketplaces, blockchain, or ZK proofs
- PathPolicy/CommandPolicy/EnvPolicy can only be narrowed (never widened) by task authority scopes via ContractEnforcer
- Existing core Agent tests must remain green — delegation is purely additive
- Event-driven coordination is stateless — DelegationReconciler runs every 2 minutes as safety net only, not as primary dispatch mechanism
- Human-as-delegatee schema-ready (type column) but execution deferred from MVP — only ai_runner delegatees
- Trust metrics use pre-aggregated snapshots with hybrid recomputation (60s event throttle + 15min scheduled) — no query-time aggregation on recovery hot path
- Capabilities validated against seeded reference table — freeform strings not accepted
- Hard limit of 25 tasks per graph enforced at creation, edit, and approval
- Default max_parallel_tasks=3 per graph, configurable 1-25; default max_concurrent_graphs_per_user=3 with per-user override
- Contract JSON validated on creation and edit — invalid contracts rejected before graph can be approved
- Graph cancellation is idempotent — kill mode: SIGTERM+10s+SIGKILL then immediate transition; drain mode: block dispatches, wait for active tasks, then transition via cancelling state
- Graphs soft-deleted — child records retained permanently, never cascade-deleted
- RecoveryEvaluator uses hardcoded logic with config thresholds — no generic rules engine or DSL
- Rollout gated by feature flags: delegation.enabled (API routes), delegation.ui_enabled (Vue UI)
- API mutation endpoints (create, edit, approve, start, cancel, human-approval) are idempotent where state allows and use existing ErrorEnvelope format for errors
- All state transitions follow explicit state machines — no ad-hoc status updates


## Acceptance Criteria

- Delegation graph with tasks and edges created via API using raw DAG JSON format with tasks[] and edges[] arrays
- Delegation graph created via linear-chain shorthand with chain[] array auto-generating sequential edges
- Draft graph editable via PUT endpoint — tasks, edges, contracts, and delegatee assignments modifiable while status=draft
- Graph approval validates: contract JSON schema, capabilities against reference table, DAG integrity (no cycles, no orphans), task count <=25, delegatee capability match — returns 422 with all errors on failure
- Graph start checks per-user concurrency cap and dispatches root tasks — returns 409 if cap exceeded
- Graph cancel is idempotent: kill mode terminates active runs via SIGTERM/SIGKILL, drain mode blocks new dispatches and waits via cancelling state
- All graph/task/attempt state transitions follow the defined state machines — no transitions outside allowed paths
- Tasks dispatched respecting dependency edges, per-graph max_parallel_tasks, and per-user concurrency limits
- Each task spawns AgentJobRun(s) with policies narrowed by ContractEnforcer to intersection of task authority and global policy
- RecoveryEvaluator reads contract boundaries and trust metric snapshots to decide retry/re-delegate/escalate/abort without manual intervention
- Re-delegation selects alternative delegatee via capability matching and trust-aware ranking from metric snapshots
- Every completed task has at least one verification step producing DelegationVerificationResult with verdict and evidence_json
- Human approval verification steps block task completion, show as UI action items, allow operator approve/reject via dedicated endpoint
- Delegatee metrics recomputed within 60s of attempt completion (throttled per delegatee) with 15min scheduled fallback
- DelegationReconciler runs every 2 minutes and catches stuck tasks whose AgentJobRun reached terminal but task state was not advanced
- Draft UI shows both inline per-field errors and summary validation panel — approval blocked until resolved
- Monitoring UI displays graph with realtime node states via Reverb/Echo showing ownership, trust inputs, verification results, failure cause
- Dual broadcast channels operational: per-user summary for graph-level state changes, per-graph detail for task-level events
- Observability metrics exposed: graphs by status, tasks dispatched/failed, recovery actions by type, verification pass rate, pending approvals count
- Failure alerts fire on: graph failed, recovery exhausted, delegatee trust below threshold, human approval pending >30min
- Feature flags gate rollout: delegation.enabled gates API routes, delegation.ui_enabled gates Vue UI components
- All delegation actions recorded in AgentAuditLog with target_type values for graph, task, attempt, verification, delegatee
- Soft-deleted graphs hidden from listings but children permanently accessible for audit and metrics
- Existing core Agent tests green with no regressions
- New tests cover: graph lifecycle state machine, task state machine, attempt state machine, dependency dispatch, parallelism caps, concurrency caps, recovery evaluator decision paths, verification pipeline sequencing, contract validation, authority narrowing, metrics recomputation and throttling, reconciler stuck-task detection, broadcast curation, chain shorthand normalisation, cancellation kill and drain modes, API idempotency

