# Phase 4 — Performance, Queue Compliance, and Cache Audit

**Date**: 2026-03-08
**Session**: 6 | Task Sequence: 5

---

## 1. N+1 Query Detection

### Unbounded `::all()` Calls

| File | Line | Context | Risk |
|---|---|---|---|
| `app/Http/Controllers/Messenger/MessengerHealthController.php` | 29 | `ConnectorAccount::all()` in `index()` | Medium — loads all connector accounts without pagination or column selection |
| `app/Http/Controllers/Messenger/MessengerHealthController.php` | 161 | `ConnectorAccount::all()` in `dashboard()` | Medium — same unbounded load |

**Finding (P2)**: Two `::all()` calls on `ConnectorAccount` model. Currently low-risk if connector count is small, but violates "Never `SELECT *`" database rule. Should use `->select([...])` and consider pagination if growth expected.

### Controller `->get()` Without Eager Loading

44 `->get()` calls found across controllers. 26 `->with()` eager loading calls found (13 files). Notable patterns:

| Controller | `->get()` Count | `->with()` Present | Assessment |
|---|---|---|---|
| `InterrogationSessionController` | 12 | Yes (5) | Partial — some queries lack eager loading |
| `OfficeStateController` | 7 | Yes (5) | Adequate |
| `AgentRunController` | 3 | Yes (1) | Potential N+1 on 2 queries |
| `RepoAnalysisSessionController` | 4 | No | **Risk** — may cause N+1 if relationships traversed |
| `DelegationGraphController` | 1 | No | Check if relationships used in view |

**Finding (P2)**: No confirmed N+1 queries in `foreach` loops within controllers (controller foreach loops operate on arrays/validation, not query results inside loops). However, several `->get()` calls lack `->with()` eager loading — relies on Laravel 12.8 automatic eager loading if enabled.

**Recommendation**: Verify `config/database.php` or `AppServiceProvider` for `Model::preventLazyLoading()` or automatic eager loading configuration. If not enabled, the 18+ unmatched `->get()` calls are potential N+1 sources when views traverse relationships.

---

## 2. Missing FK Indexes

- **138 `foreignId()` / `->foreign()` declarations** found across 81 migration files
- **12 explicit `->index()` calls** found across 9 migration files

**Analysis**: Laravel's `foreignId()->constrained()` automatically creates an index on the FK column. The low explicit `->index()` count is not necessarily a gap — it depends on whether `constrained()` is used. Laravel's `foreignId()` method creates both the column and (when paired with `constrained()`) the FK constraint with an implicit index.

**Finding (P2)**: Manual audit of high-traffic FK columns recommended. Cross-reference with query patterns in `AgentRunController`, `InterrogationSessionController`, and `DelegationGraphController` to confirm indexes exist on frequently filtered columns beyond FKs.

---

## 3. Horizon Timeout Compliance (All 15 Supervisors)

### Engineering Rules v2.0 Requirements
- `supervisor-default`: Standard queues, auto-balance, up to 10 processes
- `supervisor-long-running`: AI inference queues, **600s timeout, 256MB memory**, up to 5 processes

### Supervisor Compliance Matrix

| # | Supervisor | Queue(s) | Timeout | Memory | Max Procs (default) | Max Procs (prod) | Compliant? | Notes |
|---|---|---|---|---|---|---|---|---|
| 1 | `supervisor-1` | agent | **86,500s** | 128MB | 2 | 10 | **P1** | Naming: should be `supervisor-long-running`. Timeout 144× over 600s rule. Memory 128MB vs 256MB required. |
| 2 | `supervisor-interrogation` | interrogation | **7,800s** | 128MB | 2 | env-driven | **P1** | Long-running AI work, timeout 13× over 600s. Memory 128MB vs 256MB. |
| 3 | `supervisor-messenger` | messenger-high, messenger-default | 120s | 128MB | 3 | env-driven | ✓ | Standard queue, appropriate timeout. |
| 4 | `supervisor-delegation` | delegation | **900s** | 128MB | 2 | env-driven | ⚠️ | AI inference → 600s+ acceptable but memory 128MB vs 256MB. |
| 5 | `supervisor-memory-working` | memory-working | 5s | 128MB | 3 | env-driven | ✓ | Short-lived standard queue. |
| 6 | `supervisor-memory-formation` | memory-formation | 300s | 128MB | 3 | env-driven | ✓ | Standard queue, 5 retries with backoff. |
| 7 | `supervisor-org-rituals` | org-rituals | 600s | 128MB | 2 | env-driven | ⚠️ | At 600s boundary. Memory 128MB vs 256MB if considered long-running. |
| 8 | `supervisor-code-analysis` | code-analysis | **dynamic** (default ~3,780s) | 128MB | 2 | env-driven | **P1** | Computed from env vars, defaults to ~3,780s. Memory 128MB vs 256MB. |
| 9 | `supervisor-subagent` | subagent | **3,600s** | 128MB | 2 | env-driven | **P1** | AI inference queue, 6× over 600s. Memory 128MB vs 256MB. |
| 10 | `supervisor-skill-validation` | skill-validation | 120s | 128MB | 2 | env-driven | ✓ | Standard queue. |
| 11 | `supervisor-tunnel` | tunnel | **0 (unlimited)** | 128MB | 1 | 1 | **P1** | Unlimited timeout with `balance: 'false'` (string, not boolean — potential bug). |
| 12 | `supervisor-connector-credentials` | connector-credentials | 60s | 128MB | 2 | env-driven | ✓ | Standard queue. |
| 13 | `supervisor-connector-webhooks` | connector-webhooks | 30s | 128MB | 2 | env-driven | ✓ | Standard queue. |
| 14 | `supervisor-connector-approvals` | connector-approvals | 30s | 128MB | 1 | env-driven | ✓ | Standard queue. |
| 15 | `supervisor-default` | default | 120s | 128MB | 1 | env-driven | ✓ | Standard queue. Prod maxProcesses only 1–2, rules allow up to 10. |

### Key Deviations

#### P1: Naming Deviation
- **Rule requires**: `supervisor-long-running` for AI inference queues
- **Actual**: `supervisor-1` handles the `agent` queue (the primary long-running AI queue)
- **Impact**: Inconsistent with engineering rules naming convention; makes configuration auditing harder

#### P1: Memory Limit Deviation
- **Rule requires**: 256MB for long-running supervisors
- **Actual**: All 15 supervisors use 128MB
- **Affected long-running supervisors**: `supervisor-1` (86,500s), `supervisor-interrogation` (7,800s), `supervisor-delegation` (900s), `supervisor-code-analysis` (~3,780s), `supervisor-subagent` (3,600s), `supervisor-org-rituals` (600s)
- **Impact**: Memory-intensive AI operations may OOM with 128MB limit

#### P1: Timeout Outliers
- `supervisor-1`: 86,500s (~24 hours) — far exceeds 600s rule for long-running
- `supervisor-interrogation`: 7,800s (~2.17 hours)
- `supervisor-code-analysis`: ~3,780s (~1.05 hours, dynamic)
- `supervisor-subagent`: 3,600s (1 hour)
- `supervisor-tunnel`: 0 (unlimited) — no timeout at all

#### Potential Bug
- `supervisor-tunnel` has `'balance' => 'false'` (string) instead of `'balance' => false` (boolean). This may cause unexpected behaviour as the string `'false'` is truthy in PHP.

---

## 4. Cache Strategy Audit

### Cache Usage Summary
- **71 `Cache::` calls** found across the codebase
- **No `rememberForever()` calls** found
- **2 `remember()` calls** found:
  - `app/Http/Controllers/Api/V1/Memory/MemoryModelsController.php:72` — with TTL constant
  - `app/Support/Delegation/TrustScoreCalculator.php:17` — 300s TTL
- **Primary usage pattern**: `Cache::put()` / `Cache::get()` / `Cache::forget()` / `Cache::has()`

### Redis DB Separation
- DB 0: Default (queues, Horizon data) ✓
- DB 1: Cache ✓
- DB 2: Memory ✓

**Verified**: Redis database separation is correctly configured in `config/database.php`.

### Cache Usage by Domain

| Domain | Files | Pattern | TTL Set? |
|---|---|---|---|
| SessionProcessManager | 1 | Process/session tracking, live fragments, turn buffers | Yes (TTL_SECONDS constant) |
| MetricsCollector (Messenger) | 1 | Counter increments, latency tracking | Yes (CACHE_TTL constant) |
| CircuitBreaker | 1 | Circuit state persistence | Yes (CACHE_TTL_SECONDS) |
| LicenseService | 1 | License status caching | Yes (variable TTL) |
| Messenger Adapters | 3 | Webhook cache, interaction context, bot messages | Yes (24h, constants) |
| RunEventWriter | 1 | Throttling event broadcasts | Yes (2–4s TTL) |
| AccountLinkTokenService | 1 | Token storage with expiry | Yes |
| ReplayProtection | 1 | Idempotency tracking | Yes |
| OAuthFlowManager | 1 | OAuth state storage | Yes |
| TrustScoreCalculator | 1 | Score caching | Yes (300s) |
| RunnerModelsController | 1 | Model list caching | Yes (10min/2min) |
| MemoryModelsController | 1 | Model list caching | Yes (CACHE_TTL_SECONDS) |
| DocsRuntimeBootstrapService | 1 | Lock-based initialization | Yes (30s lock) |
| DocumentationTelemetrySubscriber | 1 | Window-based dedup | Yes |
| AgentBenchmarkSloCommand | 1 | Benchmark fingerprint tracking | Yes |

### Findings

**P1: No Documented Cache Strategy**
There is no centralized cache strategy document. Cache usage is organic and spread across 15+ files. While individual TTLs are set correctly, there is no:
- Cache key naming convention documented
- Cache invalidation strategy
- Cache warming strategy
- Cache sizing/memory budget

**P2: Cacheable Opportunities Not Exploited**
- `ConnectorAccount::all()` (called 2×) — candidate for `Cache::remember()`
- Route list / permission checks — no evidence of caching
- Team/user lookups in hot paths — not cached
- Configuration values fetched from DB — not cached

---

## 5. Tailwind Version Compliance

**Finding (P1): Tailwind v3 → v4 Migration Required**

| Aspect | Current | Required (Rules v2.0) |
|---|---|---|
| Version | `^3.4.0` (package.json) | v4 |
| Config | `tailwind.config.js` (JS-based) | CSS-first `@theme {}` |
| Build speed | Baseline | 5× faster with v4 |

**Note**: `@tailwindcss/vite` plugin is already at `^4.0.0` and `@tailwindcss/forms` at `^0.5.7`, `@tailwindcss/typography` at `^0.5.10`. These may conflict with tailwindcss v3 core — investigate potential mismatch.

**Migration path**: `npx @tailwindcss/upgrade` (official migration tool).

---

## 6. Frontend Bundle Analysis

### Client Build Summary

| Metric | Value |
|---|---|
| Total JS chunks | ~195 files |
| Total CSS | ~149.24 kB (gzip: ~24.39 kB) |
| Largest JS chunk | `Wizard-BopQaPci.js` — **725.68 kB** (gzip: 227.03 kB) |
| 2nd largest | `OrbitControls-B6SVrMFE.js` — **509.37 kB** (gzip: 127.49 kB) |
| 3rd largest | `app-3Wlwymos.js` — **411.81 kB** (gzip: 141.04 kB) |
| Build time | 6.26s (client) + 2.73s (SSR) |

### Chunks > 500 kB (Vite Warning Threshold)

| Chunk | Size | Gzip | Likely Content |
|---|---|---|---|
| `Wizard-BopQaPci.js` | 725.68 kB | 227.03 kB | Interrogation wizard (largest page) |
| `OrbitControls-B6SVrMFE.js` | 509.37 kB | 127.49 kB | **Three.js OrbitControls** |

### Three.js Analysis

- **Package**: `three: ^0.170.0` (dependencies, not devDependencies)
- **Bundle impact**: `OrbitControls-B6SVrMFE.js` alone is 509.37 kB
- **Office3D component**: 6.15 kB (gzip: 3.03 kB) — the Vue component itself is small
- **SSR build note**: `"AdditiveBlending" is imported from external module "three" but never used` — dead import in `resources/js/Support/Office/animations/visualEffects.js`
- **Recommendation**: Lazy-load Three.js and OrbitControls via dynamic `import()`. Remove unused `AdditiveBlending` import. Consider whether the 3D office visualization justifies 509+ kB.

### Other Large Chunks

| Chunk | Size | Gzip | Assessment |
|---|---|---|---|
| `app-3Wlwymos.js` | 411.81 kB | 141.04 kB | Main app bundle — contains Vue, Inertia, shared deps. Expected size. |
| `GraphCanvas-BRq9EVIs.js` | 164.55 kB | 53.71 kB | VueFlow graph — large but specialized |
| `MarkdownRenderer-H88Tds-6.js` | 159.34 kB | 48.57 kB | highlight.js + markdown rendering |
| `Index-DJj8Hbyn.js` | 133.40 kB | 40.72 kB | Unknown Index page — investigate |

### SSR Build
- SSR build warning: `"Thermometer"` imported but unused from `lucide-vue-next` in `AgentOffice.vue`
- Largest SSR chunk: `Wizard-BQtqzdMg.js` at 360.66 kB

---

## 7. AI API Cost Tracking

### Token Tracking Status: **Partially Implemented**

| Component | Status | Location |
|---|---|---|
| `MemoryProviderUsage` model | ✓ Implemented | `app/Models/MemoryProviderUsage.php` |
| Cost calculation method | ✓ Implemented | `MemoryProviderUsage::calculateCost()` — uses pricing from `config/memory.php` |
| Pricing table | ✓ Configured | `config/memory.php:173` — hardcoded per-provider pricing (USD/1M tokens) |
| Memory provider usage table | ✓ Created | Migration `2026_02_28_120600` |
| `ExecuteAgentRunJob` token parsing | ✓ Implemented | `app/Jobs/ExecuteAgentRunJob.php:695` — parses token usage from run events |
| CLI usage to dashboard | ✓ Implemented | Records to `MemoryProviderUsage` for dashboard tracking |
| `OrgCostLedger` model | ✓ Exists | Factory with `withCost(tokenCount, runtimeMs, costUsd)` |
| Telemetry ingestion | ✓ Exists | `app/Services/Telemetry/IngestionService.php:200` — requires `input_tokens`, `output_tokens`, `total_cost_usd` |
| `yethee/tiktoken` | ✓ Installed | `composer.json:28` — used in `TokenEstimator` for precise token counting |
| `TokenEstimator` | ✓ Implemented | `app/Support/Security/TokenEstimator.php` — uses tiktoken `EncoderProvider` |
| Memory stats command | ✓ Implemented | `app/Console/Commands/Memory/MemoryStatsCommand.php:257` — displays cost per provider |

### Missing Components

| Component | Status | Severity |
|---|---|---|
| OpenLLMetry integration | Not installed | P1 — no LLM-specific observability (token counts, cost, decision paths, latency per model call) |
| Cost anomaly alerting | Not found | P2 — no automated alerts for cost spikes |
| Model selection optimization | Not audited | P2 — no evidence of per-task model routing based on criticality |
| Real-time cost dashboard | Partial | Stats command exists but no live dashboard widget |

**Finding (P1)**: Token usage and cost estimation infrastructure exists via `MemoryProviderUsage` and `TokenEstimator`, but **OpenLLMetry is not installed** for comprehensive LLM observability. Current tracking covers memory operations but may not capture all AI API calls across the full job pipeline.

---

## Verification Checklist

- [x] All 15 Horizon supervisors documented with timeout compliance status
- [x] Naming deviation (`supervisor-1` vs `supervisor-long-running`) documented
- [x] Memory limit deviation (128MB vs 256MB) documented for long-running supervisors
- [x] Tailwind v3 → v4 compliance gap documented
- [x] Cache strategy presence/absence documented with P1/P2 classification
- [x] Bundle size captured with Three.js noted as optimization candidate
- [x] AI cost tracking status documented

---

## Findings Summary

| # | Category | Severity | Description |
|---|---|---|---|
| PERF-01 | Queue Compliance | P1 | `supervisor-1` naming deviation — should be `supervisor-long-running` |
| PERF-02 | Queue Compliance | P1 | All long-running supervisors use 128MB memory — rules require 256MB |
| PERF-03 | Queue Compliance | P1 | `supervisor-1` timeout 86,500s — far exceeds 600s rule |
| PERF-04 | Queue Compliance | P1 | `supervisor-interrogation` timeout 7,800s — exceeds 600s rule |
| PERF-05 | Queue Compliance | P1 | `supervisor-tunnel` unlimited timeout (0) with no boundary |
| PERF-06 | Queue Compliance | P1 | `supervisor-code-analysis` dynamic timeout (~3,780s default) — exceeds 600s |
| PERF-07 | Queue Compliance | P1 | `supervisor-subagent` timeout 3,600s — exceeds 600s rule |
| PERF-08 | Cache | P1 | No documented cache strategy (key naming, invalidation, warming, sizing) |
| PERF-09 | Frontend | P1 | Tailwind v3 with JS config — rules require v4 with CSS-first `@theme {}` |
| PERF-10 | Observability | P1 | OpenLLMetry not installed — no LLM-specific observability |
| PERF-11 | Queue Compliance | P2 | `supervisor-tunnel` balance set to string `'false'` — potential bug |
| PERF-12 | Database | P2 | `ConnectorAccount::all()` unbounded queries (2 occurrences) |
| PERF-13 | Database | P2 | 18+ `->get()` calls in controllers without matching `->with()` eager loading |
| PERF-14 | Frontend | P2 | Three.js `OrbitControls` chunk 509 kB — candidate for lazy-loading |
| PERF-15 | Frontend | P2 | `Wizard` chunk 726 kB — exceeds 500 kB Vite warning threshold |
| PERF-16 | Frontend | P2 | Unused imports: `AdditiveBlending` (three), `Thermometer` (lucide) |
| PERF-17 | Cache | P2 | Cacheable opportunities not exploited (ConnectorAccount, route/permission lookups) |
| PERF-18 | Observability | P2 | No cost anomaly alerting configured |

---

## Assumptions and Limitations

- N+1 detection is static analysis only — runtime profiling with Laravel Debugbar/Telescope would provide definitive results
- FK index analysis assumes `foreignId()->constrained()` pattern creates implicit indexes (standard Laravel behaviour)
- Bundle analysis based on `build-output.txt` from Task 2 — actual tree-shaking effectiveness not verified
- Horizon timeout "rules" assessed against engineering rules v2.0 requirement of 600s for `supervisor-long-running`
- Some long timeouts (agent, interrogation) may be intentional design decisions for long-running AI operations — the deviation from rules is documented but the business justification should be reviewed
