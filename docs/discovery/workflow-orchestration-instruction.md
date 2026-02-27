# Requirements Discovery Summary

Session: 5

# Workflow-Orchestration Compliance Layer

## Overview
A unified orchestration compliance layer that operationalizes workflow standards across both standard job execution (`ExecuteAgentRunJob`) and interrogation build execution (`ExecuteInterrogationBuildJob`). The system enforces plan-first behavior, verification gates, correction-driven lessons capture, and elegance checks through configurable, progressive policy enforcement.

## Architecture

### New Namespace: `app/Support/Compliance/`

| Service | Responsibility |
|---------|----------------|
| `ComplexityClassifier` | Hybrid heuristic + override classification (simple vs non_trivial) |
| `PolicyEvaluator` | Main orchestration service evaluating all applicable gates |
| `PlanGate` | Enforces plan evidence requirement for non-trivial tasks |
| `VerificationGate` | Enforces tiered verification evidence by task category |
| `EleganceGate` | PHPStan + PHP_CodeSniffer + LLM self-review checkpoint |
| `BugFixGate` | Enforces full evidence chain for bug-fix completions |
| `LessonsManager` | Handles lesson capture, storage, and recency-weighted retrieval |
| `ReplanSignalDetector` | Monitors execution for drift/failure patterns requiring re-plan |
| `ComplianceMetadataWriter` | Persists compliance state to run/task/session metadata |
| `ComplianceEventEmitter` | Emits structured compliance events for observability |
| `ComplianceTelemetryCollector` | Collects rollout metrics for enforcement decisions |
| `ComplianceFlagResolver` | Resolves hierarchical global + tenant-level flags (stricter only) |
| `RunnerCapabilityChecker` | Detects runner capabilities for graceful degradation |
| `TaskCategoryResolver` | Maps tasks to fixed enum categories with custom escape hatch |

## Complexity Classification

### Heuristic Thresholds (Confirmed Defaults)
- **File scope**: touches >3 files → non_trivial
- **Directory scope**: crosses >2 directories → non_trivial
- **Change magnitude**: estimated LOC >50 → non_trivial
- **Schema changes**: touches database migrations → non_trivial
- **Dependency risk**: modifies shared services, configs, or public API contracts → non_trivial
- **Semantic keywords**: exact match + simple stemming (e.g., "migration" matches "migrations")

### Keyword Detection
- Exact match first, then simple stemming
- No embedding similarity (keep lightweight)
- Keywords configurable in `config/agent.php`
- Default keywords: `migration`, `refactor`, `security`, `architecture`, `breaking`

### Override Mechanism
- Field: `complexity_override` on task/run creation
- Values: `force_simple`, `force_non_trivial`, `auto` (default)

## Task Categories (Fixed Enum + Custom Escape Hatch)

```php
enum TaskCategory: string {
    case FEATURE = 'feature';
    case BUGFIX = 'bugfix';
    case REFACTOR = 'refactor';
    case DOCUMENTATION = 'documentation';
    case TEST = 'test';
    case INFRASTRUCTURE = 'infrastructure';
    case CUSTOM = 'custom';
}
```

### Custom Category Handling
- Ad-hoc values allowed without pre-registration
- Custom categories stored as-is
- Treated as `non_trivial` by default for gate evaluation
- Tenants can optionally register known categories with specific gate configurations

## Verification Requirements by Category

| Category | automated_check | ai_critic | human_approval |
|----------|-----------------|-----------|----------------|
| Feature | Required | Required | Optional |
| Bugfix | Required | Required | Required |
| Refactor | Required | Optional | Optional |
| Documentation | Optional | Optional | Optional |
| Test | Optional | Optional | Optional |
| Infrastructure | Required | Required | Optional |
| Custom | Required | Required | Optional |

## Gate Behaviors

| Gate | Failure Status | Metadata Key |
|------|---------------|--------------|
| Plan | `blocked` | `compliance_block_reason: 'plan_evidence_missing'` |
| Verification | `blocked` | `compliance_block_reason: 'verification_incomplete'` |
| Elegance | `blocked` | `compliance_block_reason: 'elegance_review_pending'` |
| BugFix | `blocked` | `compliance_block_reason: 'bugfix_evidence_incomplete'` |

Uses existing `STATUS_BLOCKED` constant; distinguishes compliance blocks via `compliance_block_reason` in metadata.

## Bug-Fix Evidence Chain (All Required)
1. **Failing test**: test demonstrating original bug behavior
2. **Error evidence**: logs, stack traces, or error reproduction
3. **Root cause documentation**: structured explanation of why bug occurred
4. **Fix verification**: passing test proving bug no longer reproduces

## Plan Evidence (Flexible Input, Normalized Storage)
Accepted formats:
- Structured plan JSON in metadata with scope, steps, assumptions
- Plan markdown file in `tasks/` directory
- Plan-related commit or file change before implementation

All normalized to common schema:
```json
{
  "plan_format": "markdown|json|commit",
  "plan_location": "tasks/plan.md",
  "plan_hash": "sha256:...",
  "captured_at": "ISO8601"
}
```

## Elegance Checkpoint

### Static Analysis Tools
- **PHPStan**: level 6 minimum, custom rules via config
- **PHP_CodeSniffer**: PSR-12 ruleset as baseline, custom rules via config
- Both tools run and results aggregated into `elegance_findings` JSON

### LLM Self-Review
Structured JSON schema prompt evaluating:
- DRY (Don't Repeat Yourself)
- SRP (Single Responsibility Principle)
- Naming conventions
- Error handling
- Code organization

Output schema:
```json
{
  "score": 0-100,
  "violations": [
    {
      "rule": "string",
      "severity": "warning|error",
      "location": "string",
      "suggestion": "string"
    }
  ]
}
```

## Re-plan Trigger Signals
- Single trigger → advisory (log + event)
- 2+ concurrent triggers → mandatory re-plan

Signals:
- Consecutive failures (N+ failed commands in sequence)
- Scope drift (modifications outside original task scope)
- Time threshold exceeded without progress
- Explicit blocker language detected

## Lessons System

### Trigger Signals (All Enabled, Tagged by Source)
- `explicit_rejection`: user marks output as rejected
- `correction_message`: follow-up with correction keywords
- `manual_edit`: user modifies agent-generated files
- `rerun_request`: explicit re-execution request

### Storage
- Path: `tasks/lessons.md`
- Format: structured markdown with date, context, lesson, category, source_type

### Injection Strategy
- Default token budget: 2000 tokens
- Configurable via `COMPLIANCE_LESSON_TOKEN_BUDGET` env variable
- Priority when budget exceeded: recent lessons first, then category-matched lessons
- Older lessons filtered by category match to current task

## Feature Flags (Hierarchical: Global + Tenant Stricter-Only)

```php
// config/agent.php
'compliance' => [
    'enabled' => env('COMPLIANCE_ENABLED', true),
    'mode' => env('COMPLIANCE_MODE', 'advisory'), // advisory | warning | enforced
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

Tenants can only make settings stricter (lower thresholds, more required gates), never looser.

## Policy Evaluation Timing
- **Synchronous (fast)**: complexity classification, category detection
- **Asynchronous**: verification evidence collection, elegance review, lessons retrieval

## Runner Capability Handling
- Identical policy expectations for all runners (Claude, Codex, custom)
- Capability-aware enforcement only
- Graceful degradation when capability missing
- Log advisory + record in `runner_capability_downgrades` metadata

## Metadata Contract

```json
{
  "workflow_policy_version": "1.0",
  "complexity_classification": "non_trivial",
  "complexity_override": "auto",
  "task_category": "feature",
  "plan_required": true,
  "plan_completed": true,
  "plan_evidence": {"plan_format": "markdown", "plan_location": "tasks/plan.md"},
  "verification_required": true,
  "verification_completed": false,
  "verification_evidence": null,
  "elegance_review_required": true,
  "elegance_review_completed": false,
  "elegance_findings": null,
  "lessons_referenced": ["lesson-2024-01-15-001"],
  "autonomous_bugfix_mode": false,
  "bugfix_evidence": null,
  "compliance_block_reason": null,
  "gate_outcomes": {
    "plan": {"status": "passed", "reason": null},
    "verification": {"status": "pending", "reason": "awaiting test execution"},
    "elegance": {"status": "skipped", "reason": "verification not yet complete"}
  },
  "runner_capability_downgrades": []
}
```

## API Contract (Compliance Summary Object)

```json
{
  "compliance": {
    "policy_version": "1.0",
    "classification": "non_trivial",
    "category": "feature",
    "mode": "enforced",
    "gates": {
      "plan": {"required": true, "status": "passed"},
      "verification": {"required": true, "status": "blocked", "reason": "Missing test execution proof"},
      "elegance": {"required": true, "status": "pending"}
    },
    "overall_status": "blocked",
    "remediation": "Provide passing test output to unblock verification gate"
  }
}
```

## UI Requirements (MVP)
- Badge indicators: pass (green), blocked (yellow), advisory (blue), pending (gray)
- Expandable tooltip: failed gate name, specific reason, remediation guidance

## Telemetry Metrics
- Gate evaluation counts per gate type
- Would-block rate (advisory mode)
- Actual block rate (enforced mode)
- Time-to-resolution for blocked tasks
- Override frequency
- False positive reports
- Task completion rate comparison (advisory vs enforced)
- Lesson utilization rate
- Re-plan trigger frequency
- Elegance score distribution

## Rollout Phases

### Phase A: Advisory Mode
- Exit criteria: minimum 1 week + greater than 85% pass rate + operator approval

### Phase B: Build Flow Enforcement
- Enable in `ExecuteInterrogationBuildJob`
- Jobs flow remains advisory

### Phase C: Jobs Flow Enforcement
- Enable in `ExecuteAgentRunJob`

### Phase D: Full Enforcement
- Enable elegance and lessons strict checks

## Staged Rollback Mechanism
- strict to warning-only (threshold: 20% block rate)
- warning-only to advisory (threshold: 30% block rate)
- Configurable thresholds per stage
- Automated alerting when thresholds approached

### Alerting Channels
- **Dashboard**: always enabled (default)
- **Slack**: optional, configurable per tenant
- **Email**: optional, configurable per tenant
- **ComplianceEvents**: all alerts emitted for custom integrations

## Integration Points

### ExecuteAgentRunJob
- `PolicyEvaluator::evaluateBeforeStart()` (sync: classification)
- `PolicyEvaluator::evaluateBeforeComplete()` (async: verification, elegance)

### ExecuteInterrogationBuildJob
- `PolicyEvaluator::evaluateBeforeTaskStart()`
- `PolicyEvaluator::evaluateBeforeTaskComplete()`

### BuildTaskRunFactory
- Inject compliance metadata on run creation

### SystemPromptResolver
- `LessonsManager::retrieveRelevant()` for lesson injection

### FeatureFlagManager
- `ComplianceFlagResolver` integration for hierarchical flag resolution

## Test Coverage Requirements
- 100% coverage for gate decision paths (all pass/fail branches)
- 80% coverage for other compliance code

## Performance Budgets
- Simple tasks: policy evaluation under 50ms
- Non-trivial tasks: policy evaluation under 250ms (semantic lesson retrieval allowed)

## Data Retention
- Compliance metadata retained same as parent run/task history
- Pruned when parent record is deleted

## Goals

- Create app/Support/Compliance/ namespace with ComplexityClassifier, PolicyEvaluator, PlanGate, VerificationGate, EleganceGate, BugFixGate, LessonsManager, ReplanSignalDetector, ComplianceMetadataWriter, ComplianceEventEmitter, ComplianceTelemetryCollector, ComplianceFlagResolver, RunnerCapabilityChecker, and TaskCategoryResolver services
- Implement ComplexityClassifier with hybrid heuristics (file greater than 3, LOC greater than 50, directories greater than 2) plus explicit override capability using exact match and simple stemming for keyword detection
- Add TaskCategory enum with feature, bugfix, refactor, documentation, test, infrastructure, and custom values supporting ad-hoc custom categories treated as non_trivial by default
- Enforce tiered verification requirements by category: Feature requires automated_check plus ai_critic, Bugfix requires all three (automated_check, ai_critic, human_approval), Refactor requires automated_check only, Documentation and Test have no requirements
- Implement BugFixGate requiring full evidence chain: failing test, error evidence, root cause documentation, and fix verification
- Add EleganceGate with PHPStan level 6 minimum plus PHP_CodeSniffer PSR-12 baseline plus LLM self-review producing structured JSON output with score 0-100 and violations array
- Create LessonsManager with trigger signals (explicit_rejection, correction_message, manual_edit, rerun_request) tagged by source type, 2000 token default budget, and recency-weighted injection prioritizing recent lessons then category-matched
- Implement ReplanSignalDetector emitting advisory on single trigger and forcing mandatory re-plan on 2 or more concurrent triggers
- Use existing STATUS_BLOCKED constant with compliance_block_reason metadata key to distinguish compliance blocks from other blocks
- Create ComplianceFlagResolver for hierarchical config allowing global defaults with tenant override to stricter settings only
- Implement runner-agnostic policy expectations with capability-aware enforcement and graceful degradation recording downgrades in metadata
- Add compliance summary object to job/run and session API payloads including policy_version, classification, category, mode, gates status, overall_status, and remediation guidance
- Create MVP UI with badge indicators (pass green, blocked yellow, advisory blue, pending gray) and expandable tooltip showing gate status, reason, and remediation
- Implement staged rollback mechanism: strict to warning-only at 20% block rate, warning-only to advisory at 30% block rate with configurable thresholds
- Configure alerting channels: dashboard always enabled, Slack and email optional per tenant, all alerts emitted as ComplianceEvents for custom integrations
- Collect telemetry metrics: gate evaluation counts, would-block rate, actual block rate, time-to-resolution, override frequency, false positive reports, task completion rate comparison, lesson utilization rate, re-plan trigger frequency, elegance score distribution
- Integrate PolicyEvaluator with ExecuteAgentRunJob calling evaluateBeforeStart (sync) and evaluateBeforeComplete (async)
- Integrate PolicyEvaluator with ExecuteInterrogationBuildJob calling evaluateBeforeTaskStart and evaluateBeforeTaskComplete
- Integrate LessonsManager with SystemPromptResolver for lesson injection into task context
- Integrate ComplianceFlagResolver with FeatureFlagManager for hierarchical flag resolution
- Achieve 100% test coverage for gate decision paths and 80% coverage for other compliance code


## Constraints

- Must not redesign messenger workflows or delegation graph architecture
- Must not replace existing runner safety constraints in ExecuteAgentRunJob
- Must not introduce destructive database behavior
- Must preserve existing execution safety guardrails and DB protections
- No breaking API changes for existing clients - add optional compliance fields first
- Policy evaluation overhead must be under 50ms for simple tasks and under 250ms for non-trivial tasks
- Tenants can only override to stricter settings, never looser than global defaults
- Never hard-fail due to runner capability gaps - graceful degradation only
- Must maintain backward compatibility with existing run/build lifecycle
- Compliance checks must not block run dispatch for async evaluations (hybrid timing model)
- Must use existing STATUS_BLOCKED constant, not introduce new status values
- Advisory mode must run minimum 1 week before enforcement consideration
- Semantic keyword detection must use exact match plus simple stemming only, no embedding similarity
- Lesson injection must respect 2000 token default budget with COMPLIANCE_LESSON_TOKEN_BUDGET env override
- EleganceGate must use PHPStan level 6 minimum and PHP_CodeSniffer PSR-12 as baseline
- Custom task categories must be treated as non_trivial by default for gate evaluation


## Acceptance Criteria

- ComplexityClassifier correctly classifies tasks using file greater than 3, LOC greater than 50, directory greater than 2 thresholds with override support via complexity_override field
- Semantic keyword detection uses exact match first then simple stemming for configured keywords without embedding similarity
- TaskCategory enum includes feature, bugfix, refactor, documentation, test, infrastructure, custom values
- Custom categories accepted without pre-registration and treated as non_trivial by default with optional tenant registration for specific gate configurations
- Verification requirements enforced per category: Feature requires automated_check plus ai_critic, Bugfix requires all three, Refactor requires automated_check only, Documentation and Test have no requirements
- Gate failures set STATUS_BLOCKED with compliance_block_reason in metadata distinguishing compliance blocks from other blocks
- PlanGate accepts JSON, markdown file, or commit evidence and normalizes to common schema with plan_format, plan_location, plan_hash, captured_at fields
- BugFixGate validates presence of failing test, error evidence, root cause documentation, and fix verification before allowing completion
- EleganceGate runs PHPStan level 6 and PHP_CodeSniffer PSR-12 with custom rules configurable via config
- EleganceGate LLM review produces JSON with score 0-100 and violations array with rule, severity, location, suggestion fields
- ReplanSignalDetector emits advisory on single trigger and forces mandatory re-plan on 2 or more concurrent triggers
- LessonsManager captures lessons from all trigger types (explicit_rejection, correction_message, manual_edit, rerun_request) with source_type tag
- Lesson injection respects 2000 token default budget with COMPLIANCE_LESSON_TOKEN_BUDGET env override
- Lesson injection prioritizes recent lessons first then category-matched lessons when budget exceeded
- ComplianceFlagResolver merges global config with tenant overrides allowing stricter settings only
- Runner capability downgrades logged and recorded in runner_capability_downgrades metadata without failing gates
- Compliance summary object included in job/run and session API responses with policy_version, classification, category, mode, gates, overall_status, remediation fields
- UI displays badge indicators (pass green, blocked yellow, advisory blue, pending gray) with expandable tooltip showing gate status, reason, and remediation
- Staged rollback triggers at configured thresholds (20% strict-to-warning, 30% warning-to-advisory)
- Dashboard alerting always enabled, Slack and email configurable per tenant via compliance config
- All alert events emitted as ComplianceEvents for custom integrations
- Telemetry captures gate evaluation counts, would-block rate, actual block rate, time-to-resolution, override frequency, false positive reports, task completion rate comparison, lesson utilization rate, re-plan trigger frequency, elegance score distribution
- Compliance metadata persisted in run/task metadata_json and retained with parent record lifecycle
- Synchronous evaluation (classification, category detection) completes under 50ms
- Async evaluation (verification, elegance, lessons) completes under 250ms
- Unit tests achieve 100% coverage on gate decision paths (all pass/fail branches)
- Unit tests achieve 80% coverage on other compliance code
- Feature tests verify blocking behavior for missing verification evidence in both job and build flows
- Existing run lifecycle and build lifecycle behavior unaffected by compliance layer addition
- Advisory mode runs minimum 1 week before enforcement with exit criteria of greater than 85% pass rate plus operator approval

