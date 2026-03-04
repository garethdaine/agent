# Agent Platform — Project Status

> Last updated: 2026-03-01

## Canonical Phase 1 Status (Authoritative)

We help companies deploy AI agents safely and keep them reliable in production.

Canonical source of truth: `docs/system-overview.md`.

This system targets a local-first Laravel runtime with provider-agnostic telemetry contract semantics.

- Workflow key regex: `^[a-z0-9._-]+[.]v[1-9][0-9]*$`.
- WeightedReliability = (sum(run_weight) / count(scored_runs)) * 100
- Reliability gates use stricter-window enforcement across rolling `14-day` and rolling `50-run`.
- Active build freshness is surfaced through `active_build_age_seconds`.
- Projection read-scope is active-build only; projection tables are internal infrastructure data and not a direct external query surface.
- Projection boundary enforcement uses the dedicated `agent_projection` schema with explicit least-privilege grants and reporting-role query denial.

Known risk boundaries:
- event-id stability
- terminal catalog drift
- projection query restrictions

## Completed Features

All features below have been implemented, tested, and marked **Completed** in the discovery session tracker.

### Messenger Control Plane (Session 9 — 2026-02-21)

Multi-provider messaging gateway with chat-driven agent control.

- **Providers:** Slack, Telegram, Discord, WhatsApp — all wired with webhook handlers and signature verification.
- **Modes:** Public webhook mode + local-first gateway workers (ReactPHP/Amp with PHP 8.1+ fibers).
- **Chat flows:** 7 user flows (create job, observe runs, run now, stop, steer, list, update).
- **Reliability:** Circuit breaker (5 failures/60s cooldown), per-provider rate limits, replay deduplication, dead-letter queue.
- **Data:** `chat_sessions`, `chat_messages`, `chat_actions`, `connector_accounts`, `messenger_identity_links`, `messenger_event_deduplication`, `messenger_dead_letters`.
- **Discovery docs:** `messenger-control-plane.md` through `-v6.md`, `messenger-control-plane-gaps.md`/`-v2.md`, `local-first-messaging-gap-brief.md`, `messenger-control-plane-local-first-completion-provider-parity.md`.

### Consolidated Implementation / Delegation Engine v1 (Session 2 — 2026-02-25)

Full delegation runtime with DAG execution, verification pipeline, and trust scoring.

- **Models:** DelegateeProfile, DelegationGraph, DelegationTask, DelegationAttempt, DelegationVerificationResult, DelegationEvent + supporting pivots/metrics.
- **Services:** GraphBuilder, GraphExecutor, Reconciler, ContractValidator, ContractEnforcer, TrustScoreCalculator, DelegateeAssigner, AttemptSpawner.
- **Verification:** 3-step pipeline — AutomatedCheckStep, AiCriticStep, HumanApprovalStep.
- **Limits:** Max 25 tasks/graph, 15 parallel ceiling, 15-min graceful cancellation timeout.
- **Recovery:** 2 retries same delegatee → 1 re-delegate → escalate.
- **Feature flags:** `delegation.enabled`, `delegation.ui_enabled`.
- **Discovery docs:** `consolidated-implementation-for-agent-platform-v1.md`, `consolidated-implementation-brief.md`, `intelligent-delegation-for-agent.md`, `reconstructed-intelligent-delegation-for-agent.md`/`-v2.md`/`-v3.md`.

### Adversarial Reviewer (Session 4 — 2026-02-26)

Quality gate for summary and plan artifacts in the interrogation workflow.

- **Service:** `AdversarialReviewerService` using Claude CLI subprocess with `--json-schema`.
- **Verdicts:** pass, revise, needs_clarification (summary only).
- **Retry caps:** 3 for summary, 2 for plan. Confidence threshold 0.6.
- **Shadow mode:** `review_warn_only` flag for safe rollout.
- **Feature flag:** `agent.interrogation.adversarial_review_enabled`.
- **Discovery docs:** `adversarial-reviewer.md`, `adversarial-reviewer-discovery-requirements-brief.md`.

### Natural Language Scheduling (Session 5 — 2026-02-27)

Hybrid parser for human-readable schedule expressions with LLM fallback.

- **Parser:** Rule-based first (`RuleBasedScheduleParser` — pattern matching), LLM fallback at <75% confidence.
- **Features:** Active-hours scheduling, ISO-8601 day indexing (1=Mon..7=Sun), async LLM with `parse_attempt_id` tracking.
- **Limits:** 200-char input max, 30s LLM timeout, 60s idempotency window, 10/min + 60/hour rate limits on LLM path.
- **Data:** `nl_parse_attempts` table with 90-day retention.
- **Discovery docs:** `natural-language-scheduling.md` through `-v4.md`.

### Workflow Orchestration Instruction (Session 5 — 2026-02-27)

Compliance layer enforcing planning, verification, and lessons across agent jobs and interrogation builds.

- **Namespace:** `app/Support/Compliance/`.
- **Services:** ComplexityClassifier, OrchestrationPolicyService, VerificationEvidenceEvaluator, LessonsManager, ComplianceFlagResolver.
- **Gates:** Plan gate (non-trivial tasks), verification gate (evidence by task category), elegance gate (deferred), lessons injection.
- **Heuristics:** >3 files, >50 LOC, >2 directories → non-trivial.
- **Lessons system:** File-based (`tasks/lessons.md`), 2000-token budget, recency-weighted, 4 trigger signals.
- **Rollout:** Advisory → Warning → Enforced with configurable thresholds.
- **Feature flags:** `compliance.enabled`, `compliance.enforcement_mode`, `compliance.plan_gate_enabled`, `compliance.verification_gate_enabled`, `compliance.elegance_gate_enabled`, `compliance.lessons_enabled`.
- **Discovery docs:** `workflow-orchestration-instruction.md`, `-v2.md`.

### Figma Make UI Parity (Session 6 — 2026-02-27)

Design system alignment with Figma reference across all app surfaces.

- **Theme:** Class-based dark mode, system preference detection, persistent user override (light/dark/system).
- **Design tokens:** RGB-channel CSS variables with Tailwind alpha support (`rgb(var(--token) / <alpha-value>)`).
- **Components:** 20+ base UI components (Button, Card, Input, Select, Badge, Table, Skeleton, etc.).
- **Typography:** DM Sans + JetBrains Mono, 1440px content max-width.
- **Icons:** Lucide Vue Next (replaced Heroicons).
- **Discovery docs:** `figma-make-ui-parity.md`, `-v2.md`, `figma-make-full-ui-parity-brief.md`.

### STAR Reasoning & Delegation Framework Integration (Session 10 — 2026-02-27)

Structured reasoning and trust calibration for delegation attempts.

- **STAR Preamble Generator:** Situation/Task/Action/Result context injection.
- **A/B testing:** Configurable percentage split for structured vs standard prompts.
- **Trust calibration:** TrustScoreCalculator with failure mode classification and targeted retry.
- **Data:** Extended `agent_jobs`, `agent_job_runs`, `agent_run_events`, `delegatee_profiles` with STAR columns.
- **Discovery docs:** `star-reasoning-delegation-framework-integration.md`.

### Messenger Control Plane — Local-First & Provider Parity (Session 11 — 2026-02-28)

Extended messenger gateway for local deployment and full provider feature coverage.

- **Gateway:** ReactPHP/Amp supervisor with reconnection strategy (backoff + jitter).
- **Provider parity:** All 4 providers with threading, signature verification, and attachment handling (10MB max, MIME filtering).
- **Discovery docs:** `messenger-control-plane-local-first-completion-provider-parity.md`.

### Agent Gaps (Session 13 — 2026-02-28)

Systematic resolution of 11 production gaps across messenger, delegation, and compliance subsystems.

- **Scope:** Handler consolidation, policy enforcement, compliance metrics, delegation reconciliation.
- **Discovery docs:** `agent-gaps.md`, `-v2.md`, `messenger-control-plane-gaps.md`, `-v2.md`.

### Agent Memory (Session 14 — 2026-03-01)

Four-layer memory system: Core Memory, Working Memory, Long-term Memory (BM25 + semantic + graph).

- **Layers:** Identity blocks (read-only) + operational blocks (agent-editable), Redis-backed working memory buffer, hybrid retrieval (BM25 keyword + pgvector semantic + Neo4j graph).
- **Formation:** LLM-powered memory formation pipeline with retry logic and consolidation service.
- **Services:** CoreMemoryManager, MemoryFormationPipeline, HybridRetriever, Neo4jGraphStore, ConsolidationService, ForgettingService, WorkingMemoryBuffer, MemoryContextBuilder.
- **API:** 5 controllers — CoreBlocks, Settings, WorkingMemory, Retrieval, Diagnostics.
- **Data:** 7 tables — `memory_settings`, `memory_core_blocks`, `memory_embeddings`, `memory_conversation_logs`, `memory_consolidation_log`, `memory_formation_failures`, `memory_provider_usage`.
- **Infrastructure:** Neo4j 5-community (Docker Compose), pgvector for 1536d embeddings.
- **Feature flags:** `memory.enabled`, `memory.api_enabled`.
- **Operating modes:** No-API (Core + Working + BM25) and API (+ embeddings + extraction + Neo4j graph).
- **Discovery docs:** `agent-memory.md`, `-v2.md`, `-v3.md`, `agent-memory-delegation.md`, `-v2.md`.

### Feature Flags Management (2026-02-26)

Centralized DB-backed feature flag system with config/env fallbacks.

- **Service:** `FeatureFlagManager` with 11 managed keys across delegation, memory, compliance, and reviewer.
- **Storage:** `agent_feature_settings` table with `updated_by_user_id` audit.
- **UI:** Tools settings page at `/tools/features/settings`.
- **API:** Authenticated read/update endpoints at `/agent/api/v1/features/settings`.

### PHPUnit Suite Stabilization (2026-02-28)

Full test suite passing: 1651 tests, 7 skipped, 0 failures.

- Resolved 33 failing tests across messenger webhooks, delegation naming, DB safety invariants.
- Hardened false-positive detection for rate-limit, approval, and blocker signals in code snippets.

---

## In Progress / Pending

### Agent Org Layer — AI Workforce (Setup)

First-class organizational operating layer for named AI employees, reporting lines, councils, and recurring rituals.

- **Status:** Session created, in Setup phase. No implementation yet.
- **Scope:** 8 new tables, 14 API endpoints, ritual templates with cron scheduling, council multi-perspective synthesis, cost ledger with governance thresholds.
- **Dependencies:** Delegation Engine v1 (done), Adversarial Reviewer (done), Memory system (done), Messenger Control Plane (done).
- **Delivery:** 5 phases — Foundation → Ritual Runtime → Council/QA Gates → Governance → Hardening.
- **Discovery doc:** `agent-org-layer-requirements-brief.md`.

### Discovery Wizard Full Session Validation

End-to-end browser walkthrough of the discovery wizard for Natural Language Scheduling.

- **Status:** Unchecked items in todo.md. Requires hands-on browser testing.
- **Scope:** Drive session 9 through all stages, capture UI parity findings, patch remaining mismatches.

---

## Reference Architecture

### Feature Flag Registry

| Flag | Subsystem | Description |
|------|-----------|-------------|
| `delegation.enabled` | Delegation | API & engine master toggle |
| `delegation.ui_enabled` | Delegation | UI navigation |
| `agent.interrogation.adversarial_review_enabled` | Reviewer | Adversarial review passes |
| `memory.enabled` | Memory | Core Memory + Working Memory + BM25 |
| `memory.api_enabled` | Memory | LLM extraction + embeddings + Neo4j |
| `compliance.enabled` | Compliance | Master toggle |
| `compliance.enforcement_mode` | Compliance | advisory / strict |
| `compliance.plan_gate_enabled` | Compliance | Plan requirement gate |
| `compliance.verification_gate_enabled` | Compliance | Verification evidence gate |
| `compliance.elegance_gate_enabled` | Compliance | Elegance evaluation gate |
| `compliance.lessons_enabled` | Compliance | Lessons injection system |

### Infrastructure Requirements

| Service | Purpose | Config |
|---------|---------|--------|
| PostgreSQL | Primary database | `DB_CONNECTION=pgsql` |
| Redis | Cache, queues, working memory | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` |
| Neo4j 5-community | Knowledge graph (memory) | Docker Compose, bolt port 7687 |
| pgvector | Semantic embeddings (memory) | PostgreSQL extension, 1536d fixed |
| Laravel Reverb | WebSocket broadcasting | `BROADCAST_CONNECTION=reverb` |
| Laravel Horizon | Queue supervision | `interrogation` + `memory-working` + `memory-formation` queues |

### Discovery Doc Index (Latest Versions)

| Feature | Latest Doc | Lines |
|---------|-----------|-------|
| Agent Memory | `agent-memory-v3.md` | 635 |
| Agent Gaps | `agent-gaps-v2.md` | ~300 |
| Agent MCP | `agent-mcp-v5.md` | ~500 |
| Agent Org Layer | `agent-org-layer-requirements-brief.md` | 359 |
| Adversarial Reviewer | `adversarial-reviewer.md` | ~250 |
| Consolidated Implementation | `consolidated-implementation-brief.md` | 932 |
| Figma UI Parity | `figma-make-ui-parity-v2.md` | ~350 |
| Messenger Control Plane | `messenger-control-plane-v6.md` | 636 |
| Messenger Gaps | `messenger-control-plane-gaps-v2.md` | ~300 |
| NL Scheduling | `natural-language-scheduling-v4.md` | 280 |
| STAR Reasoning | `star-reasoning-delegation-framework-integration.md` | ~280 |
| Workflow Orchestration | `workflow-orchestration-instruction-v2.md` | ~370 |
