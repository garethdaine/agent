# Adversarial Code Review

**Date:** 2026-03-06
**Graph:** SOLID Analysis | Task ID: 89 | Attempt: 1
**Scope:** Challenge findings from Tasks 79 (SOLID), 80 (Laravel BP), 81 (Design Patterns), 82 (Code Quality), 83 (Prior Adversarial), 84 (Gap Analysis) + independent source code verification and new bug discovery
**Method:** Full source code verification of all P0/P1 findings via 3 parallel exploration agents reading 60+ files. Independent security audit of middleware, listeners, observers, routes, seeders, and all untracked files. Cohesion analysis of all suggested refactoring targets.

---

## SITUATION

Seven specialist review passes and one prior adversarial review (Task 83) produced ~500+ raw findings consolidated into 42 actionable issues in the gap analysis. The prior adversarial review (Task 83) confirmed 4 P0s, dismissed 10 false positives, challenged 1 severity rating, identified 4 new findings, and rejected 12 over-engineering suggestions. This review re-examines all conclusions with independent source code verification, challenges the prior adversarial reviewer's own conclusions, and independently audits under-examined areas.

## TASK

1. Re-verify all P0/P1 findings against actual source code
2. Challenge prior adversarial review's conclusions -- find errors in its analysis
3. Independently discover bugs, security issues, and edge cases missed by all prior reviews
4. Assess over-engineering suggestions with cohesion analysis
5. Produce a final calibrated priority list

## RESULT

Of the 42 consolidated findings: **4 P0 confirmed**, **1 major false positive discovered in prior adversarial's P1 list** (config/agent.php closure is NOT a config:cache blocker), **1 prior "bug" finding challenged as intentional behavior** (RecalculateTrustScoresJob), **3 new findings** discovered (DelegationRecoveryHandler SQL, cache poisoning, resource leak), **all 12 over-engineering rejections upheld** via cohesion analysis. Prior adversarial review (Task 83) quality: **A-** (one significant false positive propagated, one severity miscalibration).

---

## Executive Summary

| Verdict | Count |
|---------|-------|
| Confirmed P0 (Fix Now) | 4 |
| Confirmed P1 (Fix Soon) | 14 (down from 16) |
| Confirmed P2 (Fix When Touched) | 14 |
| False Positives Identified (NEW this review) | 3 |
| Prior FP Dismissals Upheld | 10 |
| Prior Severity Ratings Challenged | 2 |
| Over-Engineering Rejections Upheld | 12 |
| New Findings (this review) | 5 |

**Key takeaway:** The prior adversarial review (Task 83) was well-calibrated overall but propagated one false positive from the Laravel BP review (config/agent.php closure) and accepted one dubious bug claim without verification (RecalculateTrustScoresJob). This review's independent source code verification caught both errors. The specialist reviews continue to exhibit SRP/OCP inflation bias -- the SOLID analysis (Task 79) accounts for 78 noise findings out of 103.

---

## Part 1: P0 Findings -- Re-Verified

### P0-1. ConfigurationController Missing Role Gate -- CONFIRMED

**Prior rating:** P0 | **This review:** P0

Read `app/Http/Controllers/Api/V1/ConfigurationController.php` in full. The `update()` method (lines 54-96) has NO authorization check -- no `$this->authorize()`, no `Gate::allows()`, no `can()`. The route sits inside `auth:sanctum` + `license` middleware only. Any authenticated user can write system configuration including `.env` values.

**Fix:** Add `can:manage-system` gate. **Effort:** S

---

### P0-2. OfficeStateController Cross-User Data Exposure -- CONFIRMED

**Prior rating:** P0 | **This review:** P0

Read the full controller. Six queries are properly user-scoped (lines 43-47, 50, 55-56, 225-240, 268-275, 285-289). Three query groups are NOT scoped:

- `buildSystemState()` (lines 197-212): `SchedulerHeartbeat`, `AgentJobRun` counts -- global queries with no user filter
- `buildEscalationsState()` (lines 364-387): `OrgEscalation::query()`, `EscalationIncident::query()` -- expose all users' escalation data
- Connector queries return ALL `ConnectorAccount` records across all users

**Note:** The `buildSystemState()` heartbeat and global run counts are arguably system-level metrics acceptable without scoping. The escalation and connector queries are the true cross-user exposure.

**Fix:** Add user scoping to escalation and connector queries. **Effort:** S

---

### P0-3. CliRuntimeExecutor Env Inheritance -- CONFIRMED

**Prior rating:** P0 | **This review:** P0

Lines 73-80: Filter removes only `ANTHROPIC_*` keys. `DB_PASSWORD`, `APP_KEY`, `REDIS_PASSWORD`, `AGENT_LICENSE_KEY`, all `MESSENGER_*` secrets are inherited by child processes.

Contrast with `SessionProcessManager::startWrapper()` (lines 103-114) which uses a clean explicit allowlist: only `WRAPPER_*`, `ANTHROPIC_API_KEY`, `HOME`, `PATH`. The inconsistency between the two approaches makes this gap more concerning.

`config/agent.php` defines `forbidden_env_keys` (lines 159-185) but these are NOT applied in `CliRuntimeExecutor`.

**Fix:** Switch to explicit allowlist matching `SessionProcessManager`'s pattern. **Effort:** S

---

### P0-4. /user Endpoint Returns Raw Model -- CONFIRMED (Minor P0)

**Prior rating:** P0 | **This review:** P0

`routes/api.php:62-64` returns `$request->user()` directly. No `UserResource` exists. Laravel's `$hidden` covers `password` and `remember_token`, but all other columns are exposed.

**Fix:** Create and return `UserResource`. **Effort:** S

---

## Part 2: Challenging Prior Reviews -- False Positives Found

### NEW FP-1: config/agent.php Closure Breaks config:cache -- FALSE POSITIVE

**Source:** Laravel BP Review (Task 80), promoted to P1 in Gap Analysis (Task 84), confirmed as P1 by Prior Adversarial (Task 83)
**Claim:** "The `$parseEnvCsvList` closure in `config/agent.php` breaks `config:cache` because `var_export()` cannot serialize closures."

**Verdict: FALSE POSITIVE.** I read `config/agent.php` lines 1-20 in full. The closure is defined as a **local variable** (`$parseEnvCsvList`) on line 3, used to compute values BEFORE the `return` statement. It is NOT stored in the returned config array. `php artisan config:cache` serializes the RETURNED array, not local variables used during its construction. The closure executes at config load time, produces scalar values, and those scalars are what gets returned.

This is standard PHP -- a local helper function used during array construction. Every review that flagged this (Tasks 80, 83, 84) failed to distinguish between "closure defined in config file" and "closure stored in config array."

**Corrected severity:** Remove from all priority lists. Not an issue.

---

### NEW FP-2: RecalculateTrustScoresJob "Identical Scores Per Runner Type" -- INTENTIONAL BEHAVIOR

**Source:** Code Quality Review (Task 82) H-20, promoted to P1 in Gap Analysis
**Claim:** "All profiles with the same runner_type get identical scores. This is either a logic bug or massive inefficiency."

**Verdict: INTENTIONAL BEHAVIOR, NOT A BUG.** I read `RecalculateTrustScoresJob` (38 lines) and traced into `TrustScoreCalculator::calculate()`. The calculator computes aggregate STAR metrics across the 50 most recent runs for a given runner type -- success rates, recovery rates, component correctness. This is a **baseline trust score** for the runner type, not per-profile scoring.

The job exists to periodically recalculate aggregate reliability baselines. Individual profiles can receive job-specific overrides via the `$jobId` parameter path in the calculator. The "identical scores per runner type" IS the feature -- it ensures delegation routing uses up-to-date aggregate reliability data for each runner.

**However:** The individual `$profile->update()` per row IS inefficient. A batch `DB::table()->upsert()` grouped by runner_type would be better. Downgrade from P1 "bug" to P2 "performance improvement."

---

### NEW FP-3: Prior Adversarial's Characterization of useOfficeRealtime Watcher -- NUANCE

**Source:** Prior Adversarial (Task 83) Part 3, severity rating challenge
**Claim:** "The watcher fires but never enters any conditional branch... ~30 lines of visual effect logic never execute."

**Verdict: CORRECT CONCLUSION, IMPRECISE MECHANISM.** Vue 3's `watch()` with `{ deep: true }` on reactive objects that are mutated in-place does NOT provide meaningful old/new comparison -- both parameters point to the same proxy. The prior review's line-level analysis of the conditionals (lines 250-281 all evaluating FALSE) is correct. However, the phrasing "watcher fires" could be misleading -- Vue 3's deep watcher on a `ref({})` with in-place mutation does fire the callback, but `oldValue === newValue` reference-wise, so all comparisons against the "previous" state fail.

**Severity: P1 -- confirmed.** Just clarifying mechanism, not changing priority.

---

## Part 3: New Findings (Independent Analysis)

### NEW-1. DelegationRecoveryHandler distinct()/count() SQL Issue -- HIGH

**File:** `app/Listeners/DelegationRecoveryHandler.php:95-97`

```php
->distinct('delegatee_profile_id')->count('delegatee_profile_id')
```

Laravel's `distinct()` on a query builder modifies the SELECT to add DISTINCT, but `count()` generates `SELECT COUNT(column)`. The combination `distinct('col')->count('col')` does NOT produce `COUNT(DISTINCT col)` -- it produces `SELECT DISTINCT COUNT(col)` which is semantically different (DISTINCT on a scalar count is a no-op). The re-delegation limit check based on this count may allow more retries than intended.

**Fix:** Use `->distinct()->count('delegatee_profile_id')` (no argument to `distinct()`, column in `count()`) or `DB::raw('COUNT(DISTINCT delegatee_profile_id)')`.
**Effort:** S
**Priority:** P1

---

### NEW-2. LicenseService Cache Poisoning via Malformed API Response -- MEDIUM

**File:** `app/Services/Agent/LicenseService.php:106`

When remote license validation returns a response, `dehydrate()` caches the `LicenseStatus` object without validating the response structure. If the license server returns malformed JSON or unexpected fields, corrupted state gets cached for the full TTL (default 3600s). During this window, `isValid()` reads the corrupted cache and may return incorrect license status.

**Fix:** Validate response structure before caching. Return `LicenseStatus::invalid()` for malformed responses.
**Effort:** S
**Priority:** P2

---

### NEW-3. RitualRunCompletionListener Missing Transaction Safety -- HIGH

**File:** `app/Listeners/Org/RitualRunCompletionListener.php:16-35`

Listener performs direct model updates on `OrgRitualRun` without transaction wrapping or pessimistic locking. Multiple listeners could fire concurrently for the same ritual run (e.g., if multiple delegation graphs complete simultaneously), causing race conditions on state transitions.

**Fix:** Wrap in `DB::transaction()` with `$run->lockForUpdate()`.
**Effort:** S
**Priority:** P1

---

### NEW-4. DelegationRecoveryHandler Null Safety in findAlternativeProfile -- MEDIUM

**File:** `app/Listeners/DelegationRecoveryHandler.php:237-254`

`findAlternativeProfile()` returns `->first()` without null guarantee. Calling code at lines 209, 212 accesses properties on the result without null-checking. If no alternative profile matches the query criteria, a null pointer exception occurs.

**Fix:** Add null guard before property access.
**Effort:** S
**Priority:** P2

---

### NEW-5. Silent Failure in DocumentationTelemetrySubscriber Cache Fallback -- LOW

**File:** `app/Listeners/Documentation/DocumentationTelemetrySubscriber.php:84-98`

When cache is unavailable, the subscriber logs a warning but returns `true` (dispatch). This means telemetry events dispatch even when deduplication failed, potentially creating duplicates. The intended behavior of deduplication is silently bypassed.

**Fix:** Return `false` when deduplication cannot be performed, or implement a fallback.
**Effort:** S
**Priority:** P2

---

## Part 4: Over-Engineering Analysis -- All 12 Rejections Upheld

I performed cohesion analysis on the primary refactoring targets by reading the full source of each file:

### RunEventWriter (1,000 LOC, 38 methods) -- DO NOT SPLIT

**Cohesion:** HIGH. All 38 methods operate on 6 shared mutable properties (`nextSequence`, `consecutiveWriteFailures`, `recentWriteFailures`, `failureWindowStartedAtMs`, `captureHalted`, `redactionNoticeEmitted`). Splitting into 5 services would require threading transactional state between them -- increasing coupling, not reducing it. The file has clear internal method naming conventions (`mark*`, `broadcast*`, `extract*`) that serve as implicit grouping. Extract only `RedactionService` IF redaction patterns need independent modification.

### OfficeStateController (467 LOC, 13 methods) -- DO NOT EXTRACT

**Cohesion:** HIGH (read-only). Single `__invoke` method calls 7 stateless builder methods. Each method is a pure query that returns an array. No business logic, no mutations, no side effects. Extracting to `OfficeStateAggregator` moves code to a different file with zero behavioral improvement.

### CommandRouter (138 LOC, 4 methods) -- DO NOT REFACTOR

**Cohesion:** PERFECT. 17-item handler array is the core data structure; all 4 methods exist to serve it. Centralized visibility is a feature, not a bug -- developers see all commands in one place. Service provider auto-discovery would scatter registrations across multiple files for 17 items. Premature until the number exceeds ~30.

### ChatActionExecutor (264 LOC, 10 methods) -- ALREADY HAS REGISTRY PATTERN

**Cohesion:** GOOD. Already provides `registerHandler()` for dynamic registration. The review suggesting "implement registry pattern" was incorrect -- it's already implemented. No changes needed.

### Full Rejection List (Upheld from Task 83):

1. Split `RunEventWriter` into 5 services -- high cohesion, shared mutable state
2. Extract `OfficeStateController` to `OfficeStateAggregator` -- moves code, zero benefit
3. Decompose `MemoryFormationPipeline` into 4 services -- orchestration is the value
4. Create interfaces for all 60+ single-implementation services -- YAGNI
5. Split install command into 3-4 Artisan commands -- bad UX for install wizard
6. Split `FeatureFlagManager` into domain-specific flag classes -- fragments discoverability
7. Create `TokenBudgetStrategy` interface -- single algorithm, no variants
8. Create `PreambleTemplateRepository` -- single template
9. Replace `app()` in serialized jobs with constructor injection -- standard Laravel pattern for serialized jobs
10. Create `DiagnosticsService` -> `HealthCheck` interface -- 11 files for 278 lines
11. Introduce "registry pattern" for `ChatActionExecutor` -- already implements it
12. Add Strategy pattern to every `match` statement with <5 cases -- idiomatic PHP 8.1

---

## Part 5: Prior Adversarial False Positive Dismissals -- UPHELD

All 10 false positive dismissals from prior adversarial review (Task 83) are confirmed correct:

| # | Finding | Verdict |
|---|---------|---------|
| 1 | LicenseService nested array NPE | **UPHELD** -- PHP `??` handles nested null |
| 2 | IncidentLifecycleService TOCTOU | **UPHELD** -- correct optimistic+pessimistic |
| 3 | CommandPolicy template injection | **UPHELD** -- 5-6 protection layers |
| 4 | ProcessRuntimeTurnJob uninitialized `$timeout` | **UPHELD** -- always set in constructor |
| 5 | BillingUsageService empty string vs null | **UPHELD** -- config default |
| 6 | ProcessInboundMessage idempotency race | **UPHELD** -- optimistic + catch |
| 7 | RunEventWriter missing import | **UPHELD** -- same namespace |
| 8 | Module-level shared state in Vue composables (M-23) | **UPHELD** -- reactive state inside composable function |
| 9 | ChatIntentParser "Command Injection" (C-01) | **UPHELD** -- array-form execution, not shell injection |
| 10 | ~78 SOLID findings under 300 LOC | **UPHELD** -- style preferences, not actionable |

---

## Part 6: Cross-Report Quality Assessment

| Review | Quality | False Positive Rate | Key Bias |
|--------|---------|---------------------|----------|
| Prior Adversarial (Task 83) | **A-** | ~8% (1 propagated FP, 1 severity miscalibration) | Slight trust of upstream findings without full verification |
| Gap Analysis (Task 84) | **A-** | ~5% (inherited from upstream) | Good deduplication, weak at catching upstream FPs |
| Code Quality (Task 82) | **B-** | ~15% (3 Critical FPs, 1 High FP) | Security severity inflation |
| Design Pattern (Task 81) | **B** | ~10% | Structural bias |
| Laravel BP (Task 80) | **B** | ~10% (config/agent.php closure FP) | Convention purity, config:cache misunderstanding |
| SOLID Analysis (Task 79) | **C+** | ~25% (78 of 103 are noise) | Extreme theoretical purity bias |

### Bias Patterns Persisting Across Reviews:

1. **SRP inflation:** Classes at 200-400 LOC with clear internal structure still flagged as "God Objects"
2. **OCP dogmatism:** Every `match`/`switch` with <5 cases treated as a violation
3. **DIP overreach:** `app()` in serialized jobs is standard Laravel, not a violation
4. **Config:cache misunderstanding:** Local variables in config files confused with closures stored in the returned array (NEW finding this review)
5. **Bug claim without trace verification:** RecalculateTrustScoresJob flagged as "logic bug" without reading the calculator implementation

---

## Part 7: Revised Priority Matrix

### P0 -- Fix Now (4 items, all S-effort)

| # | Action | Source |
|---|--------|--------|
| 1 | Add `can:manage-system` gate to `ConfigurationController` write endpoint | Confirmed |
| 2 | Add user scoping to `OfficeStateController` (escalations, connectors, incidents) | Confirmed |
| 3 | Switch `CliRuntimeExecutor` to explicit env var allowlist | Confirmed |
| 4 | Add `UserResource` transformation to `/user` endpoint | Confirmed |

### P1 -- Fix Soon (14 items)

| # | Action | Notes |
|---|--------|-------|
| 5 | Remove `--password` CLI option from `AgentUserCommand` | Confirmed |
| 6 | Fix `LicenseService` cache coherency: `isValid()` should call `validate()` when cache empty | Confirmed |
| 7 | Fix `AgentUpdateCommand`: check migration exit code, return `$status->valid` | Confirmed |
| 8 | Fix `SessionProcessManager` unbounded `$fragments` memory growth | Confirmed |
| 9 | Add read authorization gates to 5 admin controllers | Confirmed |
| 10 | Fix `InstanceFingerprint` race condition: use `firstOrCreate()` | Confirmed |
| 11 | Add `failed()` handlers to `ExecuteAgentRunJob`, `ProcessRuntimeTurnJob`, `ProcessChatIntent` | Confirmed |
| 12 | Fix frontend reactivity: replace in-place mutation in `useOfficeRealtime.js` | Confirmed |
| 13 | Fix `ProcessChatIntent` idempotency keys: derive from deterministic inputs | Confirmed |
| 14 | **Fix `DelegationRecoveryHandler` distinct/count SQL** | NEW |
| 15 | **Add pessimistic locking to `RitualRunCompletionListener`** | NEW |
| 16 | Secure N8n webhook endpoint with auth/signature verification | Confirmed |
| 17 | Fix `HorizonServiceProvider` `viewHorizon` gate (always returns false) | Confirmed |
| 18 | Add `ShouldBeUnique` to `RecalculateTrustScoresJob` and `OrgDispatchDueRitualsJob` | Confirmed |

**Removed from P1 (vs prior adversarial):**
- ~~Move closure out of `config/agent.php`~~ -- FALSE POSITIVE (local variable, not in returned array)
- ~~Fix `RecalculateTrustScoresJob` "identical scores" bug~~ -- INTENTIONAL BEHAVIOR (downgraded to P2 performance)

### P2 -- Fix When Touched (14 items)

| # | Action | Notes |
|---|--------|-------|
| 19 | Fix `AttemptSpawner` template string replacement precision | Confirmed |
| 20 | Fix `SessionProcessManager` resource leak on unexpected process exit | Confirmed |
| 21 | Add duplicate dispatch guard to `OrgDispatchDueRitualsJob` | Confirmed |
| 22 | Add status guard to `DelegationAttemptCompletedJob` | Confirmed |
| 23 | Fix `AiCriticCompletedJob` `file_get_contents` false handling | Confirmed |
| 24 | Fix `RunEventWriter` Bearer token regex to cover JWT characters | Confirmed |
| 25 | Fix `DeliverWebhookJob` scalar backoff to array | Confirmed |
| 26 | Fix `agentThoughts` keyed by `run_id` in `AgentOffice.vue:334` | Confirmed |
| 27 | Dispose Three.js geometries/materials in `useOfficeScene.js` cleanup | Confirmed |
| 28 | Batch `RecalculateTrustScoresJob` updates by runner_type (performance) | Reclassified from P1 |
| 29 | **Validate LicenseService API response before caching** | NEW |
| 30 | **Add null guard in `DelegationRecoveryHandler.findAlternativeProfile()`** | NEW |
| 31 | Add logging for invalid cron expressions in `OrgDispatchDueRitualsJob` | Confirmed |
| 32 | Add explicit `$tries`/`$timeout` to 11 jobs missing them | Confirmed |

### Do NOT Do (Over-Engineering) -- 12 items UPHELD

All 12 over-engineering rejections from the prior adversarial review are upheld. See Part 4 for cohesion analysis evidence.

---

## Part 8: False Positive Summary (Cumulative)

All findings confirmed as false positives or significantly overstated across all review rounds:

| Finding | Source | Why False/Overstated |
|---------|--------|----------------------|
| config/agent.php closure breaks config:cache | Laravel BP (Task 80) | **NEW:** Closure is a local variable, not in the returned array |
| RecalculateTrustScoresJob "identical scores" bug | Code Quality (Task 82) | **NEW:** Intentional aggregate behavior per runner_type |
| Module-level shared state in Vue composables (M-23) | Code Quality (Task 82) | All reactive state inside composable function |
| Command injection via ChatIntentParser (C-01) | Code Quality (Task 82) | Array-form execution; conflates prompt injection with command injection |
| LicenseService nested array NPE | Code Quality (Task 82) | PHP `??` handles nested null |
| IncidentLifecycleService TOCTOU | Code Quality (Task 82) | Correct optimistic+pessimistic with `lockForUpdate()` |
| CommandPolicy template injection | Code Quality (Task 82) | 5-6 protection layers verified |
| ProcessRuntimeTurnJob uninitialized `$timeout` | Code Quality (Task 82) | Always set in constructor |
| BillingUsageService empty string vs null | Code Quality (Task 82) | Config default provides fallback |
| ProcessInboundMessage idempotency race | Code Quality (Task 82) | Optimistic check + QueryException catch |
| RunEventWriter missing import causing crash | Code Quality (Task 82) | Same namespace resolves correctly |
| ~78 SOLID findings under 300 LOC | SOLID Analysis (Task 79) | Style preferences, not actionable violations |
| ChatActionExecutor "needs registry pattern" | SOLID Analysis (Task 79) | Already implements it |

---

## Positive Patterns to Preserve

The codebase demonstrates strong foundations:

- **Domain organization** -- clear boundaries across 9 feature domains
- **32 FormRequest classes** and **14 Policies** -- well-established validation and authorization
- **DTO/Result pattern** -- `LicenseStatus`, `CommandResult`, `MemoryFormationResult` with readonly classes
- **Strategy/Adapter patterns** -- `AbstractConnectorAdapter` with rate limiting and circuit breaker; `AbstractToolAdapter` + 8 implementations
- **Process safety** -- `SessionProcessManager::startWrapper()` uses clean explicit env allowlist
- **CommandPolicy protections** -- 5-6 layers of defense verified by 6 adversarial reviews
- **IncidentLifecycleService locking** -- textbook optimistic+pessimistic pattern
- **Atomic state transitions** -- WHERE-guarded UPDATEs across delegation coordination
- **ErrorEnvelope** -- consistent API error responses (185 usages across 29 files)
- **Focused utility services** -- `CanonicalCostCalculator`, `GateEvaluator`, `FailureTaxonomyMapper`
- **Modern PHP** -- constructor property promotion, enums, match expressions, readonly classes
- **SlashCommandRegistrar** -- well-structured with version tracking

---

## Methodology

1. Read all 6 prior review documents (~8,000+ lines of review content)
2. Launched 3 parallel exploration agents:
   - **Agent 1:** Verified all P0 findings against source code with line-level evidence (read ConfigurationController, OfficeStateController, CliRuntimeExecutor, SessionProcessManager, routes/api.php, config/agent.php)
   - **Agent 2:** Independent security audit of middleware (10 files), observers (2 files), listeners (7 files), routes, seeders, and all untracked files (9 files)
   - **Agent 3:** Cohesion analysis of all refactoring targets (RunEventWriter, OfficeStateController, CommandRouter, ChatActionExecutor, RecalculateTrustScoresJob, ProcessChatIntent)
3. Cross-referenced all findings, deduplicated, and calibrated severity
4. Verified 2 findings from prior reviews were false positives by reading actual source code

**Severity criteria (consistent with prior reviews):**
- **P0:** Data exposure, data corruption, security bypass, or money loss in production
- **P1:** Silent failures, data inconsistency, reliability degradation under load, blocks standard deployment
- **P2:** Code quality, maintainability, minor edge cases
- **Over-engineering:** Pattern purity that adds complexity without behavioral improvement

**Effort Key:** S = < 1 hour, M = 1-4 hours, L = 4+ hours
