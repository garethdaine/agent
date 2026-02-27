# Implementation Plan

Derived from discovery session 5.

# Workflow-Orchestration Compliance Layer Implementation Plan

## Phase 1: Foundation - Core Policy Service and Classification

### 1.1 Create OrchestrationPolicyService Contract and Implementation

**Files to Create:**
- `app/Contracts/OrchestrationPolicyServiceContract.php` - Interface defining policy evaluation contract
- `app/Support/Compliance/OrchestrationPolicyService.php` - Unified policy evaluation for jobs and builds

**Implementation Details:**
- Define `evaluatePreRun(AgentJobRun|InterrogationBuildTask $subject): PolicyEvaluationResult` method
- Define `evaluateCompletion(AgentJobRun|InterrogationBuildTask $subject): CompletionGateResult` method
- Accept both AgentJobRun and InterrogationBuildTask models via union type
- Return structured result DTOs with `status` (pass|blocked|advisory), `gates` array, `remediation` string
- Integrate with ComplexityClassifier and VerificationEvidenceEvaluator as dependencies
- Register in `AppServiceProvider` as singleton

**Service Registration:**
- Add `OrchestrationPolicyService::class` binding to `app/Providers/AppServiceProvider.php`

### 1.2 Create ComplexityClassifier Service

**Files to Create:**
- `app/Support/Compliance/ComplexityClassifier.php` - Hybrid heuristic + override classification

**Implementation Details:**
- Classify as `non_trivial` when: file count >3 OR LOC >50 OR directory count >2
- Accept explicit override via `metadata_json.complexity_override` field
- Return `ComplexityResult` DTO with `classification` enum (simple|non_trivial), `evidence` array
- Parse task instructions/description to extract file/LOC/directory counts
- Cache classification result in metadata for consistency within run lifecycle

### 1.3 Create Task Category Enum and Migration

**Files to Create:**
- `app/Enums/TaskCategory.php` - Fixed enum with cases: feature, bugfix, refactor, docs, test, custom
- `database/migrations/2026_XX_XX_XXXXXX_add_task_category_to_interrogation_build_tasks.php`

**Migration Details:**
- Add `task_category` column to `interrogation_build_tasks` table
- Type: `string(24)` with default value `'custom'`
- Index on `(interrogation_session_id, task_category)` for filtered queries

### 1.4 Create Policy Evaluation Result DTOs

**Files to Create:**
- `app/Support/Compliance/DTOs/PolicyEvaluationResult.php`
- `app/Support/Compliance/DTOs/CompletionGateResult.php`
- `app/Support/Compliance/DTOs/GateResult.php`

**DTO Structures:**
```
PolicyEvaluationResult {
  status: string (pass|blocked|advisory)
  complexity: string (simple|non_trivial)
  task_category: string
  plan_required: bool
  verification_required: bool
  elegance_required: bool
  gates: array<GateResult>
  metadata_patch: array
}

GateResult {
  gate: string (plan|verification|elegance|bugfix_evidence)
  status: string (pass|blocked|advisory)
  reason_code: string
  remediation: ?string
}
```

---

## Phase 2: Verification and Evidence Evaluation

### 2.1 Create VerificationEvidenceEvaluator Service

**Files to Create:**
- `app/Support/Compliance/VerificationEvidenceEvaluator.php`

**Implementation Details:**
- Define category-specific requirements map:
  - `feature`: automated_check + ai_critic
  - `bugfix`: automated_check + ai_critic + human_approval
  - `refactor`: automated_check only
  - `docs`: no verification required
  - `test`: no verification required
  - `custom`: inherits feature requirements
- Parse `metadata_json.verification_evidence` for evidence presence
- Return `VerificationResult` with `satisfied: bool`, `missing: array`, `evidence: array`

### 2.2 Create Bug-Fix Evidence Chain Validator

**Files to Create:**
- `app/Support/Compliance/BugfixEvidenceValidator.php`

**Evidence Chain Requirements:**
1. Failing test evidence in `metadata_json.bugfix_evidence.failing_test`
2. Error evidence in `metadata_json.bugfix_evidence.error_output`
3. Root cause documentation in `metadata_json.root_cause_evidence`
4. Fix verification in `metadata_json.verification_evidence.automated_check`

**Implementation Details:**
- All four elements required for bugfix tasks to pass completion gate
- Return structured result with `complete: bool`, `missing_steps: array`, `chain: array`

### 2.3 Create Plan Evidence Evaluator

**Files to Create:**
- `app/Support/Compliance/PlanEvidenceEvaluator.php`

**Implementation Details:**
- Accept markdown, structured JSON, or external document reference
- Normalize all formats to common schema: `{format: string, content: string, reference: ?string, captured_at: string}`
- Store normalized evidence in `metadata_json.plan_evidence`
- Return `PlanEvidenceResult` with `present: bool`, `format: string`, `normalized: array`

---

## Phase 3: Gate Integration Points

### 3.1 Integrate Policy Evaluation into ExecuteAgentRunJob

**File to Modify:**
- `app/Jobs/ExecuteAgentRunJob.php`

**Integration Point:**
- After `$movedToStarting` transition succeeds, before runtime validation
- Call `OrchestrationPolicyService::evaluatePreRun($run)`
- If result status is `blocked` and enforcement enabled:
  - Transition to `STATUS_FAILED` with `error_code: COMPLIANCE_BLOCKED`
  - Set `compliance_block_reason` in metadata_json
- If result status is `advisory`:
  - Log advisory and continue execution
  - Record advisory in metadata_json

**Metadata Updates:**
- Merge `result.metadata_patch` into `run.metadata_json`
- Include: `workflow_policy_version`, `complexity_classification`, `task_category`, `plan_required`, `verification_required`

### 3.2 Integrate Policy Evaluation into BuildTaskRunFactory

**File to Modify:**
- `app/Support/Interrogation/BuildTaskRunFactory.php`

**Integration Point:**
- Before `AgentJobRun::query()->create()` call in `create()` method
- Call `OrchestrationPolicyService::evaluatePreRun($task)`
- Store compliance metadata in the created run's metadata_json

**Additional Metadata:**
- Extract task_category from task model
- Inject lessons context via LessonsManager if enabled

### 3.3 Integrate Completion Gates into ExecuteInterrogationBuildJob

**File to Modify:**
- `app/Jobs/ExecuteInterrogationBuildJob.php`

**Integration Point:**
- In `finalizeTaskFromRun()` method, before setting `STATUS_COMPLETED`
- Call `OrchestrationPolicyService::evaluateCompletion($task)`
- If verification gate fails:
  - Set task status to `STATUS_BLOCKED` instead of `STATUS_COMPLETED`
  - Set `compliance_block_reason` in task metadata
  - Set build status to `paused` with `pause_reason: compliance_blocked`

**Gate Sequence:**
1. Verification evidence gate (category-specific)
2. Plan evidence gate (for non_trivial)
3. Elegance gate (for non_trivial when enabled)
4. Bugfix evidence chain (for bugfix category)

---

## Phase 4: Lessons System

### 4.1 Create LessonsManager Service

**Files to Create:**
- `app/Support/Compliance/LessonsManager.php`

**Implementation Details:**
- Standardize lessons file path as `{project_directory}/tasks/lessons.md`
- Implement `appendLesson(string $content, string $source, array $context): void`
- Implement `queryLessons(string $query, int $tokenBudget = 2000): array`
- Source types: `explicit_rejection`, `correction_message`, `failed_retry`, `manual_add`
- Recency weighting: newer lessons ranked higher
- Token budget enforcement with truncation

### 4.2 Create Lessons File Writer

**Files to Create:**
- `app/Support/Compliance/LessonsFileWriter.php`

**File Format:**
```markdown
## Lessons

### [timestamp] [source_type]
[lesson_content]

Context:
- Task: [task_title]
- Category: [task_category]
- Session: [session_id]
```

### 4.3 Implement Lesson Injection into Task Context

**File to Modify:**
- `app/Support/Interrogation/BuildTaskRunFactory.php`

**Injection Point:**
- In `buildTaskMarkdown()` method
- Add new section `## Relevant Lessons` after `## Plan Context`
- Query lessons using task title + description keywords
- Inject up to 2000 tokens of relevant lessons

### 4.4 Create Lesson Trigger Event Handlers

**Files to Create:**
- `app/Listeners/RecordLessonOnCorrection.php`

**Trigger Points:**
- `editAnswer` action in InterrogationSessionController → user correction
- Task retry with previous failure → failed verification retry
- Manual lesson submission via new API endpoint

---

## Phase 5: Re-Plan Signal Detection

### 5.1 Create ReplanSignalDetector Service

**Files to Create:**
- `app/Support/Compliance/ReplanSignalDetector.php`

**Signal Types:**
- `test_failure`: Test command exit code non-zero after implementation
- `scope_expansion`: Task description differs significantly from original
- `dependency_conflict`: Unresolvable package/module conflicts detected

**Detection Logic:**
- Parse run stdout/stderr for failure patterns
- Compare task metadata for scope changes
- Check for dependency-related error messages

**Trigger Behavior:**
- 1 signal → advisory (log recommendation to re-plan)
- 2+ signals → mandatory (block completion until plan updated)

### 5.2 Integrate Re-Plan Detection into Completion Flow

**File to Modify:**
- `app/Jobs/ExecuteInterrogationBuildJob.php`

**Integration Point:**
- In `finalizeTaskFromRun()` before status assignment
- Call `ReplanSignalDetector::detect($task, $run)`
- If mandatory re-plan triggered:
  - Set task status to `STATUS_BLOCKED`
  - Set `compliance_block_reason: replan_required`
  - Record detected signals in metadata

---

## Phase 6: Elegance Gate

### 6.1 Create EleganceGate Service

**Files to Create:**
- `app/Support/Compliance/EleganceGate.php`

**Static Analysis Phase:**
- PHPStan level 6 execution
- PHP_CodeSniffer PSR-12 ruleset
- Collect violations into structured array

**LLM Review Phase:**
- Submit changed files to LLM with structured JSON schema output requirement
- Schema: `{score: int(0-100), violations: [{file: string, line: int, severity: string, message: string}]}`
- Threshold: configurable via `config/agent.php`

### 6.2 Create EleganceGateRunner

**Files to Create:**
- `app/Support/Compliance/EleganceGateRunner.php`

**Execution Flow:**
1. Run PHPStan: `vendor/bin/phpstan analyse --level 6 --error-format json`
2. Run PHP_CodeSniffer: `vendor/bin/phpcs --standard=PSR12 --report=json`
3. Parse JSON outputs
4. Execute LLM review with file diffs
5. Aggregate results into `EleganceResult` DTO

### 6.3 Store Elegance Results in Metadata

**Metadata Schema:**
```json
{
  "elegance_review_required": true,
  "elegance_review_completed": true,
  "elegance_findings": {
    "phpstan_errors": 0,
    "phpcs_violations": 2,
    "llm_score": 85,
    "llm_violations": [],
    "passed": true
  }
}
```

---

## Phase 7: Feature Flags and Configuration

### 7.1 Extend FeatureFlagManager for Compliance Flags

**File to Modify:**
- `app/Support/Agent/FeatureFlagManager.php`

**New Flag Definitions:**
```php
public const COMPLIANCE_PLAN_GATE_ENABLED = 'compliance.plan_gate_enabled';
public const COMPLIANCE_VERIFICATION_GATE_ENABLED = 'compliance.verification_gate_enabled';
public const COMPLIANCE_ELEGANCE_GATE_ENABLED = 'compliance.elegance_gate_enabled';
public const COMPLIANCE_LESSONS_ENABLED = 'compliance.lessons_enabled';
public const COMPLIANCE_ENFORCEMENT_MODE = 'compliance.enforcement_mode'; // advisory|strict
```

**Flag Behavior:**
- Global defaults in `config/agent.php`
- Tenant overrides can only make stricter (not weaker)

### 7.2 Create ComplianceFlagResolver Service

**Files to Create:**
- `app/Support/Compliance/ComplianceFlagResolver.php`

**Resolution Logic:**
- Fetch global default from config
- Fetch tenant override from database (if exists)
- Apply stricter-only merge: if tenant enables enforcement, use that; if tenant tries to disable, ignore
- Return resolved flag values for all compliance settings

### 7.3 Add Compliance Configuration to agent.php

**File to Modify:**
- `config/agent.php`

**New Configuration Section:**
```php
'compliance' => [
    'enabled' => (bool) env('AGENT_COMPLIANCE_ENABLED', false),
    'enforcement_mode' => env('AGENT_COMPLIANCE_ENFORCEMENT_MODE', 'advisory'),
    'plan_gate_enabled' => (bool) env('AGENT_COMPLIANCE_PLAN_GATE_ENABLED', true),
    'verification_gate_enabled' => (bool) env('AGENT_COMPLIANCE_VERIFICATION_GATE_ENABLED', true),
    'elegance_gate_enabled' => (bool) env('AGENT_COMPLIANCE_ELEGANCE_GATE_ENABLED', false),
    'lessons_enabled' => (bool) env('AGENT_COMPLIANCE_LESSONS_ENABLED', true),
    'lessons_token_budget' => (int) env('AGENT_COMPLIANCE_LESSONS_TOKEN_BUDGET', 2000),
    'elegance_score_threshold' => (int) env('AGENT_COMPLIANCE_ELEGANCE_SCORE_THRESHOLD', 70),
    'replan_mandatory_signal_count' => (int) env('AGENT_COMPLIANCE_REPLAN_MANDATORY_SIGNALS', 2),
    'complexity_thresholds' => [
        'file_count' => (int) env('AGENT_COMPLIANCE_FILE_COUNT_THRESHOLD', 3),
        'loc_count' => (int) env('AGENT_COMPLIANCE_LOC_THRESHOLD', 50),
        'directory_count' => (int) env('AGENT_COMPLIANCE_DIRECTORY_THRESHOLD', 2),
    ],
],
```

---

## Phase 8: Events and Observability

### 8.1 Create ComplianceEvents

**Files to Create:**
- `app/Events/ComplianceGateEvaluated.php`

**Event Payload:**
```php
public function __construct(
    public int $subjectId,
    public string $subjectType, // AgentJobRun|InterrogationBuildTask
    public string $gate,
    public string $status, // pass|blocked|advisory
    public string $reasonCode,
    public array $evidence,
    public string $timestamp
)
```

### 8.2 Create Compliance Event Listener for Metrics

**Files to Create:**
- `app/Listeners/RecordComplianceMetrics.php`

**Metrics to Record:**
- `compliance.gate.evaluated` counter with tags: gate, status, subject_type
- `compliance.verification.rate` gauge: completed/required
- `compliance.lessons.utilization` counter
- `compliance.elegance.score` histogram
- `compliance.block.duration` histogram (time in blocked state)

### 8.3 Integrate with InterrogationEventWriter

**File to Modify:**
- `app/Support/Interrogation/InterrogationEventWriter.php`

**New Event Types:**
- `compliance_gate_pass`
- `compliance_gate_blocked`
- `compliance_gate_advisory`
- `lessons_injected`
- `replan_recommended`
- `replan_required`

---

## Phase 9: API Surface Extensions

### 9.1 Extend Run/Task API Responses with Compliance Summary

**Files to Modify:**
- `app/Http/Controllers/Api/V1/AgentRunController.php`
- `app/Http/Controllers/Api/V1/InterrogationSessionController.php`

**Response Extension:**
```json
{
  "compliance_summary": {
    "status": "pass|blocked|advisory",
    "gates": [
      {"gate": "plan", "status": "pass", "reason_code": null},
      {"gate": "verification", "status": "blocked", "reason_code": "missing_automated_check"}
    ],
    "remediation": "Run `php artisan test` and capture output as verification evidence."
  }
}
```

### 9.2 Create Compliance Status Endpoint

**File to Create:**
- `app/Http/Controllers/Api/V1/ComplianceController.php`

**Routes to Add in api.php:**
```php
Route::get('/compliance/status', [ComplianceController::class, 'status']);
Route::get('/compliance/metrics', [ComplianceController::class, 'metrics']);
Route::post('/compliance/lessons', [ComplianceController::class, 'addLesson'])->middleware('throttle:agent-mutations');
```

**Endpoints:**
- `GET /compliance/status` - Current compliance flag states and enforcement mode
- `GET /compliance/metrics` - Aggregated compliance metrics for dashboard
- `POST /compliance/lessons` - Manual lesson submission

### 9.3 Create Compliance API Resource

**Files to Create:**
- `app/Http/Resources/ComplianceSummaryResource.php`

---

## Phase 10: UI Surface Exposure

### 10.1 Extend Job/Run List with Compliance Indicators

**API Response Changes:**
- Include `compliance_summary.status` in index/list endpoints
- Frontend can render badge/icon based on status value

**UI Acceptance Criteria:**
- Job monitor page displays compliance badge (pass=green, blocked=red, advisory=yellow) on each run card
- Build panel displays compliance status on each task card
- Clicking blocked badge reveals remediation reason in tooltip or modal

### 10.2 Extend Session Detail View with Gate Results

**API Response Changes:**
- Include full `compliance_summary.gates` array in show endpoints
- Frontend renders gate checklist with status indicators

**UI Acceptance Criteria:**
- Session detail page shows collapsible "Compliance Gates" section
- Each gate displays: name, status icon, reason code (if blocked), remediation (if applicable)
- Gates section visible in Build Execution phase

### 10.3 Add Compliance Dashboard Route

**Route Addition:**
- Ensure API metrics endpoint is navigable from main dashboard
- Frontend integration point for compliance telemetry visualization

**UI Acceptance Criteria:**
- Dashboard includes compliance health widget showing:
  - Pass rate percentage for last 24 hours
  - Top 3 blocking reason codes
  - Enforcement mode indicator (advisory/strict)
- Widget links to full compliance metrics view

---

## Phase 11: Testing Strategy

### 11.1 Unit Tests

**Test Files to Create:**
- `tests/Unit/Support/Compliance/ComplexityClassifierTest.php`
- `tests/Unit/Support/Compliance/VerificationEvidenceEvaluatorTest.php`
- `tests/Unit/Support/Compliance/PlanEvidenceEvaluatorTest.php`
- `tests/Unit/Support/Compliance/BugfixEvidenceValidatorTest.php`
- `tests/Unit/Support/Compliance/LessonsManagerTest.php`
- `tests/Unit/Support/Compliance/ReplanSignalDetectorTest.php`
- `tests/Unit/Support/Compliance/EleganceGateTest.php`
- `tests/Unit/Support/Compliance/ComplianceFlagResolverTest.php`
- `tests/Unit/Support/Compliance/OrchestrationPolicyServiceTest.php`

**Coverage Targets:**
- ComplexityClassifier: all threshold combinations, override behavior
- VerificationEvidenceEvaluator: all category requirements, partial evidence
- LessonsManager: append, query, token budget, recency weighting
- BugfixEvidenceValidator: all chain steps, incomplete chains

### 11.2 Feature Tests

**Test Files to Create:**
- `tests/Feature/Compliance/JobComplianceGateTest.php`
- `tests/Feature/Compliance/BuildComplianceGateTest.php`
- `tests/Feature/Compliance/LessonsInjectionTest.php`
- `tests/Feature/Compliance/ReplanSignalTest.php`
- `tests/Feature/Api/ComplianceApiTest.php`

**Feature Test Coverage:**
- Job blocked when verification missing (enforcement mode)
- Build paused when plan evidence missing
- Lessons injected into task markdown
- Advisory logged without blocking
- API returns compliance summary

### 11.3 Regression Tests

**Test Files to Create:**
- `tests/Feature/Regression/RunLifecycleRegressionTest.php`
- `tests/Feature/Regression/BuildLifecycleRegressionTest.php`

**Regression Coverage:**
- Existing run lifecycle unchanged when compliance disabled
- Existing build lifecycle unchanged when compliance disabled
- No breaking changes to API response structure

### 11.4 Event/Metadata Assertion Tests

**Test Files to Create:**
- `tests/Feature/Compliance/ComplianceEventTest.php`
- `tests/Feature/Compliance/MetadataPersistenceTest.php`

**Assertion Coverage:**
- ComplianceGateEvaluated events dispatched correctly
- Metadata contract fields persisted accurately
- Event payload matches expected schema

---

## Phase 12: Migration and Rollout

### 12.1 Database Migration

**Migration File:**
- `database/migrations/2026_XX_XX_XXXXXX_add_task_category_to_interrogation_build_tasks.php`

**Migration Contents:**
- Add `task_category` column with default `'custom'`
- Add index on `(interrogation_session_id, task_category)`

### 12.2 Advisory Phase Deployment

**Deployment Steps:**
1. Deploy all code changes with `AGENT_COMPLIANCE_ENABLED=false`
2. Run migration to add task_category column
3. Enable compliance with `AGENT_COMPLIANCE_ENFORCEMENT_MODE=advisory`
4. Monitor telemetry for gate evaluation rates

**Validation Criteria:**
- No production incidents
- Advisory events logging correctly
- No performance degradation

### 12.3 Build Flow Enforcement

**Promotion Criteria:**
- Minimum 1 week in advisory mode
- >85% pass rate on verification gates
- Operator approval obtained

**Enablement:**
- Set `AGENT_COMPLIANCE_VERIFICATION_GATE_ENABLED=true`
- Set `AGENT_COMPLIANCE_ENFORCEMENT_MODE=strict` for build flow only

### 12.4 Jobs Flow Enforcement

**Promotion Criteria:**
- Build flow enforcement stable
- No elevated block rate in jobs flow advisory telemetry
- Operator approval obtained

**Enablement:**
- Apply strict mode to jobs flow via config flag

### 12.5 Elegance and Lessons Strict Mode

**Promotion Criteria:**
- Core gates stable
- Elegance score distribution acceptable
- Lesson utilization demonstrating value

**Enablement:**
- Set `AGENT_COMPLIANCE_ELEGANCE_GATE_ENABLED=true` in strict mode
- Monitor for false-positive elegance blocks

---

## Dependency Order

1. **Phase 1** (Foundation) - No dependencies
2. **Phase 2** (Verification) - Depends on Phase 1 DTOs
3. **Phase 3** (Gate Integration) - Depends on Phase 1 + 2 services
4. **Phase 4** (Lessons) - Depends on Phase 1 config
5. **Phase 5** (Re-Plan) - Depends on Phase 3 integration points
6. **Phase 6** (Elegance) - Depends on Phase 1 + 7 flags
7. **Phase 7** (Feature Flags) - Depends on Phase 1 services
8. **Phase 8** (Events) - Depends on Phase 3 integration
9. **Phase 9** (API) - Depends on Phase 1 DTOs + Phase 8 events
10. **Phase 10** (UI) - Depends on Phase 9 API extensions
11. **Phase 11** (Testing) - Can begin in parallel after Phase 1
12. **Phase 12** (Rollout) - Final phase after all implementation

## Sections

- Phase 1: Foundation - Core Policy Service and Classification
- Phase 2: Verification and Evidence Evaluation
- Phase 3: Gate Integration Points
- Phase 4: Lessons System
- Phase 5: Re-Plan Signal Detection
- Phase 6: Elegance Gate
- Phase 7: Feature Flags and Configuration
- Phase 8: Events and Observability
- Phase 9: API Surface Extensions
- Phase 10: UI Surface Exposure
- Phase 11: Testing Strategy
- Phase 12: Migration and Rollout


## Risks

- False-positive gate blocks may cause legitimate work to be blocked incorrectly; mitigated by advisory-first rollout with quick toggle flags and clear reason codes for operator review
- Runner capability mismatch for subagent expectations may cause inconsistent behavior across claude/codex/custom runners; mitigated by capability-aware downgrade to advisory mode without hard failures
- Metadata inconsistency across jobs and builds may create audit gaps; mitigated by shared schema contract via DTOs and normalization utilities with validation
- Increased complexity in build finalization logic may introduce regressions; mitigated by isolated services with targeted tests around status transitions and regression test suite
- Policy evaluation overhead may impact run startup latency; mitigated by synchronous classification with fast heuristics and asynchronous verification for heavier checks
- EleganceGate LLM review may have variable quality or availability; mitigated by fallback to static analysis only with advisory status and configurable timeouts
- Lessons system keyword matching may miss relevant lessons or inject irrelevant content; mitigated by exact match plus simple stemming without NLP dependencies and recency weighting
- High block rate after enforcement promotion may disrupt workflows; mitigated by staged rollback mechanism from strict to warning-only to advisory with configurable thresholds


## Assumptions

- AgentJobRun and InterrogationBuildTask models remain the authoritative execution subjects with metadata_json supporting arbitrary JSON
- STATUS_BLOCKED constant exists on InterrogationBuildTask model and is appropriate for compliance blocks (confirmed in codebase)
- FeatureFlagManager pattern is extensible for new compliance flag definitions using DEFINITIONS constant pattern
- PHPStan and PHP_CodeSniffer are available in the project vendor directory or will be added as dev dependencies
- Existing RunStateTransitionService can handle transitions to blocked states without modification
- InterrogationEventWriter can be extended with new event types without breaking existing consumers
- API responses are consumed by frontend clients that can handle optional new fields without breaking
- tasks/lessons.md file location is writable within project directories configured in allowed_working_directory_bases
- LLM review for elegance gate uses existing infrastructure for Claude/Codex API calls
- Telemetry infrastructure exists or will be integrated for metrics recording

