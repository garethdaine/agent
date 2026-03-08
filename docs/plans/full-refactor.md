# Implementation Plan

Derived from discovery session 7.

# AgentOps Full Refactor — Implementation Plan

## STAR Preamble

**SITUATION:** Laravel 12 / PHP 8.3 monolith with 74 confirmed findings (14 P0, 41 P1, 19 P2) across security, CI/CD, code quality, architecture, frontend, testing, and observability. 55 Linear issues exist (AGE-2281–AGE-2335). 19 P2 findings lack Linear issues. Zero CI workflows, 57 models with mass-assignment vulnerability, 3,413 PHPStan errors, 504 files missing strict_types, 3 god classes, missing delegation memory Layer 4.

**TASK:** Fix all 74 findings, create 19 P2 Linear issues, update all 55 existing issues to Done (except AGE-2293 → Won't Fix), generate REFACTOR_REPORT.md with before/after metrics.

**ACTION:** 13-phase execution ordered by dependency chain (foundation → security → quality → architecture → frontend → testing → verification).

**RESULT:** Zero PHPStan errors, zero Pint violations, zero ESLint problems, all tests green, 8 CI workflows operational, all 57 models on $fillable, delegation memory Layer 4 complete, REFACTOR_REPORT.md generated.

---

## Phase 1: Linear Issue Management & Baseline Capture

### 1.1 Capture Before-State Metrics
- [ ] Record PHPStan error count (3,413)
- [ ] Record Pint violation count (284 across 138 files)
- [ ] Record ESLint problem count (19: 2 errors, 17 warnings)
- [ ] Record test suite results (3,959 passed, 11 failed, 9 skipped)
- [ ] Record strict_types compliance (406/910 files)
- [ ] Record factory count (43 existing, 44 missing)
- [ ] Record guarded model count (57)
- [ ] Record CI workflow count (1 — docs-deploy-sync only)
- [ ] Record chunk sizes (Wizard 726 kB, OrbitControls 509 kB)
- [ ] Record Horizon supervisor naming/memory state

### 1.2 Create 19 P2 Linear Issues
- [ ] Create Linear issue for Finding #56: `$request->all()` → `$request->only()` in MessengerConnectorController
- [ ] Create Linear issue for Finding #57: v-html sanitization in 10 Vue files
- [ ] Create Linear issue for Finding #58: DB::raw() safety review (9 locations)
- [ ] Create Linear issue for Finding #59: 87 files exceeding 300-line threshold
- [ ] Create Linear issue for Finding #60: AbstractBuildAdapter extraction from CodexAdapter/ClaudeAdapter
- [ ] Create Linear issue for Finding #61: Three.js OrbitControls lazy-loading
- [ ] Create Linear issue for Finding #62: Wizard chunk splitting below 500 kB
- [ ] Create Linear issue for Finding #63: Unbounded ConnectorAccount::all() in MessengerHealthController
- [ ] Create Linear issue for Finding #64: Caching for ConnectorAccount lookups
- [ ] Create Linear issue for Finding #65: laravel/ai availability tracking (informational)
- [ ] Create Linear issue for Finding #66: laravel/mcp evaluation
- [ ] Create Linear issue for Finding #67: laravel/boost evaluation
- [ ] Create Linear issue for Finding #68: Application Dockerfile
- [ ] Create Linear issue for Finding #69: Pennant feature flag cleanup
- [ ] Create Linear issue for Finding #70: supervisor-tunnel balance fix
- [ ] Create Linear issue for Finding #71: Fix 11 failing tests, investigate 9 skipped
- [ ] Create Linear issue for Finding #72: ESLint 19 problems fix
- [ ] Create Linear issue for Finding #73: 5 Vue components Options API → script setup
- [ ] Create Linear issue for Finding #74: Playwright role-based locator migration

### 1.3 Close AGE-2293 as Won't Fix
- [ ] Add comment to AGE-2293 explaining conscious decision to retain `--dangerously-skip-permissions` in AttemptSpawner.php:125-131
- [ ] Set AGE-2293 state to Won't Fix / Cancelled

---

## Phase 2: Foundation — Formatting, Strict Types, Pint (Findings #29, #30)

**Dependency:** None. Must precede PHPStan work since strict_types surfaces new type errors.

### 2.1 Batch strict_types Declaration (Finding #29, AGE-2309)
- [ ] Script to add `declare(strict_types=1);` to all 504 missing PHP files in one batch
- [ ] Verify 910/910 PHP files have the declaration via grep
- [ ] Run test suite — capture any new TypeErrors for Phase 5 PHPStan resolution
- [ ] Update AGE-2309 to Done

### 2.2 Pint Auto-Fix (Finding #30, AGE-2310)
- [ ] Run `./vendor/bin/pint` to auto-fix 284 violations across 138 files
- [ ] Verify `./vendor/bin/pint --test` exits cleanly
- [ ] Update AGE-2310 to Done

---

## Phase 3: Security P0 Fixes (Findings #1, #10, #11, #12)

**Dependency:** Phase 2 (files are formatted consistently before mass edits).

### 3.1 Mass Assignment — $guarded → $fillable (Finding #1, AGE-2281)
- [ ] For each of the 57 models: audit every `::create()`, `->fill()`, `->update()`, `->forceFill()` call site to derive correct $fillable list
- [ ] Batch 1 (Agent domain — 9 models): AgentAuditLog, AgentBackupSetting, AgentFeatureSetting, AgentJob, AgentJobRun, AgentMaintenanceCheckpoint, AgentRunEvent, AgentSystemState, AccountLinkToken
- [ ] Batch 2 (Chat/Messenger domain — 8 models): ChatAction, ChatAttachment, ChatMessage, ChatSession, MessengerDeadLetter, MessengerEventDeduplication, MessengerIdentityLink, PendingConfirmation
- [ ] Batch 3 (Delegation domain — 10 models): DelegateeMetric, DelegateeProfile, DelegationAttempt, DelegationCapability, DelegationEvent, DelegationGraph, DelegationTask, DelegationTaskDependency, DelegationVerificationResult, EscalationIncident
- [ ] Batch 4 (Memory domain — 7 models): MemoryConsolidationLog, MemoryConversationLog, MemoryCoreBlock, MemoryEmbedding, MemoryFormationFailure, MemoryProviderUsage, MemorySetting
- [ ] Batch 5 (Connector domain — 3 models): ConnectedProvider, ConnectorAccount, CredentialVault
- [ ] Batch 6 (Interrogation domain — 5 models): InterrogationBuildTask, InterrogationEvent, InterrogationSession, InterrogationSetting, InterrogationTechStack
- [ ] Batch 7 (Documentation domain — 3 models): DocumentationEntry, DocumentationFragment, DocumentationLink
- [ ] Batch 8 (RepoAnalysis domain — 5 models): RepoAnalysisArtifact, RepoAnalysisEvent, RepoAnalysisReport, RepoAnalysisSession, RepoAnalysisTask
- [ ] Batch 9 (Remaining — 7 models): ApiDocArtifact, NlOrgParseAttempt, NlParseAttempt, RunClassification, SchedulerHeartbeat, TunnelSetting, WorkflowGateTransition
- [ ] Run full test suite after each batch
- [ ] Verify grep for `guarded.*\[\]` returns zero results across app/Models/
- [ ] Update AGE-2281 to Done

### 3.2 Sentry Installation (Finding #10, AGE-2290)
- [ ] `composer require sentry/sentry-laravel`
- [ ] `php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"`
- [ ] Add `SENTRY_DSN=` placeholder to `.env.example`
- [ ] Verify config/sentry.php exists and is published
- [ ] Update AGE-2290 to Done

### 3.3 Remove soul_json from API (Finding #11, AGE-2291)
- [ ] Remove `soul_json` field from `DelegateeProfileController::transformProfile()` at line 324
- [ ] Ensure API response no longer contains system prompts, personality, or user_context fields
- [ ] Add test verifying soul_json is absent from API response
- [ ] Update AGE-2291 to Done

### 3.4 Memory Formation Input Sanitization (Finding #12, AGE-2292)
- [ ] Add input validation/sanitization in MemoryFormationPipeline at lines 196-204
- [ ] Reject or escape malicious entity content (injection markers, control characters, oversized payloads)
- [ ] Add tests proving malicious entity names/types/content are rejected
- [ ] Update AGE-2292 to Done

---

## Phase 4: Security P1 Fixes (Findings #15–#23)

**Dependency:** Phase 3 (P0 security resolved first).

### 4.1 SHA-Pin GitHub Actions (Findings #15–#16, AGE-2295, AGE-2296)
- [ ] Replace `actions/checkout@v4` with full commit SHA in docs-deploy-sync.yml:25
- [ ] Replace `shivammathur/setup-php@v2` with full commit SHA in docs-deploy-sync.yml:28
- [ ] Update AGE-2295, AGE-2296 to Done

### 4.2 N8n Webhook Auth (Finding #17, AGE-2297)
- [ ] Add auth middleware or HMAC signature verification to N8n webhook route at routes/api.php:79
- [ ] Add test verifying unauthenticated requests are rejected
- [ ] Update AGE-2297 to Done

### 4.3 Session Secure Cookie (Finding #18, AGE-2298)
- [ ] Set `'secure' => env('SESSION_SECURE_COOKIE', true)` in config/session.php
- [ ] Update AGE-2298 to Done

### 4.4 Prompt Injection Sanitization (Findings #19–#20, AGE-2299, AGE-2300)
- [ ] Sanitize soul data in GeneralTaskHandler.php:178,184 — escape/strip injection markers before prompt concatenation
- [ ] Validate contract_json prompt content in AiCriticStep.php:154 — sanitize template variables before str_replace
- [ ] Add tests for both sanitization paths
- [ ] Update AGE-2299, AGE-2300 to Done

### 4.5 Trust-Gated Pre-Execution Approval (Finding #21, AGE-2301)
- [ ] Modify HumanApprovalStep.php:35-72 to check DelegateeProfile::trust_score and DelegationTask::contract_json['reversibility'] BEFORE task execution
- [ ] If trust_score < 0.7 OR reversibility is false → task enters `pending_approval` state, waits for human confirmation
- [ ] If trust_score >= 0.7 AND reversibility is true → task proceeds immediately
- [ ] Add `delegation.approval_trust_threshold` key to config/agent.php defaulting to 0.7
- [ ] Add tests: low-trust blocked, irreversible blocked, high-trust+reversible proceeds
- [ ] Update AGE-2301 to Done

### 4.6 PII Redaction in RunEventWriter (Finding #22, AGE-2302)
- [ ] Add PII redaction at RunEventWriter.php:61-73 before logging — filter emails, API keys, credentials
- [ ] Add test verifying sensitive patterns are redacted from log output
- [ ] Update AGE-2302 to Done

### 4.7 Soul Content Validation (Finding #23, AGE-2303)
- [ ] Add content validation to DelegateeProfile::setSoul() — reject strings containing API key patterns, credential markers
- [ ] Add test for rejection of API key patterns (sk-*, AKIA*, Bearer tokens)
- [ ] Update AGE-2303 to Done

---

## Phase 5: CI/CD Workflows (Findings #2–#9)

**Dependency:** Phase 2 (Pint clean), Phase 3 (Sentry installed). Workflows reference tools that must be functional.

### 5.1 Create 8 Separate CI Workflow Files (AGE-2282 through AGE-2289)
- [ ] `.github/workflows/test.yml` — PHP tests via `php artisan test --parallel` on push/PR; PostgreSQL service container
- [ ] `.github/workflows/phpstan.yml` — `./vendor/bin/phpstan analyse` at level 5
- [ ] `.github/workflows/pint.yml` — `./vendor/bin/pint --test`
- [ ] `.github/workflows/composer-audit.yml` — `composer audit`
- [ ] `.github/workflows/eslint.yml` — `npx eslint resources/`
- [ ] `.github/workflows/vitest.yml` — `npx vitest run`
- [ ] `.github/workflows/npm-audit.yml` — `npm audit`
- [ ] `.github/workflows/build.yml` — `npm run build` verification
- [ ] Each workflow: set appropriate triggers (push to main, PR), PHP 8.3/8.4 matrix where applicable, Node 22.x
- [ ] Update AGE-2282 through AGE-2289 to Done

---

## Phase 6: PHPStan Zero Errors (Finding #14, AGE-2294)

**Dependency:** Phase 2 (strict_types added — surfaces TypeErrors that become PHPStan errors), Phase 3 ($fillable changes may affect type analysis).

### 6.1 PHPStan Level 5 Error Resolution
- [ ] Run `./vendor/bin/phpstan analyse` and categorize 3,413 errors by type (missing return types, parameter types, property types, undefined methods, etc.)
- [ ] Fix errors in dependency order: Models → DTOs → Services → Jobs → Controllers → Support
- [ ] Fix strict_types-induced TypeErrors discovered in Phase 2
- [ ] Verify `./vendor/bin/phpstan analyse` returns zero errors
- [ ] phpstan.neon configured: level 5, paths: app/, config/, database/, routes/
- [ ] Update AGE-2294 to Done

---

## Phase 7: God Class Refactoring (Findings #24–#27)

**Dependency:** Phase 6 (PHPStan clean — extracted classes must also pass static analysis).

### 7.1 InterrogationSessionController Decomposition (Finding #24, AGE-2304)
- [ ] Create `app/Services/Interrogation/InterrogationBuildService.php` — extract build task management methods
- [ ] Create `app/Services/Interrogation/InterrogationPlanService.php` — extract plan revisions, state transitions
- [ ] Create `app/Services/Interrogation/InterrogationExportService.php` — extract export functionality
- [ ] Create `app/Services/Interrogation/InterrogationApprovalService.php` — extract approval flows
- [ ] Create test files: `tests/Feature/Interrogation/InterrogationBuildServiceTest.php`, `InterrogationPlanServiceTest.php`, `InterrogationExportServiceTest.php`, `InterrogationApprovalServiceTest.php`
- [ ] Wire services into slim controller via constructor injection
- [ ] Verify controller delegates to services, no business logic remains inline
- [ ] Update AGE-2304 to Done

### 7.2 RunEventWriter Decomposition (Finding #25, AGE-2305)
- [ ] Create `app/Support/Agent/EventPatternMatcher.php` — rate-limit detection, approval/permission/clarification pattern matching
- [ ] Create `app/Support/Agent/OutputRedactor.php` — PII redaction, content filtering (absorbs Finding #22 work)
- [ ] Create `app/Support/Agent/EventBroadcaster.php` — Reverb broadcasting, memory integration
- [ ] Create test files for each extracted class
- [ ] Update AGE-2305 to Done

### 7.3 RepoAnalysisSessionController Decomposition (Finding #26, AGE-2306)
- [ ] Create `app/Services/RepoAnalysis/RepoAnalysisWorkflowService.php` — task planning/execution/retry, pause/resume/retry workflows
- [ ] Create `app/Services/RepoAnalysis/RepoAnalysisReportService.php` — snapshot generation, coverage validation, report generation
- [ ] Create test files for each extracted service
- [ ] Wire services into slim controller
- [ ] Update AGE-2306 to Done

### 7.4 AgentRunController::stop() Refactor (Finding #27, AGE-2307)
- [ ] Extract `terminateProcess()` private method
- [ ] Extract `cleanupState()` private method
- [ ] Extract `recordStopEvent()` private method
- [ ] `stop()` method becomes orchestrator calling the 3 extracted methods
- [ ] Update AGE-2307 to Done

---

## Phase 8: Action Pattern Migration (Finding #28, AGE-2308)

**Dependency:** Phase 7 (god classes decomposed — avoids extracting Actions from code that will be moved to services).

### 8.1 Extract Actions from All 77 Controllers
- [ ] Audit all 77 controllers for inline `::where()`, `->save()`, `->create()`, `->update()`, `->delete()` DB operations
- [ ] Create Action classes in `app/Actions/{Domain}/` for each extracted operation
- [ ] Priority controllers (highest DB op concentration): RepoAnalysisSessionController (14 ops), DelegationGraphController (5), ChatSessionController (5), AgentJobController, MessengerConnectorController
- [ ] Each Action: single `execute()` or `__invoke()` method, typed parameters, typed return
- [ ] Controllers become thin — delegate to Actions for DB operations, Services for business logic
- [ ] Run full test suite after migration
- [ ] Update AGE-2308 to Done

---

## Phase 9: Observability & Infrastructure (Findings #10, #31–#39, #46–#49)

**Dependency:** Phase 6 (PHPStan clean — new config files and services must pass static analysis).

### 9.1 OpenTelemetry Installation (Finding #36, AGE-2316)
- [ ] `composer require open-telemetry/opentelemetry-php` (or appropriate meta-package)
- [ ] Configure console/log exporter only — traces emit to Laravel log
- [ ] Add `OTEL_EXPORTER_OTLP_ENDPOINT=` placeholder to `.env.example`
- [ ] Do NOT configure OTLP gRPC/HTTP or external collectors
- [ ] Update AGE-2316 to Done

### 9.2 OpenLLMetry Installation (Finding #37, AGE-2317)
- [ ] Install LLM-specific instrumentation package for token/cost/latency tracking
- [ ] Configure console exporter for development
- [ ] Update AGE-2317 to Done

### 9.3 Laravel Pulse Configuration (Finding #38, AGE-2318)
- [ ] Install and configure Laravel Pulse
- [ ] Register dashboard route accessible via web middleware with appropriate auth gate
- [ ] Verify Pulse dashboard is accessible at configured route (e.g., /pulse) with navigation entry or documented URL
- [ ] Update AGE-2318 to Done

### 9.4 Structured JSON Logging (Finding #48, AGE-2328)
- [ ] Set default production log stack to JSON channel in config/logging.php
- [ ] Update AGE-2328 to Done

### 9.5 Log Redaction Default (Finding #49, AGE-2329)
- [ ] Set `LOG_REDACT_SENSITIVE` default to `true` in config/logging.php
- [ ] Update AGE-2329 to Done

### 9.6 Horizon Supervisor Normalization (Findings #33–#35, AGE-2313, AGE-2314, AGE-2315)
- [ ] Rename `supervisor-1` to `supervisor-long-running` in config/horizon.php (AGE-2313)
- [ ] Set 256MB memory for long-running supervisors: supervisor-long-running, supervisor-interrogation, supervisor-code-analysis, supervisor-subagent (AGE-2314)
- [ ] Review and normalize timeout outliers — document justification for each non-standard timeout (AGE-2315)
- [ ] Update AGE-2313, AGE-2314, AGE-2315 to Done

### 9.7 Cache Strategy Documentation (Finding #39, AGE-2319)
- [ ] Create cache strategy document: key naming convention (`{domain}:{entity}:{id}`), invalidation policy (model observer + TTL), warming strategy (deploy-time artisan command), sizing guidelines
- [ ] Update AGE-2319 to Done

### 9.8 Husky + lint-staged + commitlint (Findings #31–#32, AGE-2311, AGE-2312)
- [ ] Install Husky + lint-staged with pre-commit hooks: Pint on staged .php files, ESLint on staged .vue/.js/.ts files, type-check
- [ ] Install commitlint with conventional commit preset
- [ ] Update AGE-2311, AGE-2312 to Done

### 9.9 Deployment Documentation (Findings #46–#47, AGE-2326, AGE-2327)
- [ ] Document deployment strategy with `horizon:terminate` step
- [ ] Update AGE-2326, AGE-2327 to Done

---

## Phase 10: Architecture — Delegation Framework (Findings #50–#55)

**Dependency:** Phase 6 (PHPStan clean), Phase 3 ($fillable pattern established for new models).

### 10.1 Capability Profile JSON Column (Finding #50, AGE-2330)
- [ ] Create migration: add `capability_profile` JSON column to `delegatee_profiles` table
- [ ] Update DelegateeProfile model with $casts for capability_profile
- [ ] Update AGE-2330 to Done

### 10.2 Trust Score History (Finding #54, AGE-2334)
- [ ] Create migration: `trust_score_histories` table with columns: id, delegatee_profile_id (FK), score (decimal 3,2), components_json, calculated_at (timestamp), reason (string)
- [ ] Create TrustScoreHistory model with $fillable
- [ ] Wire into TrustScoreCalculator to persist history on each recalculation
- [ ] Add tests
- [ ] Update AGE-2334 to Done

### 10.3 ContractEnforcer Completion (Finding #51, AGE-2331)
- [ ] Add deadline enforcement: check `time_constraints.deadline_ts`, fail/escalate if exceeded
- [ ] Add criticality-based escalation triggers (critical → immediate escalation, high → warn + escalate, normal/low → log)
- [ ] Add resource quota enforcement (check against contract-defined limits)
- [ ] Add per-capability permission validation (verify delegatee has required capabilities for each tool/action)
- [ ] Add tests for each enforcement path
- [ ] Update AGE-2331 to Done

### 10.4 PermissionAttenuationService (Finding #52, AGE-2332)
- [ ] Create `app/Services/Delegation/PermissionAttenuationService.php`
- [ ] Implement hierarchical permission degradation: parent task permissions are ceiling for child tasks
- [ ] Permissions narrow at each delegation boundary — never widen
- [ ] Add tests
- [ ] Update AGE-2332 to Done

### 10.5 Sub-Delegation Framework (Finding #53, AGE-2333)
- [ ] Implement delegation chain support: DelegationTask can reference parent DelegationTask
- [ ] Implement transitive assignment: delegatee can sub-delegate within attenuated permission boundary
- [ ] Wire into DelegationCoordinator for chain-aware orchestration
- [ ] Add tests for delegation chains (2-deep, 3-deep)
- [ ] Update AGE-2333 to Done

### 10.6 Delegation Memory Layer 4 (Finding #55, AGE-2335)
- [ ] Create migration: `delegation_contexts` table (id, delegation_task_id FK, context_json, propagation_direction enum [down/up/bidirectional], ttl_seconds, created_by_agent_id, timestamps)
- [ ] Create migration: `delegation_learnings` table (id, delegation_graph_id FK, delegatee_profile_id FK, outcome_summary_json, success_patterns_json, failure_patterns_json, aggregated_at, timestamps)
- [ ] Create migration: `delegation_coordination_locks` table (id, resource_type, resource_id, holder_delegation_task_id FK, acquired_at, expires_at, lock_type enum [exclusive/shared], timestamps)
- [ ] Create `DelegationContext` model with $fillable
- [ ] Create `DelegationLearning` model with $fillable
- [ ] Create `DelegationCoordinationLock` model with $fillable
- [ ] Create `app/Services/Delegation/DelegationContextPropagator.php` — propagate context down chains, merge on return
- [ ] Create `app/Services/Delegation/DelegationLearningAggregator.php` — summarize outcomes, feed into capability matching
- [ ] Create `app/Services/Delegation/DelegationCoordinationManager.php` — manage locks/semaphores for concurrent delegations
- [ ] Wire into DelegationCoordinator and DelegationTask
- [ ] Add comprehensive tests for all 3 models and 3 services
- [ ] Update AGE-2335 to Done

---

## Phase 11: Frontend (Findings #40, #57, #61–#62, #72–#74)

**Dependency:** Phase 2 (Pint clean — no conflicting file changes), Phase 5 (ESLint workflow exists to validate).

### 11.1 Tailwind v3 → v4 Migration (Finding #40, AGE-2320)
- [ ] Run `npx @tailwindcss/upgrade`
- [ ] Migrate `tailwind.config.js` to CSS-first `@theme {}` syntax
- [ ] Remove `tailwind.config.js` after migration
- [ ] Fix any breakages across 103 components + 107 pages
- [ ] Verify `npm run build` succeeds
- [ ] Update AGE-2320 to Done

### 11.2 v-html Sanitization (Finding #57, P2 Linear issue)
- [ ] Audit 10 Vue files with v-html usage (Docs/Show.vue:541, Docs/Index.vue:557, etc.)
- [ ] Replace with safe rendering (v-text, DOMPurify sanitization, or component-based rendering) where user content is possible
- [ ] Update P2 Linear issue to Done

### 11.3 Chunk Splitting (Findings #61–#62, P2 Linear issues)
- [ ] Lazy-load Three.js OrbitControls (509 kB) via dynamic `import()` — split from main bundle
- [ ] Split Wizard chunk (726 kB) below 500 kB threshold via route-level code splitting
- [ ] Verify `npm run build` produces no chunks exceeding 500 kB
- [ ] Update P2 Linear issues to Done

### 11.4 ESLint Cleanup (Finding #72, P2 Linear issue)
- [ ] Fix 2 ESLint errors and 17 warnings across resources/js/
- [ ] Verify `npx eslint resources/` reports zero problems
- [ ] Update P2 Linear issue to Done

### 11.5 Vue Script Setup Migration (Finding #73, P2 Linear issue)
- [ ] Migrate 5 Vue components from Options API to `<script setup>` syntax
- [ ] Update P2 Linear issue to Done

### 11.6 Playwright Role-Based Locators (Finding #74, P2 Linear issue)
- [ ] Migrate Playwright tests from CSS selectors to `getByRole`, `getByLabel`, `getByText` locators
- [ ] Update P2 Linear issue to Done

---

## Phase 12: Testing (Findings #41–#45, #71)

**Dependency:** Phase 7 (god classes decomposed — extracted services need factories and tests), Phase 10 (new models need factories).

### 12.1 Create 44 Missing Database Factories (Finding #41, AGE-2321)
- [ ] Create factories for all 44 models without existing factories in database/factories/
- [ ] Include new Layer 4 models: DelegationContext, DelegationLearning, DelegationCoordinationLock, TrustScoreHistory
- [ ] Each factory produces valid model instance with realistic fake data
- [ ] Update AGE-2321 to Done

### 12.2 Smoke Tests for 51 Untested Files (Finding #42, AGE-2322)
- [ ] Create test files for 30 untested services (including DTOs — verify instantiation, key methods)
- [ ] Create test files for 9 untested jobs (verify dispatch, handle executes without exception)
- [ ] Create test files for 12 untested actions (Fortify/Jetstream scaffolding — verify execution path)
- [ ] Update AGE-2322 to Done

### 12.3 Vue Test Expansion & Co-location (Findings #43–#44, AGE-2323, AGE-2324)
- [ ] Move existing Vue tests from `__tests__/` subdirs to co-located files (e.g., `AgentCard.test.ts` next to `AgentCard.vue`)
- [ ] Create new Vue tests using @testing-library/vue with accessibility-first queries (getByRole, getByLabel)
- [ ] Use `userEvent` over `fireEvent` for interactions
- [ ] Total Vue tests must exceed 20
- [ ] Update AGE-2323, AGE-2324 to Done

### 12.4 Pest Architecture Presets (Finding #45, AGE-2325)
- [ ] Configure `arch()->preset()->php()` — baseline PHP architecture rules
- [ ] Configure `arch()->preset()->security()` — no debug functions, no eval, etc.
- [ ] Configure `arch()->preset()->laravel()` — Laravel-specific conventions
- [ ] Add `arch()->preset()->strict()` for additional strictness
- [ ] Verify all presets pass
- [ ] Update AGE-2325 to Done

### 12.5 Fix Failing & Skipped Tests (Finding #71, P2 Linear issue)
- [ ] Fix 11 failing tests
- [ ] Investigate 9 skipped tests — re-enable or document skip reason
- [ ] Verify `php artisan test --parallel` reports zero failures
- [ ] Update P2 Linear issue to Done

---

## Phase 13: Remaining P2 Fixes

**Dependency:** Phase 8 (Action pattern established), Phase 11 (frontend tools updated).

### 13.1 $request->all() → $request->only() (Finding #56, P2 Linear issue)
- [ ] Replace `$request->all()` with `$request->only([...])` in MessengerConnectorController.php at lines 103, 208, 359
- [ ] Update P2 Linear issue to Done

### 13.2 DB::raw() Safety Review (Finding #58, P2 Linear issue)
- [ ] Audit 9 DB::raw() locations — confirm no user input interpolation in any
- [ ] Add inline comments documenting safety assessment for each usage
- [ ] Update P2 Linear issue to Done

### 13.3 Large File Reduction (Finding #59, P2 Linear issue)
- [ ] Review 87 files exceeding 300 lines — many addressed by Phase 7 god class extraction and Phase 8 Action migration
- [ ] Identify remaining files still over threshold post-refactor
- [ ] Extract further where practical
- [ ] Update P2 Linear issue to Done

### 13.4 AbstractBuildAdapter Extraction (Finding #60, P2 Linear issue)
- [ ] Create `app/Support/Interrogation/Adapters/AbstractBuildAdapter.php`
- [ ] Extract 15+ shared build methods with embedded JSON schemas from CodexAdapter and ClaudeAdapter
- [ ] CodexAdapter and ClaudeAdapter extend AbstractBuildAdapter, override only adapter-specific methods
- [ ] Add tests for AbstractBuildAdapter
- [ ] Update P2 Linear issue to Done

### 13.5 Unbounded Query Fixes (Finding #63, P2 Linear issue)
- [ ] Replace `ConnectorAccount::all()` at MessengerHealthController.php:29,161 with scoped queries using `->select([...])` and pagination or limit
- [ ] Update P2 Linear issue to Done

### 13.6 Caching Additions (Finding #64, P2 Linear issue)
- [ ] Add caching for ConnectorAccount lookups (TTL-based, invalidated on model events)
- [ ] Evaluate caching for route/permission checks
- [ ] Update P2 Linear issue to Done

### 13.7 laravel/mcp and laravel/boost Evaluation (Findings #66–#67, P2 Linear issues)
- [ ] Evaluate laravel/mcp: check Packagist availability, assess fit for MCP server needs
- [ ] Evaluate laravel/boost: check availability, assess documentation tooling value
- [ ] Document evaluation results (install if available and beneficial, skip with reasoning if not)
- [ ] Update P2 Linear issues to Done

### 13.8 Application Dockerfile (Finding #68, P2 Linear issue)
- [ ] Create `Dockerfile` in repository root for containerized deployment
- [ ] Multi-stage build: composer install → npm build → production PHP 8.3/8.4 image
- [ ] Include Horizon, scheduler, and web server entrypoints
- [ ] Update P2 Linear issue to Done

### 13.9 Pennant Feature Flag Cleanup (Finding #69, P2 Linear issue)
- [ ] Audit config/pennant.php — define active feature flags or remove unused Pennant configuration
- [ ] Update P2 Linear issue to Done

### 13.10 supervisor-tunnel Balance Fix (Finding #70, P2 Linear issue)
- [ ] Change `'balance' => 'false'` to `'balance' => false` (boolean) in config/horizon.php supervisor-tunnel
- [ ] Update P2 Linear issue to Done

### 13.11 laravel/ai Tracking (Finding #65, P2 Linear issue)
- [ ] Confirm laravel/ai is still not on Packagist — document as informational
- [ ] Existing Guzzle fallback adapters (AnthropicAdapter, OpenAIAdapter) are sufficient
- [ ] Update P2 Linear issue to Done

---

## Phase 14: Final Verification & Report Generation

**Dependency:** All previous phases complete.

### 14.1 Full Suite Verification
- [ ] `php artisan test --parallel` — zero failures
- [ ] `./vendor/bin/phpstan analyse` — zero errors at level 5
- [ ] `./vendor/bin/pint --test` — zero violations
- [ ] `npx eslint resources/` — zero problems
- [ ] `npx vitest run` — all passing
- [ ] `npm run build` — no chunks over 500 kB
- [ ] Pest architecture presets: `arch()->preset()->php()`, `->security()`, `->laravel()` all pass
- [ ] Grep `guarded.*\[\]` across app/Models/ — zero results
- [ ] Grep `declare(strict_types=1)` — 910/910 PHP files
- [ ] Verify all 8 CI workflow files exist in .github/workflows/

### 14.2 Linear Issue Final Reconciliation
- [ ] Verify 54 existing Linear issues set to Done
- [ ] Verify AGE-2293 closed as Won't Fix with comment
- [ ] Verify 19 P2 Linear issues created and set to Done

### 14.3 Generate REFACTOR_REPORT.md
- [ ] Create `tasks/REFACTOR_REPORT.md` with:
  - Executive summary
  - Before/after metrics table (PHPStan errors, Pint violations, ESLint problems, test results, strict_types compliance, factory coverage, guarded models, CI workflow count, chunk sizes, Horizon supervisor config)
  - Per-finding change log (all 74 findings with file changes, verification result)
  - New files created (services, actions, models, migrations, tests, CI workflows, Dockerfile)
  - Files deleted/renamed
  - Architecture changes summary (delegation Layer 4, sub-delegation, permission attenuation)
  - Risk items encountered and resolved
  - Remaining technical debt (if any)

## Sections

- Phase 1: Linear Issue Management & Baseline Capture
- Phase 2: Foundation — Formatting, Strict Types, Pint (Findings #29, #30)
- Phase 3: Security P0 Fixes (Findings #1, #10, #11, #12)
- Phase 4: Security P1 Fixes (Findings #15–#23)
- Phase 5: CI/CD Workflows (Findings #2–#9)
- Phase 6: PHPStan Zero Errors (Finding #14)
- Phase 7: God Class Refactoring (Findings #24–#27)
- Phase 8: Action Pattern Migration (Finding #28)
- Phase 9: Observability & Infrastructure (Findings #10, #31–#39, #46–#49)
- Phase 10: Architecture — Delegation Framework (Findings #50–#55)
- Phase 11: Frontend (Findings #40, #57, #61–#62, #72–#74)
- Phase 12: Testing (Findings #41–#45, #71)
- Phase 13: Remaining P2 Fixes
- Phase 14: Final Verification & Report Generation


## Risks

- strict_types batch addition may surface hundreds of runtime TypeErrors that compound PHPStan error count beyond the baseline 3,413 — mitigation: fix TypeErrors as part of Phase 6 PHPStan resolution, not separately
- Mass $fillable migration on 57 models risks missing fields used by obscure call sites (queue jobs, event listeners, console commands) — mitigation: grep every ::create, ->fill, ->update, ->forceFill call site per model; run full test suite after each batch of ~7 models
- God class extraction (Phase 7) may break Inertia.js page props if controller method signatures or return shapes change — mitigation: verify frontend pages render correctly after each controller decomposition
- Tailwind v3→v4 migration may break visual styles across 210 Vue files — mitigation: run npx @tailwindcss/upgrade first, then visual spot-check key pages; npm run build must succeed before proceeding
- Action pattern migration across 77 controllers is high-volume refactoring with risk of subtle behavioral changes — mitigation: run full test suite after each controller batch; focus on highest-concentration controllers first
- PHPStan 3,413→0 resolution may require changing method signatures that are part of Laravel contracts or package interfaces — mitigation: use PHPDoc annotations (@phpstan-param, @phpstan-return) for framework interface compliance rather than changing signatures
- Delegation memory Layer 4 introduces 3 new tables and 3 services with complex coordination semantics (locks, propagation) — mitigation: implement and test each component independently before wiring into DelegationCoordinator
- OpenTelemetry PHP ecosystem maturity is variable — specific package availability and Laravel integration may require fallback to manual span creation — mitigation: use console/log exporter only, defer OTLP to future phase
- laravel/mcp and laravel/boost packages may not be available on Packagist — mitigation: evaluate availability first, document findings, skip installation if unavailable
- Breaking API change (soul_json removal) may affect any external consumers of the DelegateeProfile API endpoint — mitigation: user explicitly approved immediate breaking change; no deprecation period
- Husky pre-commit hooks may slow developer workflow if Pint/PHPStan run on full codebase — mitigation: lint-staged runs tools only on staged files
- 11 failing tests may have root causes in production code bugs rather than test issues — mitigation: investigate each failure individually; fix production code if bug is confirmed, fix test if assertion is wrong


## Assumptions

- All 55 existing Linear issues (AGE-2281 through AGE-2335) are accessible and updatable via the Linear API with current credentials
- The Linear project uses team name 'Agent Orchestration' and has a 'Discovery Audit' label available for new P2 issues
- PostgreSQL test database (pgsql_testing) is available and configured for running the full test suite after each batch of changes
- Neo4j 5.x Community edition is running locally via docker-compose for memory formation pipeline testing
- Redis is available on DB 0/1/2 for Horizon, cache, and memory working buffer testing
- PHP 8.3+ and Node 22.x are available in the development environment for all tool commands
- The existing 43 database factories produce valid model instances and are not broken by the $fillable migration
- PHPStan level 5 with paths app/, config/, database/, routes/ is the agreed configuration — no additional paths or higher levels
- Sentry DSN will be configured at deploy time — only placeholder SENTRY_DSN= in .env.example is required
- OpenTelemetry console/log exporter is the agreed scope — no external collector, no OTLP endpoint configuration beyond placeholder
- The 77 controllers identified for Action pattern migration is the complete list — no additional controllers have been added since the audit
- laravel/ai is confirmed unavailable on Packagist as of 2026-03-08 — provider adapters continue using direct Guzzle HTTP clients
- pgvector extension availability is not guaranteed — memory retrieval must gracefully degrade to BM25 keyword search
- The existing Pest test configuration supports architecture presets (php, security, laravel, strict) without additional package installation
- config/delegation.php exists and is the appropriate location for delegation-related configuration additions
- Tailwind v4 CSS-first migration tool (npx @tailwindcss/upgrade) supports the project's current Tailwind v3 configuration

