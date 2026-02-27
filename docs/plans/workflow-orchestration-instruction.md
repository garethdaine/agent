# Implementation Plan

Derived from discovery session 5.

# Workflow-Orchestration Compliance Layer Implementation Plan

## Executive Summary
This plan implements a unified orchestration compliance layer that enforces workflow standards across both `ExecuteAgentRunJob` and `ExecuteInterrogationBuildJob` execution paths. The system introduces policy-based enforcement for plan-first behavior, verification gates, correction-driven lessons capture, and elegance checks through configurable, progressive policy enforcement.

## Phase 1: Foundation Services and Enums

### 1.1 Create TaskCategory Enum
**File:** `app/Support/Compliance/TaskCategory.php`

Define the fixed enum with custom escape hatch:
- `FEATURE`, `BUGFIX`, `REFACTOR`, `DOCUMENTATION`, `TEST`, `INFRASTRUCTURE`, `CUSTOM`
- Static method `fromString(string $value): self` to normalize input, mapping unknown values to `CUSTOM`
- Method `isCustom(): bool` for detecting ad-hoc categories
- Method `defaultComplexity(): string` returning `non_trivial` for `CUSTOM` and category-appropriate defaults for others

### 1.2 Create ComplexityClassifier Service
**File:** `app/Support/Compliance/ComplexityClassifier.php`

Implement hybrid heuristic classification:
- Constructor injects config values from `config/agent.php`
- Method `classify(array $context): ComplexityResult` accepting task/run context
- Heuristics implemented as private methods:
  - `exceedsFileThreshold(int $fileCount): bool` (default: >3 files)
  - `exceedsLocThreshold(int $estimatedLoc): bool` (default: >50 LOC)
  - `exceedsDirectoryThreshold(int $directoryCount): bool` (default: >2 directories)
  - `touchesSchema(array $files): bool` (detects migration files)
  - `hasSemanticKeywords(string $content): bool` (exact match + simple stemming)
- Override handling via `complexity_override` field (`force_simple`, `force_non_trivial`, `auto`)
- Returns `ComplexityResult` DTO with `classification`, `triggers`, `override_applied`

### 1.3 Create TaskCategoryResolver Service
**File:** `app/Support/Compliance/TaskCategoryResolver.php`

Map tasks to categories:
- Method `resolve(array $context): TaskCategory`
- Inspect task metadata, title, description for category signals
- Support explicit `task_category` field in metadata
- Fall back to `TaskCategory::CUSTOM` for unrecognized categories
- Method `isRegisteredCustomCategory(string $value, ?int $tenantId): bool` for tenant-specific custom categories

### 1.4 Create ComplianceFlagResolver Service
**File:** `app/Support/Compliance/ComplianceFlagResolver.php`

Hierarchical flag resolution:
- Constructor injects `FeatureFlagManager` and config
- Method `resolve(string $key, ?int $tenantId = null): mixed`
- Load global defaults from `config/agent.php`
- Load tenant overrides from database (new `agent_compliance_settings` table)
- Enforce stricter-only constraint: compare numeric thresholds (tenant must be ≤ global), boolean gates (tenant can enable if global disabled, but cannot disable if global enabled)
- Method `resolveAll(?int $tenantId = null): array` for bulk resolution
- Method `getEffectiveMode(?int $tenantId = null): string` returning `advisory`, `warning`, or `enforced`

### 1.5 Create RunnerCapabilityChecker Service
**File:** `app/Support/Compliance/RunnerCapabilityChecker.php`

Detect runner capabilities:
- Method `check(string $runnerType): RunnerCapabilities` DTO
- Capabilities to detect: `supports_subagent`, `supports_structured_output`, `supports_tool_use`
- Method `hasCapability(string $runnerType, string $capability): bool`
- Method `recordDowngrade(string $capability, string $reason, array &$metadata): void`

## Phase 2: Gate Implementations

### 2.1 Create Base Gate Interface and Abstract Class
**File:** `app/Support/Compliance/Contracts/Gate.php`

```php
interface Gate {
    public function evaluate(GateContext $context): GateResult;
    public function getName(): string;
    public function isRequired(GateContext $context): bool;
}
```

**File:** `app/Support/Compliance/Gates/AbstractGate.php`

Abstract base with shared logic:
- Protected method `shouldSkip(GateContext $context): ?GateResult` for mode-based skipping
- Protected method `recordOutcome(GateResult $result, GateContext $context): void`

### 2.2 Create GateContext and GateResult DTOs
**File:** `app/Support/Compliance/GateContext.php`

Context passed to gates:
- `complexity`: string (simple/non_trivial)
- `category`: TaskCategory
- `metadata`: array
- `runnerType`: string
- `mode`: string (advisory/warning/enforced)
- `tenantId`: ?int

**File:** `app/Support/Compliance/GateResult.php`

Result from gate evaluation:
- `status`: string (passed/failed/skipped/pending)
- `reason`: ?string
- `evidence`: ?array
- `remediation`: ?string

### 2.3 Implement PlanGate
**File:** `app/Support/Compliance/Gates/PlanGate.php`

Plan evidence enforcement:
- Required when `complexity === 'non_trivial'`
- Method `evaluate()` checks for plan evidence in multiple formats:
  - JSON in metadata with `scope`, `steps`, `assumptions`
  - Markdown file in `tasks/` directory
  - Commit/file change with plan reference
- Normalizes all formats to common schema: `plan_format`, `plan_location`, `plan_hash`, `captured_at`
- Returns `GateResult` with `compliance_block_reason: 'plan_evidence_missing'` on failure

### 2.4 Implement VerificationGate
**File:** `app/Support/Compliance/Gates/VerificationGate.php`

Tiered verification enforcement by category:
- Define requirements matrix as class constant
- Method `getRequirements(TaskCategory $category): array` returns `[automated_check, ai_critic, human_approval]` requirements
- Method `evaluate()` checks each required tier:
  - `automated_check`: test execution output, static analysis results
  - `ai_critic`: LLM review output in metadata
  - `human_approval`: approval flag in metadata (for bugfix category)
- Returns `GateResult` with `compliance_block_reason: 'verification_incomplete'` on failure
- Populates `remediation` with specific missing evidence types

### 2.5 Implement EleganceGate
**File:** `app/Support/Compliance/Gates/EleganceGate.php`

Code quality checkpoint:
- Only required for `complexity === 'non_trivial'`
- Method `runStaticAnalysis(array $files): array` executes:
  - PHPStan at configured level (default 6)
  - PHP_CodeSniffer with configured ruleset (default PSR-12)
- Method `runLlmReview(array $changes): array` using structured JSON prompt
- LLM review output schema: `score` (0-100), `violations[]` with `rule`, `severity`, `location`, `suggestion`
- Aggregates results into `elegance_findings` JSON
- Returns `GateResult` with `compliance_block_reason: 'elegance_review_pending'` on failure

### 2.6 Implement BugFixGate
**File:** `app/Support/Compliance/Gates/BugFixGate.php`

Bug-fix evidence chain enforcement:
- Only active when `category === TaskCategory::BUGFIX` or `autonomous_bugfix_mode === true`
- Validates full evidence chain:
  - `failing_test`: test file path or test output showing failure
  - `error_evidence`: logs, stack traces, error reproduction
  - `root_cause_documentation`: structured explanation
  - `fix_verification`: passing test output
- Returns `GateResult` with `compliance_block_reason: 'bugfix_evidence_incomplete'` on failure
- Populates `remediation` listing which evidence items are missing

## Phase 3: Core Orchestration Services

### 3.1 Create PolicyEvaluator Service
**File:** `app/Support/Compliance/PolicyEvaluator.php`

Main orchestration service:
- Constructor injects all gates, `ComplexityClassifier`, `TaskCategoryResolver`, `ComplianceFlagResolver`
- Method `evaluateBeforeStart(array $context): PolicyResult` (sync):
  - Run complexity classification
  - Resolve task category
  - Store initial compliance metadata
  - Return early with mode/requirements
- Method `evaluateBeforeComplete(array $context): PolicyResult` (async-compatible):
  - Evaluate PlanGate
  - Evaluate VerificationGate
  - Evaluate EleganceGate (if non-trivial)
  - Evaluate BugFixGate (if bugfix category)
  - Aggregate results
- Method `evaluateBeforeTaskStart(InterrogationBuildTask $task, array $context): PolicyResult`
- Method `evaluateBeforeTaskComplete(InterrogationBuildTask $task, array $context): PolicyResult`
- Returns `PolicyResult` DTO with `canProceed`, `blockReason`, `gateOutcomes`, `metadata`

### 3.2 Create LessonsManager Service
**File:** `app/Support/Compliance/LessonsManager.php`

Lesson capture and retrieval:
- Constant `LESSONS_PATH = 'tasks/lessons.md'`
- Method `capture(LessonTrigger $trigger, array $context): void`:
  - `explicit_rejection`: user marks output as rejected
  - `correction_message`: follow-up with correction keywords
  - `manual_edit`: user modifies agent-generated files
  - `rerun_request`: explicit re-execution request
  - Appends structured entry to lessons file with date, context, lesson, category, source_type
- Method `retrieveRelevant(TaskCategory $category, ?int $tokenBudget = null): array`:
  - Default budget from config (2000 tokens)
  - Priority: recent lessons first, then category-matched
  - Filter older lessons by category match
  - Return array of lesson entries within budget
- Method `injectIntoContext(array &$context, TaskCategory $category): void`

### 3.3 Create ReplanSignalDetector Service
**File:** `app/Support/Compliance/ReplanSignalDetector.php`

Drift/failure detection:
- Method `detect(array $executionContext): ReplanSignal`:
  - Check consecutive failures (configurable N threshold)
  - Check scope drift (files modified outside original scope)
  - Check time threshold exceeded without progress
  - Check explicit blocker language patterns
- Returns `ReplanSignal` DTO with `shouldReplan`, `isAdvisory`, `triggers`
- Single trigger → `isAdvisory = true` (log + event only)
- 2+ concurrent triggers → `isAdvisory = false` (mandatory re-plan)

### 3.4 Create ComplianceMetadataWriter Service
**File:** `app/Support/Compliance/ComplianceMetadataWriter.php`

Metadata persistence:
- Method `write(Model $record, array $complianceData): void` supporting `AgentJobRun`, `InterrogationBuildTask`, `InterrogationSession`
- Merges compliance data into existing `metadata_json`
- Normalizes key names per contract
- Method `read(Model $record): array` extracts compliance metadata

### 3.5 Create ComplianceEventEmitter Service
**File:** `app/Support/Compliance/ComplianceEventEmitter.php`

Event emission for observability:
- Method `emit(string $eventType, array $payload): void`
- Event types: `gate_evaluated`, `gate_passed`, `gate_failed`, `policy_evaluated`, `compliance_blocked`, `lesson_captured`, `replan_triggered`
- Integrates with existing event infrastructure (`RunEventWriter`, `InterrogationEventWriter`)

### 3.6 Create ComplianceTelemetryCollector Service
**File:** `app/Support/Compliance/ComplianceTelemetryCollector.php`

Rollout metrics collection:
- Method `record(string $metric, array $dimensions, $value): void`
- Metrics: `gate_evaluation_count`, `would_block_count`, `actual_block_count`, `time_to_resolution`, `override_count`, `false_positive_count`, `lesson_utilization_count`, `replan_trigger_count`, `elegance_score`
- Method `getMetrics(string $period = 'day'): array`
- Method `getRolloutHealth(): array` returning pass rates and block rates per gate

## Phase 4: Integration Points

### 4.1 Integrate with ExecuteAgentRunJob
**File:** `app/Jobs/ExecuteAgentRunJob.php`

Modify handle method:
- After `RuntimeValidation::validate()` succeeds, call `PolicyEvaluator::evaluateBeforeStart()`
- Store compliance metadata via `ComplianceMetadataWriter`
- Before `finalizeTerminal()` with success status, call `PolicyEvaluator::evaluateBeforeComplete()`
- If gate fails and mode is `enforced`:
  - Use existing `STATUS_BLOCKED` constant (add if not present, or use `STATUS_FAILED` with specific error_code)
  - Set `compliance_block_reason` in metadata
  - Emit `compliance_blocked` event
- If mode is `advisory` or `warning`, log only and continue

### 4.2 Integrate with ExecuteInterrogationBuildJob
**File:** `app/Jobs/ExecuteInterrogationBuildJob.php`

Modify task lifecycle:
- Before `$runFactory->create()`, call `PolicyEvaluator::evaluateBeforeTaskStart()`
- In `finalizeTaskFromRun()`, before setting `STATUS_COMPLETED`, call `PolicyEvaluator::evaluateBeforeTaskComplete()`
- If gate fails:
  - Set task status to `InterrogationBuildTask::STATUS_BLOCKED`
  - Set `compliance_block_reason` in task metadata
  - Set build status to `paused` with `pause_reason: compliance`

### 4.3 Integrate with BuildTaskRunFactory
**File:** `app/Support/Interrogation/BuildTaskRunFactory.php`

Inject compliance metadata on run creation:
- Add `ComplianceMetadataWriter` dependency
- In `create()` method, after AgentJobRun creation:
  - Call `PolicyEvaluator::evaluateBeforeStart()` with task context
  - Write initial compliance metadata to run's `metadata_json`
  - Include `workflow_policy_version`, `complexity_classification`, `task_category`

### 4.4 Integrate with SystemPromptResolver
**File:** `app/Support/Interrogation/SystemPromptResolver.php`

Add lesson injection:
- Add `LessonsManager` dependency
- In `resolveForPhase()`, after building session context:
  - Call `LessonsManager::retrieveRelevant()` with current task category
  - Append lessons to context if budget allows
  - Include in prompt output

### 4.5 Integrate ComplianceFlagResolver with FeatureFlagManager
**File:** `app/Support/Agent/FeatureFlagManager.php`

Add compliance flag definitions:
- Add constants for compliance flags: `COMPLIANCE_ENABLED`, `COMPLIANCE_MODE`, `COMPLIANCE_PLAN_GATE`, etc.
- Register in `DEFINITIONS` array with labels and descriptions
- `ComplianceFlagResolver` queries this manager for global state

## Phase 5: Configuration and Database

### 5.1 Add Compliance Configuration
**File:** `config/agent.php`

Add compliance section:
```php
'compliance' => [
    'enabled' => env('COMPLIANCE_ENABLED', true),
    'mode' => env('COMPLIANCE_MODE', 'advisory'),
    'gates' => [
        'plan' => env('COMPLIANCE_PLAN_GATE', true),
        'verification' => env('COMPLIANCE_VERIFICATION_GATE', true),
        'elegance' => env('COMPLIANCE_ELEGANCE_GATE', true),
        'bugfix' => env('COMPLIANCE_BUGFIX_GATE', true),
    ],
    'lessons' => [
        'enabled' => env('COMPLIANCE_LESSONS', true),
        'token_budget' => env('COMPLIANCE_LESSON_TOKEN_BUDGET', 2000),
    ],
    'classifier' => [
        'file_threshold' => env('COMPLIANCE_FILE_THRESHOLD', 3),
        'loc_threshold' => env('COMPLIANCE_LOC_THRESHOLD', 50),
        'directory_threshold' => env('COMPLIANCE_DIRECTORY_THRESHOLD', 2),
        'semantic_keywords' => ['migration', 'refactor', 'security', 'architecture', 'breaking'],
    ],
    'elegance' => [
        'phpstan_level' => env('COMPLIANCE_PHPSTAN_LEVEL', 6),
        'phpcs_ruleset' => env('COMPLIANCE_PHPCS_RULESET', 'PSR12'),
        'custom_rules' => [],
    ],
    'rollback' => [
        'strict_to_warning_threshold' => 0.20,
        'warning_to_advisory_threshold' => 0.30,
    ],
    'alerting' => [
        'dashboard' => true,
        'slack' => env('COMPLIANCE_ALERT_SLACK', false),
        'email' => env('COMPLIANCE_ALERT_EMAIL', false),
    ],
]
```

### 5.2 Create Tenant Compliance Settings Migration
**File:** `database/migrations/xxxx_create_agent_compliance_settings_table.php`

Schema:
- `id`: bigIncrements
- `tenant_id`: nullable unsignedBigInteger (null = global)
- `key`: string, indexed
- `value`: json
- `updated_by_user_id`: nullable unsignedBigInteger
- `created_at`, `updated_at`
- Unique constraint on `[tenant_id, key]`

### 5.3 Create AgentComplianceSetting Model
**File:** `app/Models/AgentComplianceSetting.php`

Standard Eloquent model:
- Guarded array, JSON cast for `value`
- Scopes: `forTenant(?int $tenantId)`, `global()`
- Relationship: `belongsTo(User::class, 'updated_by_user_id')`

## Phase 6: API Contract Updates

### 6.1 Create ComplianceSummaryResource
**File:** `app/Http/Resources/ComplianceSummaryResource.php`

Transform compliance metadata for API responses:
```php
public function toArray($request): array {
    return [
        'policy_version' => $this->resource['workflow_policy_version'] ?? '1.0',
        'classification' => $this->resource['complexity_classification'] ?? null,
        'category' => $this->resource['task_category'] ?? null,
        'mode' => $this->resource['compliance_mode'] ?? 'advisory',
        'gates' => $this->transformGates(),
        'overall_status' => $this->determineOverallStatus(),
        'remediation' => $this->resource['compliance_remediation'] ?? null,
    ];
}
```

### 6.2 Update AgentRunController
**File:** `app/Http/Controllers/Api/V1/AgentRunController.php`

Add compliance to run responses:
- In `show()` and `index()` methods, include `compliance` key using `ComplianceSummaryResource`
- Extract compliance data from `metadata_json`
- Optional field - backward compatible

### 6.3 Update InterrogationSessionController
**File:** `app/Http/Controllers/Api/V1/InterrogationSessionController.php`

Add compliance to session and task responses:
- In session detail endpoints, include `compliance` summary
- In build task endpoints, include per-task compliance status
- Add endpoint `GET /api/v1/interrogation/sessions/{id}/compliance` for detailed compliance view

### 6.4 Create ComplianceSettingsController
**File:** `app/Http/Controllers/Api/V1/ComplianceSettingsController.php`

Manage compliance settings:
- `GET /api/v1/compliance/settings` - list all settings with effective values
- `PUT /api/v1/compliance/settings` - update settings (respects stricter-only constraint)
- `GET /api/v1/compliance/health` - rollout health metrics
- `POST /api/v1/compliance/mode` - switch between advisory/warning/enforced

### 6.5 Register API Routes
**File:** `routes/api.php`

Add routes:
```php
Route::prefix('compliance')->group(function () {
    Route::get('settings', [ComplianceSettingsController::class, 'index']);
    Route::put('settings', [ComplianceSettingsController::class, 'update']);
    Route::get('health', [ComplianceSettingsController::class, 'health']);
    Route::post('mode', [ComplianceSettingsController::class, 'setMode']);
});
```

## Phase 7: UI Implementation

### 7.1 Create Compliance Badge Component
**File:** Frontend component (location per project structure)

Badge indicators:
- `pass`: green background
- `blocked`: yellow background
- `advisory`: blue background
- `pending`: gray background
- Component accepts `status`, `gateName`, `reason`, `remediation` props
- Expandable tooltip on hover/click showing full details

### 7.2 Add Compliance Section to Jobs Monitor
**File:** Frontend jobs monitor view

Display compliance status:
- Add compliance badge next to run status
- Expandable section showing gate outcomes
- Remediation guidance when blocked
- Link to detailed compliance view

### 7.3 Add Compliance Section to Build Panel
**File:** Frontend build panel view

Display compliance per task:
- Badge indicator per task row
- Aggregate compliance status for build
- Blocked task shows specific gate failure and remediation
- Resume button disabled until compliance satisfied (when enforced)

### 7.4 Add Compliance Settings Page
**File:** Frontend settings view

Operator-facing settings:
- Toggle gates on/off
- Select enforcement mode (advisory/warning/enforced)
- View current rollout health metrics
- Configure alerting channels

### 7.5 Navigation and Discoverability
- Add "Compliance" item to settings navigation
- Add compliance status indicator to run/task detail headers
- Add alert banner when compliance blocks are occurring at high rate

## Phase 8: Alerting and Rollback

### 8.1 Create ComplianceAlertService
**File:** `app/Support/Compliance/ComplianceAlertService.php`

Alert emission:
- Method `checkThresholds(): void` invoked periodically
- Compare current block rate against configured thresholds
- If approaching threshold, emit warning alert
- If exceeds threshold, trigger automatic mode downgrade
- Channels: dashboard (always), Slack (optional), email (optional)
- All alerts also emitted as `ComplianceEvent` for custom integrations

### 8.2 Create StagedRollbackService
**File:** `app/Support/Compliance/StagedRollbackService.php`

Automatic mode transitions:
- Method `evaluate(): ?string` returns new mode if rollback needed
- Thresholds: `strict → warning` at 20%, `warning → advisory` at 30%
- Method `applyRollback(string $newMode): void`
- Records rollback event for audit trail
- Notifies via alert channels

### 8.3 Schedule Compliance Health Check
**File:** `app/Console/Kernel.php` or equivalent

Add scheduled command:
- `compliance:health-check` runs every 5 minutes
- Invokes `ComplianceAlertService::checkThresholds()`
- Invokes `StagedRollbackService::evaluate()`
- Records telemetry

## Phase 9: Testing

### 9.1 Unit Tests for Core Services

**ComplexityClassifierTest:**
- Test file threshold triggers non_trivial
- Test LOC threshold triggers non_trivial
- Test directory threshold triggers non_trivial
- Test schema change detection
- Test semantic keyword exact match
- Test semantic keyword stemming
- Test override force_simple
- Test override force_non_trivial
- Test simple task classification
- Coverage: 100% of decision paths

**TaskCategoryResolverTest:**
- Test explicit category from metadata
- Test inference from title keywords
- Test custom category handling
- Test default to CUSTOM for unknown

**PlanGateTest:**
- Test pass with JSON evidence
- Test pass with markdown file
- Test pass with commit evidence
- Test fail when no evidence
- Test skip when complexity is simple
- Test normalization of evidence format
- Coverage: 100% of decision paths

**VerificationGateTest:**
- Test feature category requirements (automated + ai_critic)
- Test bugfix category requirements (all three)
- Test refactor category requirements (automated only)
- Test documentation category (no requirements)
- Test partial satisfaction fails
- Test full satisfaction passes
- Coverage: 100% of decision paths

**EleganceGateTest:**
- Test PHPStan execution and result parsing
- Test CodeSniffer execution and result parsing
- Test LLM review output parsing
- Test aggregation of findings
- Test skip when complexity is simple
- Coverage: 100% of decision paths

**BugFixGateTest:**
- Test all evidence present passes
- Test missing failing_test fails
- Test missing error_evidence fails
- Test missing root_cause fails
- Test missing fix_verification fails
- Test only active for bugfix category
- Coverage: 100% of decision paths

**LessonsManagerTest:**
- Test capture from each trigger type
- Test file append format
- Test retrieval with token budget
- Test recency prioritization
- Test category matching
- Test injection into context

**ReplanSignalDetectorTest:**
- Test single trigger advisory
- Test multiple triggers mandatory
- Test consecutive failure detection
- Test scope drift detection
- Test time threshold detection

**ComplianceFlagResolverTest:**
- Test global config loading
- Test tenant override merging
- Test stricter-only constraint enforcement
- Test boolean gate override rules
- Test numeric threshold override rules

### 9.2 Feature Tests for Integration

**ExecuteAgentRunJobComplianceTest:**
- Test policy evaluation called on run start
- Test compliance metadata persisted
- Test gate failure blocks run in enforced mode
- Test gate failure logs only in advisory mode
- Test existing run lifecycle unaffected

**ExecuteInterrogationBuildJobComplianceTest:**
- Test policy evaluation called before task start
- Test policy evaluation called before task complete
- Test task blocked on gate failure
- Test build paused with compliance reason
- Test existing build lifecycle unaffected

**ComplianceApiTest:**
- Test compliance summary in run responses
- Test compliance summary in session responses
- Test settings endpoint CRUD
- Test mode switching
- Test health endpoint

### 9.3 Test Coverage Targets
- Gate decision paths: 100% coverage
- Other compliance code: 80% coverage
- Integration with existing jobs: full regression suite passes

## Phase 10: Rollout Sequence

### 10.1 Phase A: Advisory Mode
- Deploy all code with `COMPLIANCE_MODE=advisory`
- All evaluation runs, outcomes logged, no blocking
- Collect telemetry for minimum 1 week
- Exit criteria: >85% pass rate, operator approval

### 10.2 Phase B: Build Flow Enforcement
- Switch `COMPLIANCE_MODE=enforced` for build path only
- Jobs path remains advisory
- Monitor block rate, respond to false positives
- Adjust thresholds if needed

### 10.3 Phase C: Jobs Flow Enforcement
- Enable enforced mode for jobs path
- Monitor unified compliance metrics
- Support tickets for blocked runs

### 10.4 Phase D: Full Enforcement
- Enable elegance gate strict checks
- Enable lessons strict capture
- Full compliance layer operational

## Service Provider Registration

**File:** `app/Providers/ComplianceServiceProvider.php`

Register services:
```php
public function register(): void {
    $this->app->singleton(ComplexityClassifier::class);
    $this->app->singleton(TaskCategoryResolver::class);
    $this->app->singleton(ComplianceFlagResolver::class);
    $this->app->singleton(PolicyEvaluator::class);
    $this->app->singleton(LessonsManager::class);
    $this->app->singleton(ComplianceMetadataWriter::class);
    $this->app->singleton(ComplianceEventEmitter::class);
    $this->app->singleton(ComplianceTelemetryCollector::class);
    $this->app->singleton(ComplianceAlertService::class);
    $this->app->singleton(StagedRollbackService::class);
    $this->app->singleton(RunnerCapabilityChecker::class);
    $this->app->singleton(ReplanSignalDetector::class);
    // Gates
    $this->app->singleton(PlanGate::class);
    $this->app->singleton(VerificationGate::class);
    $this->app->singleton(EleganceGate::class);
    $this->app->singleton(BugFixGate::class);
}
```

Register in `config/app.php` providers array.

## File Creation Summary

### New Files (app/Support/Compliance/)
1. `TaskCategory.php` - Enum
2. `ComplexityClassifier.php` - Service
3. `TaskCategoryResolver.php` - Service
4. `ComplianceFlagResolver.php` - Service
5. `RunnerCapabilityChecker.php` - Service
6. `PolicyEvaluator.php` - Service
7. `LessonsManager.php` - Service
8. `ReplanSignalDetector.php` - Service
9. `ComplianceMetadataWriter.php` - Service
10. `ComplianceEventEmitter.php` - Service
11. `ComplianceTelemetryCollector.php` - Service
12. `ComplianceAlertService.php` - Service
13. `StagedRollbackService.php` - Service
14. `Contracts/Gate.php` - Interface
15. `Gates/AbstractGate.php` - Abstract class
16. `Gates/PlanGate.php` - Gate
17. `Gates/VerificationGate.php` - Gate
18. `Gates/EleganceGate.php` - Gate
19. `Gates/BugFixGate.php` - Gate
20. `GateContext.php` - DTO
21. `GateResult.php` - DTO
22. `PolicyResult.php` - DTO
23. `ComplexityResult.php` - DTO
24. `ReplanSignal.php` - DTO
25. `RunnerCapabilities.php` - DTO

### New Files (app/Models/)
26. `AgentComplianceSetting.php` - Model

### New Files (app/Http/)
27. `Controllers/Api/V1/ComplianceSettingsController.php`
28. `Resources/ComplianceSummaryResource.php`

### New Files (app/Providers/)
29. `ComplianceServiceProvider.php`

### New Files (database/migrations/)
30. `xxxx_create_agent_compliance_settings_table.php`

### New Files (tests/)
31. `tests/Unit/Support/Compliance/ComplexityClassifierTest.php`
32. `tests/Unit/Support/Compliance/TaskCategoryResolverTest.php`
33. `tests/Unit/Support/Compliance/Gates/PlanGateTest.php`
34. `tests/Unit/Support/Compliance/Gates/VerificationGateTest.php`
35. `tests/Unit/Support/Compliance/Gates/EleganceGateTest.php`
36. `tests/Unit/Support/Compliance/Gates/BugFixGateTest.php`
37. `tests/Unit/Support/Compliance/LessonsManagerTest.php`
38. `tests/Unit/Support/Compliance/ReplanSignalDetectorTest.php`
39. `tests/Unit/Support/Compliance/ComplianceFlagResolverTest.php`
40. `tests/Feature/Jobs/ExecuteAgentRunJobComplianceTest.php`
41. `tests/Feature/Jobs/ExecuteInterrogationBuildJobComplianceTest.php`
42. `tests/Feature/Http/ComplianceApiTest.php`

### Modified Files
1. `config/agent.php` - Add compliance configuration
2. `app/Jobs/ExecuteAgentRunJob.php` - Add policy evaluation calls
3. `app/Jobs/ExecuteInterrogationBuildJob.php` - Add policy evaluation calls
4. `app/Support/Interrogation/BuildTaskRunFactory.php` - Add compliance metadata
5. `app/Support/Interrogation/SystemPromptResolver.php` - Add lesson injection
6. `app/Support/Agent/FeatureFlagManager.php` - Add compliance flag definitions
7. `app/Http/Controllers/Api/V1/AgentRunController.php` - Add compliance to responses
8. `app/Http/Controllers/Api/V1/InterrogationSessionController.php` - Add compliance to responses
9. `routes/api.php` - Add compliance routes
10. `app/Console/Kernel.php` - Add scheduled compliance health check
11. `config/app.php` - Register ComplianceServiceProvider

## Dependency Order

1. **Phase 1 (Foundation)** - No dependencies, can proceed immediately
2. **Phase 2 (Gates)** - Depends on Phase 1 DTOs and interfaces
3. **Phase 3 (Orchestration)** - Depends on Phase 1 and Phase 2
4. **Phase 4 (Integration)** - Depends on Phase 3 services
5. **Phase 5 (Config/DB)** - Can proceed in parallel with Phase 1-3
6. **Phase 6 (API)** - Depends on Phase 3 and Phase 5
7. **Phase 7 (UI)** - Depends on Phase 6 API availability
8. **Phase 8 (Alerting)** - Depends on Phase 3 telemetry
9. **Phase 9 (Testing)** - Proceeds in parallel with each phase
10. **Phase 10 (Rollout)** - After all phases complete

## Sections

- Phase 1: Foundation Services and Enums
- Phase 2: Gate Implementations
- Phase 3: Core Orchestration Services
- Phase 4: Integration Points
- Phase 5: Configuration and Database
- Phase 6: API Contract Updates
- Phase 7: UI Implementation
- Phase 8: Alerting and Rollback
- Phase 9: Testing
- Phase 10: Rollout Sequence


## Risks

- False-positive gate blocks causing legitimate work to be blocked. Mitigation: Advisory-first rollout with >85% pass rate exit criteria before enforcement; quick toggle flags per gate; detailed remediation messaging.
- Runner capability mismatch causing unexpected behavior. Mitigation: RunnerCapabilityChecker with graceful degradation; downgrades recorded in metadata without failing gates; identical policy expectations across runners.
- Metadata inconsistency across run/task/session surfaces. Mitigation: Single ComplianceMetadataWriter service with normalized key names; shared schema contract; unit tests for metadata format.
- Increased complexity in build finalization logic causing regressions. Mitigation: Isolated services with clear interfaces; targeted tests around status transitions; existing lifecycle tests as regression baseline.
- Performance degradation from policy evaluation. Mitigation: Hybrid sync/async timing model; sync evaluation under 50ms; async operations deferred; classification cached per run.
- Elegance gate static analysis tools failing or timing out. Mitigation: Configurable timeouts; graceful degradation to partial results; tool availability checked before execution.
- Lesson injection exceeding token budget affecting prompt quality. Mitigation: Configurable token budget with env override; recency-weighted filtering; category-based pruning.
- Staged rollback triggering too aggressively. Mitigation: Configurable thresholds per stage; manual approval option; minimum observation window before auto-rollback.


## Assumptions

- STATUS_BLOCKED constant exists in InterrogationBuildTask model and can be reused for compliance blocks; AgentJobRun may need compliance blocks handled via STATUS_FAILED with specific error_code if no BLOCKED status exists.
- PHPStan and PHP_CodeSniffer are available in the project environment or can be installed as dev dependencies.
- Existing metadata_json columns on AgentJobRun, InterrogationBuildTask, and InterrogationSession have sufficient capacity for compliance metadata (JSON columns).
- FeatureFlagManager pattern can be extended for compliance flags without breaking existing feature flag consumers.
- Frontend infrastructure exists for implementing badge components and settings pages; specific framework/location determined by existing frontend patterns.
- Telemetry storage can handle the metrics volume from compliance evaluation without significant infrastructure changes.
- tasks/lessons.md path is writable by the application and persists across deployments.
- Tenant isolation already exists in the platform and tenant_id can be resolved from request context for ComplianceFlagResolver.
- Existing RunEventWriter and InterrogationEventWriter can handle additional compliance event types without modification.
- Advisory mode minimum duration of 1 week is acceptable for initial rollout; operator approval gate exists or can be implemented.

