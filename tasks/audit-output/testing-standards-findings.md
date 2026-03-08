# Phase 5-6: Testing Coverage Baseline & Coding Standards Compliance

> Generated: 2026-03-08 | Session 6, Task 6

---

## 1. Coverage Metrics Baseline

| Metric | Current | Minimum | Target | Status |
|---|---|---|---|---|
| Line coverage | **N/A — OOM** | 80% | 90%+ | BLOCKED |
| Branch coverage | **N/A — OOM** | 75% | 85%+ | BLOCKED |
| Mutation score | **Not run** | 60% | 80%+ | BLOCKED |
| Type coverage (PHPStan L5) | **3,413 errors** | 90% pass | 100% pass | FAIL |

**Coverage blocker**: PCOV coverage report generation failed with OOM at ~512 MB during route file instrumentation. Fix: increase `memory_limit` or set `pcov.exclude` for vendor/routes. Coverage percentages cannot be extracted until this is resolved.

**Test suite results**: 3,959 passed, 11 failed, 9 skipped (14,382 assertions) in 270.36s.

---

## 2. Zero-Coverage Files

### Untested Services (30 files)

| File | Notes |
|---|---|
| `app/Services/Cost/WorkflowBudgetEnforcementResult.php` | DTO / Result object |
| `app/Services/Runtime/Adapters/AgentApiToolAdapter.php` | Runtime adapter |
| `app/Services/Runtime/Adapters/McpToolAdapter.php` | Runtime adapter |
| `app/Services/Runtime/Adapters/RuntimeToolAdapter.php` | Runtime adapter |
| `app/Services/Runtime/Adapters/DiscoveryToolAdapter.php` | Runtime adapter |
| `app/Services/Agent/WebhookDeliveryService.php` | Webhook delivery |
| `app/Services/Agent/ConfigSchemaService.php` | Config schema |
| `app/Services/Observability/ObservabilitySnapshotService.php` | Observability |
| `app/Services/Tunnel/TunnelSyncService.php` | Tunnel feature |
| `app/Services/Messenger/AgentRouter.php` | Messenger routing |
| `app/Services/Messenger/RegistrationResult.php` | DTO |
| `app/Services/Messenger/ChannelPolicyGuard.php` | Policy guard |
| `app/Services/Messenger/ChannelPolicyResult.php` | DTO |
| `app/Services/Reliability/WeightedReliabilityResult.php` | DTO |
| `app/Services/Reliability/GateEvaluationResult.php` | DTO |
| `app/Services/Reliability/FailureTaxonomyMapper.php` | Mapper |
| `app/Services/Reliability/RunClassificationResult.php` | DTO |
| `app/Services/Reliability/WorkflowGovernanceSnapshotService.php` | Governance |
| `app/Services/Reliability/AssistedSlaExpiryReclassifier.php` | SLA reclassifier |
| `app/Services/Escalation/WorkflowGovernanceService.php` | Governance |
| `app/Services/Telemetry/ProjectionRebuildStartResult.php` | DTO |
| `app/Services/Telemetry/TerminalizationGapProjector.php` | Projector |
| `app/Services/Telemetry/ProjectionBuildActivationResult.php` | DTO |
| `app/Services/Telemetry/ProjectionBuildManager.php` | Build manager |
| `app/Services/Telemetry/VersionedSchemaRegistry.php` | Schema registry |
| `app/Services/Telemetry/ActiveProjectionBuildStateService.php` | State service |
| `app/Services/Replay/ReplayParityService.php` | Replay parity |
| `app/Services/Replay/ReplayParityResult.php` | DTO |
| `app/Services/Billing/BillingUsageService.php` | Billing |
| `app/Services/Credentials/OAuthTokenService.php` | OAuth tokens |

### Untested Jobs (9 files)

| File |
|---|
| `app/Jobs/Documentation/PersistDocsTelemetryEventJob.php` |
| `app/Jobs/RepoAnalysis/PruneRepoAnalysisArtifactsJob.php` |
| `app/Jobs/Memory/MemoryPruneJob.php` |
| `app/Jobs/Memory/MemoryConsolidationJob.php` |
| `app/Jobs/Memory/RuntimeMemoryFormationJob.php` |
| `app/Jobs/Org/OrgEscalationTimeoutJob.php` |
| `app/Jobs/Agent/DeliverWebhookJob.php` |
| `app/Jobs/Messenger/CompactionJob.php` |
| `app/Jobs/ReindexDocumentationSearchJob.php` |

### Untested Actions (12 files — all Fortify/Jetstream scaffolding)

| File |
|---|
| `app/Actions/Fortify/UpdateUserProfileInformation.php` |
| `app/Actions/Fortify/PasswordValidationRules.php` |
| `app/Actions/Fortify/UpdateUserPassword.php` |
| `app/Actions/Fortify/ResetUserPassword.php` |
| `app/Actions/Fortify/CreateNewUser.php` |
| `app/Actions/Jetstream/DeleteUser.php` |
| `app/Actions/Jetstream/InviteTeamMember.php` |
| `app/Actions/Jetstream/RemoveTeamMember.php` |
| `app/Actions/Jetstream/DeleteTeam.php` |
| `app/Actions/Jetstream/AddTeamMember.php` |
| `app/Actions/Jetstream/UpdateTeamName.php` |
| `app/Actions/Jetstream/CreateTeam.php` |

**Total untested files**: 51 across Services (30), Jobs (9), and Actions (12).

---

## 3. Missing Factories

**44 models** lack database factories (out of 80 total models, 37 factories exist):

| Missing Factory |
|---|
| AccountLinkToken |
| AgentAuditLog |
| AgentBackupSetting |
| AgentConnectorCredentialEvent |
| AgentConnectorWebhookEvent |
| AgentFeatureSetting |
| AgentMaintenanceCheckpoint |
| AgentRunEvent |
| AgentSystemState |
| ApiDocArtifact |
| ChatAttachment |
| ConnectedProvider |
| CredentialVault |
| DelegateeCapabilityPivot |
| DelegationTaskDependency |
| DocumentationEntry |
| DocumentationFragment |
| DocumentationLink |
| EscalationIncident |
| InterrogationBuildTask |
| InterrogationEvent |
| InterrogationSetting |
| InterrogationTechStack |
| Membership |
| MemoryConsolidationLog |
| MemoryFormationFailure |
| MemorySetting |
| MessengerDeadLetter |
| MessengerEventDeduplication |
| NlOrgParseAttempt |
| NlParseAttempt |
| PendingConfirmation |
| RepoAnalysisArtifact |
| RepoAnalysisEvent |
| RepoAnalysisReport |
| RepoAnalysisSession |
| RepoAnalysisTask |
| RunClassification |
| SchedulerHeartbeat |
| TeamInvitation |
| TunnelSetting |
| UserChatPreference |
| UserNotificationSetting |
| WorkflowGateTransition |

---

## 4. Test Co-location Compliance — P1 FINDING

**Engineering rules v2.0 require**: `ComponentName.test.ts` co-located next to `ComponentName.vue`.

**Current state**: All 4 Vue component spec files are in `__tests__/` subdirectories, violating co-location:

| Current Location | Expected Location |
|---|---|
| `resources/js/Components/__tests__/HelpHint.spec.ts` | `resources/js/Components/HelpHint.test.ts` |
| `resources/js/Pages/Tools/CodeAnalysis/__tests__/eventStream.spec.ts` | `resources/js/Pages/Tools/CodeAnalysis/eventStream.test.ts` |
| `resources/js/Pages/Agent/Monitor/__tests__/freshness.spec.ts` | `resources/js/Pages/Agent/Monitor/freshness.test.ts` |
| `resources/js/Components/__tests__/StatusCard.spec.ts` | `resources/js/Components/StatusCard.test.ts` |

**Additionally**: Only 4 Vue component tests exist for the entire frontend. This is a separate P1 finding (gross under-coverage of Vue layer).

---

## 5. Test Quality Assessment

### Methodology
Sampled 6 test files across Feature, Unit, and Integration layers.

### Findings

| Dimension | Assessment | Score |
|---|---|---|
| **Assertion style** | Predominantly behavior-based; tests verify observable state changes and outcomes, not internal call counts | Strong |
| **RefreshDatabase usage** | Consistent and appropriate — Feature tests use it, Unit tests do not | Strong |
| **Test DB engine** | PostgreSQL (`pgsql_testing`) — matches production engine | Compliant |
| **Naming conventions** | Descriptive test names reflecting scenario + expected outcome | Strong |
| **AAA pattern** | Clear Arrange-Act-Assert structure across all sampled files | Strong |
| **Mocking approach** | Mockery used judiciously for external dependencies; assertions still validate output, not mock interactions | Acceptable |

### Minor Concerns
- Some count-based assertions use `assertGreaterThanOrEqual(0, ...)` instead of exact values
- Filesystem coupling in integration tests (acceptable for scope)
- No Pest architecture testing presets detected (`->preset('php')`, `->preset('security')`, `->preset('laravel')`)

**Overall test quality: 7.5/10** — Good behavior-based testing with appropriate database handling.

---

## 6. Coding Standards Compliance

### 6.1 `declare(strict_types=1)` — P1

**504 PHP files** are missing `declare(strict_types=1)`.

Engineering rules v2.0 require strict types in all PHP files.

### 6.2 PSR-12 / Pint — P1

| Metric | Value |
|---|---|
| Files with violations | 138 |
| Total fixer applications needed | 284 |

Most common violations: `unary_operator_spaces`, `not_operator_with_successor_space`, `concat_space`, `no_unused_imports`, `single_line_empty_body`, `braces_position`, `ordered_imports`, `new_with_parentheses`.

### 6.3 PHPStan Level 5 — P0

**3,413 errors** at level 5. This represents a significant type-safety gap against the 90% minimum / 100% target.

### 6.4 ESLint — P2

**19 problems** (2 errors, 17 warnings):
- 2 errors: `no-useless-escape` in `agentRunEventFormatting.js`
- 17 warnings: `no-unused-vars` (unused imports in JS files)

### 6.5 Vue `<script setup>` Compliance

**5 Vue components** missing `<script setup>`:
- `resources/js/Components/ApplicationLogo.vue`
- `resources/js/Components/ApplicationMark.vue`
- `resources/js/Components/AuthenticationCard.vue`
- `resources/js/Components/SectionBorder.vue`
- `resources/js/Components/SectionTitle.vue`

All are Jetstream scaffolding components. All Pages use `<script setup>` correctly.

### 6.6 TODO/FIXME Count

**1 TODO/FIXME** found in codebase (from `todo-fixme-count.txt`).

---

## 7. Pre-commit Hooks & CI Tooling

### Pre-commit Hooks

| Check | Status |
|---|---|
| Husky directory | **Not present** |
| Custom `.githooks/` | **Present** — contains `pre-commit` |
| `lint-staged` in package.json | **Not configured** (0 references) |
| `commitlint` in package.json | **Not configured** (0 references) |

**Pre-commit hook content**: The existing `.githooks/pre-commit` only runs docs sync (`scripts/docs/sync.sh --mode=commit`). It does **not** run any quality checks (Pint, PHPStan, ESLint, tests).

**Finding (P1)**: No quality-gate pre-commit hooks. Engineering rules v2.0 require Husky + lint-staged for pre-commit checks.

### Config Centralization

- No legacy `app/Http/Kernel.php` — configuration is in `bootstrap/app.php` (Laravel 12 compliant).

---

## 8. Playwright Configuration

**File**: `playwright.config.ts`

| Check | Status | Compliant? |
|---|---|---|
| `trace: 'on-first-retry'` | Present | Yes |
| `webServer` → `php artisan serve` | Present | Yes |
| Role-based locators | **NOT used** — tests use CSS selectors (`page.locator('text=...', '[class*="..."]'`) | No |
| `data-testid` attributes | Partially used alongside CSS | Partial |

**Finding (P2)**: Playwright tests use CSS selectors and text-matching rather than role-based locators (`getByRole`, `getByLabel`). Engineering rules recommend role-based locators for resilience.

---

## 9. Summary of Findings

| # | Category | Severity | Finding |
|---|---|---|---|
| 1 | Testing | **BLOCKED** | Coverage metrics unavailable — PCOV OOM during report generation |
| 2 | Testing | P0 | PHPStan level 5: 3,413 errors (type coverage far below 90% minimum) |
| 3 | Testing | P1 | 44 missing database factories (out of 80 models) |
| 4 | Testing | P1 | 51 untested files across Services (30), Jobs (9), Actions (12) |
| 5 | Testing | P1 | Vue test co-location violation — all 4 tests in `__tests__/` subdirs |
| 6 | Testing | P1 | Only 4 Vue component tests for entire frontend |
| 7 | Standards | P1 | 504 PHP files missing `declare(strict_types=1)` |
| 8 | Standards | P1 | 138 files with Pint PSR-12 violations (284 fixes needed) |
| 9 | Standards | P1 | No quality-gate pre-commit hooks (no Husky/lint-staged/commitlint) |
| 10 | Standards | P1 | No Pest architecture testing presets configured |
| 11 | Standards | P2 | 19 ESLint problems (2 errors, 17 warnings) |
| 12 | Standards | P2 | 5 Vue components missing `<script setup>` (Jetstream scaffolding) |
| 13 | Standards | P2 | Playwright tests use CSS selectors instead of role-based locators |
| 14 | Testing | P2 | 11 failing tests, 9 skipped tests in suite |

---

## Verification Checklist

- [x] All 4 coverage metric rows populated (current vs minimum vs target) — coverage blocked by OOM, documented
- [x] Missing factories enumerated — **44 missing** (expected ~41, actual 44 due to recent model additions)
- [x] Test co-location violation documented as P1
- [x] strict_types missing count documented — **504 files**
- [x] PHPStan error count documented — **3,413 errors**
- [x] Pre-commit hook presence/absence documented — docs-sync only, no quality gates
