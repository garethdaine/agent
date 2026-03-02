# Requirements Discovery Brief — AgentKeeper-Inspired Cognitive Continuity

Date: 2026-03-01
Status: Draft (Discovery)
Owner: Agent Platform
Scope: Agent memory continuity across runner/provider switches, retries, and delegation handoffs

---

## Executive Summary

Agent already has a strong four-layer memory foundation (core blocks, working memory, long-term retrieval, consolidation). What it does not yet provide as a first-class primitive is a deterministic "critical fact continuity" lane that guarantees high-priority facts survive runner/provider transitions under strict context budgets.

AgentKeeper is useful as a pattern reference for that lane, not as a dependency. The recommended integration is to add a native Cognitive Continuity layer inside the existing memory subsystem and wire it into current run lifecycle hooks (`RunEventWriter`, `MemoryFormationJob`, `MemoryContextBuilder`, `ExecuteAgentRunJob`) plus delegation spawn context.

---

## What AgentKeeper Solves (and What Is Reusable)

Reviewed sources:
- [AgentKeeper README](https://raw.githubusercontent.com/Thinklanceai/agentkeeper/main/README.md)
- [AgentKeeper engine (`src/cre/engine.py`)](https://raw.githubusercontent.com/Thinklanceai/agentkeeper/main/src/cre/engine.py)
- [AgentKeeper storage (`src/storage/sqlite_store.py`)](https://raw.githubusercontent.com/Thinklanceai/agentkeeper/main/src/storage/sqlite_store.py)
- [AgentKeeper API surface (`agentkeeper.py`)](https://raw.githubusercontent.com/Thinklanceai/agentkeeper/main/agentkeeper.py)

Reusable ideas:
1. Provider-agnostic memory facts persisted outside model sessions.
2. Explicit critical flag on facts.
3. Token-budget-aware reconstruction that prioritizes critical facts first.
4. Continuity stats (critical recovery vs budget).

Non-reusable parts (for this repo):
1. Python runtime and thin library model.
2. Single SQLite table persistence strategy.
3. Flat single-agent memory with no scoping/classification/audit model.
4. No delegation-aware memory boundaries.

Decision: do **not** vendor AgentKeeper; implement equivalent primitives natively in Laravel memory layer.

---

## Current Agent Memory/Delegation Fit

### Existing strengths
- Pre-run injection already exists via `MemoryContextBuilder` and run path override in `ExecuteAgentRunJob`.
- Working memory capture already exists via `RunEventWriter` -> `MemoryWorkingBufferJob`.
- Post-run long-term formation already exists via `MemoryFormationJob` -> `MemoryFormationPipeline`.
- Multi-provider capability model and failover already exist (`MemoryCapabilityResolver`, `MemoryAdapterFactory`).
- Delegation attempts already carry graph/task context in run metadata (`AttemptSpawner`).

### Gap relevant to AgentKeeper-style continuity
- No dedicated "continuity fact" store with first-class `critical` semantics.
- No deterministic reconstruction policy specifically optimized for critical fact retention.
- `MemoryContextBuilder` budgeting is generic and does not explicitly reserve space for critical continuity facts.
- No continuity KPIs (critical recovery rate, dropped critical facts due to budget, etc.).
- Delegation-scoped memory continuity is not explicitly modeled.

---

## Proposed Feature

## Feature Name
Cognitive Continuity Layer (CCL)

## Goal
Guarantee high-priority factual continuity across:
1. Runner/provider switches (`claude` <-> `codex` <-> `custom`),
2. retries/restarts,
3. delegation handoffs.

## Non-goals (v1)
- Full multi-agent shared memory marketplace.
- New external memory service.
- Replacing existing hybrid retrieval stack (this is additive).

---

## Architecture Placement

### Placement in current stack
- Layer 1/3 bridge: persistent atomic facts (new) sourced from core/working/long-term signals.
- Injection stage: extend `MemoryContextBuilder` with continuity-first selection.
- Formation stage: extend `MemoryFormationPipeline` to upsert continuity facts.
- Delegation stage: use `run.metadata_json.task_id/graph_id` context for scoped reconstruction.

### New components
1. `App\Models\MemoryContinuityFact`
2. `App\Support\Memory\ContinuityFactManager`
3. `App\Support\Memory\CognitiveReconstructionEngine`
4. `App\Http\Controllers\Api\V1\Memory\MemoryContinuityController`

### Existing components to extend
1. `app/Support/Memory/MemoryContextBuilder.php`
2. `app/Support/Memory/MemoryFormationPipeline.php`
3. `app/Http/Controllers/Api/V1/Memory/MemoryDiagnosticsController.php`
4. `app/Http/Requests/Memory/UpdateMemorySettingsRequest.php`
5. `app/Providers/MemoryServiceProvider.php`
6. `routes/api.php`

---

## Data Model Changes

### New table: `memory_continuity_facts`

Suggested columns:
- `id` bigint PK
- `user_id` bigint FK
- `job_id` bigint nullable FK
- `run_id` bigint nullable FK
- `delegation_graph_id` bigint nullable FK
- `delegation_task_id` bigint nullable FK
- `content` text
- `content_hash` varchar(64)
- `is_critical` boolean default false
- `priority_score` double precision default 0.5
- `confidence_score` double precision default 0.5
- `classification` varchar(20) default `internal`
- `source_type` varchar(30) (`manual`, `extracted`, `promoted`)
- `token_count` integer default 0
- `times_selected` integer default 0
- `last_selected_at` timestamptz nullable
- `forgotten_at` timestamptz nullable
- `metadata_json` jsonb nullable
- `created_at`, `updated_at`

Indexes:
- unique composite on `(user_id, content_hash, COALESCE(job_id,0), COALESCE(delegation_graph_id,0), COALESCE(delegation_task_id,0))`
- `(user_id, is_critical, forgotten_at)`
- `(user_id, classification, forgotten_at)`

Rationale:
- Keeps deterministic fact layer independent from embedding availability.
- Supports no-API and degraded modes.
- Supports delegation scoping without changing existing long-term tables.

---

## API Additions

Namespace: `/agent/api/v1/memory` (inside `MemoryEnabled` middleware)

Endpoints:
1. `GET /continuity/facts`
- List facts with filters (`critical`, `job_id`, `graph_id`, `task_id`, `limit`).

2. `POST /continuity/remember`
- Add or upsert fact (`content`, `critical`, `classification`, optional scope ids).

3. `POST /continuity/forget`
- Soft-forget by `fact_id` (sets `forgotten_at`).

4. `POST /continuity/reconstruct` (diagnostic)
- Input: `query`, `token_budget`, optional scope ids.
- Output: selected facts + stats (`critical_selected`, `critical_total`, `tokens_used`).

5. `GET /continuity/stats`
- Returns continuity KPIs and budget pressure metrics.

---

## Reconstruction Algorithm (CCL)

Inputs:
- user id
- optional scope (`job_id`, `graph_id`, `task_id`)
- query text
- token budget
- classification allowlist

Selection policy:
1. Candidate set from active (not forgotten) facts within scope + global fallback.
2. Score facts by weighted formula:
- `critical boost`
- `query overlap score`
- `recency score`
- `selection feedback` (facts repeatedly useful trend upward)
3. Pack by budget:
- critical facts first
- then score-per-token efficiency
- if critical cannot fit, evict lowest non-critical selected fact(s)
4. Return selected facts and reconstruction stats.

Budget strategy inside `MemoryContextBuilder`:
- reserve `continuity_budget_percent` (default 35%) for continuity facts,
- use remainder for current hybrid retriever output,
- dedupe by `content_hash` before rendering markdown.

---

## Integration Flow

## Ingestion
1. Manual: `/memory/continuity/remember` endpoint.
2. Automatic: `MemoryFormationPipeline` promotes high-confidence extracted facts to continuity table.
3. Optional promotion from core blocks (e.g., `task_state` milestones) during consolidation.

## Injection
1. `ExecuteAgentRunJob` invokes `MemoryContextBuilder` (existing hook).
2. `MemoryContextBuilder` invokes `CognitiveReconstructionEngine` first.
3. Final wrapper sections become:
- `## Agent Identity`
- `## User Context`
- `## Continuity Facts` (new)
- `## Relevant Memories` (existing hybrid retrieval)
- `---` original task

## Delegation-specific behavior
- When `metadata_json.source === 'delegation'`, include task/graph scoped facts first.
- Fall back to user-global continuity facts if task/graph scoped set is sparse.

---

## Config Additions (`config/memory.php`)

Add `continuity` block:
- `enabled` (bool, default true when memory enabled)
- `budget_percent` (default 35)
- `critical_min_reserve_tokens` (default 400)
- `max_selected_facts` (default 25)
- `min_confidence_for_auto_promotion` (default 0.75)
- `promotion_max_per_run` (default 20)

Add corresponding allowed settings keys in `UpdateMemorySettingsRequest`:
- `continuity_budget_percent`
- `continuity_critical_min_reserve_tokens`
- `continuity_max_selected_facts`
- `continuity_min_confidence_for_auto_promotion`

---

## Rollout Plan

Phase 1: Data + service foundations
- Add migration/model/factory for continuity facts.
- Add `ContinuityFactManager` + `CognitiveReconstructionEngine`.
- Add unit tests for packing/prioritization logic.

Phase 2: Context injection integration
- Extend `MemoryContextBuilder` with continuity section and budget split.
- Add feature tests for wrapper ordering and budget behavior.

Phase 3: Formation + delegation integration
- Extend `MemoryFormationPipeline` to promote facts.
- Use delegation run metadata for scoped retrieval.
- Add integration tests for delegation continuity handoff.

Phase 4: API + diagnostics
- Add continuity endpoints and request validators.
- Extend `/memory/stats` and `memory:stats` with continuity KPIs.

Phase 5: Hardening
- Add throttles, audit logs, and dry-run diagnostics.
- Measure critical recovery rate and tune scoring weights.

---

## Testing Strategy

Unit tests:
1. Critical-first packing under tight budgets.
2. Non-critical eviction to preserve critical facts.
3. Scope precedence (task -> graph -> user global).
4. Dedup behavior by content hash.

Feature tests:
1. Continuity API CRUD + auth + rate limits.
2. Context wrapper includes continuity section and budget split.
3. Delegation attempt run injects scoped continuity facts.
4. Formation pipeline auto-promotion obeys confidence threshold.

Regression tests:
1. Existing memory endpoints unchanged.
2. Existing run lifecycle finalization unchanged.
3. Memory-disabled mode still returns expected 503 for operational routes.

---

## Risks and Mitigations

1. Fact bloat/noise
- Mitigation: promotion thresholds, max facts/run, forgetting policy, dedup by hash.

2. Incorrect critical tagging
- Mitigation: default non-critical for auto facts, explicit manual override API, audit trail.

3. Context inflation hurting run quality
- Mitigation: strict continuity budget split, hard token ceiling, section truncation with stats.

4. Delegation scope leakage
- Mitigation: strict scope filters + classification checks in continuity queries.

5. Duplicate memory semantics with existing embeddings
- Mitigation: treat continuity as deterministic fact lane; embeddings remain semantic lane.

---

## Success Criteria

1. Critical fact recovery >= 90% in internal runner-switch test suite at 2,000-token continuity budget.
2. Context build latency increase <= 30ms p95 relative to current `MemoryContextBuilder`.
3. No increase in run finalization failures attributable to memory path.
4. Delegation retries/handoffs preserve scoped critical facts in reconstructed context.
5. Diagnostics expose continuity KPIs (`critical_total`, `critical_selected`, `critical_recovery_rate`, `tokens_used`).

---

## Open Questions

1. Should continuity facts be editable from UI in v1, or API-only first?
2. Should auto-promotion be on by default in no-API mode, or require API mode only?
3. Should continuity stats be persisted per-run for longitudinal tracking, or computed on demand initially?
4. Should we gate continuity with a separate feature flag (`memory.continuity_enabled`) for safer rollout?

---

## Recommendation

Implement this as an additive native memory sub-layer, not an external dependency.

AgentKeeper’s core insight is already aligned with Agent’s architecture; the highest-leverage move is to formalize a deterministic continuity fact lane and integrate it into existing injection/formation/delegation seams.
