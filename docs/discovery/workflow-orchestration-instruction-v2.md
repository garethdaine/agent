# Requirements Discovery Summary

Session: 5

# Workflow-Orchestration Compliance Layer - Discovery Summary

## Overview
A unified orchestration compliance layer that operationalizes workflow standards across both standard job execution (`ExecuteAgentRunJob`) and interrogation build execution (`ExecuteInterrogationBuildJob`). The system enforces planning gates, verification requirements, correction-driven lessons, and elegance checkpoints with configurable progressive enforcement.

## Architecture

### Core Services
- **OrchestrationPolicyService**: Shared policy evaluation for jobs and builds
- **ComplexityClassifier**: Hybrid heuristic + override classification (file >3, LOC >50, directories >2)
- **VerificationEvidenceEvaluator**: Validates evidence presence per task category requirements
- **LessonsManager**: Append/query `tasks/lessons.md` with recency-weighted injection (2000 token budget)
- **EleganceGate**: PHPStan level 6 + PHP_CodeSniffer PSR-12 + LLM self-review (JSON schema with score 0-100)
- **ComplianceFlagResolver**: Hierarchical flags (global defaults, tenant stricter-only overrides)

### Task Categories (Fixed Enum)
- `feature`: automated_check + ai_critic verification
- `bugfix`: automated_check + ai_critic + human_approval verification
- `refactor`: automated_check only
- `docs`: no verification required
- `test`: no verification required
- `custom`: non_trivial classification default, configurable verification

### Complexity Classification
- **simple**: Below all thresholds, no plan/verification gates
- **non_trivial**: Exceeds any threshold, requires plan evidence + category-appropriate verification

### Gate Behaviors
- Failures mark status as `STATUS_BLOCKED` (existing constant) with `compliance_block_reason` in metadata
- Supports pass/blocked/advisory states
- Staged rollback: strict → warning-only → advisory

### Bug-Fix Evidence Chain
1. Failing test or error evidence
2. Root cause documentation
3. Fix implementation
4. Passing verification

### Re-Plan Triggers
- Test failure after implementation
- Scope expansion detected
- Dependency conflict discovered
- Single trigger = advisory; 2+ triggers = mandatory re-plan

### Lessons System
- Triggers: explicit user correction, failed verification retry, task rejection, manual lesson add
- Semantic keyword detection with exact match + simple stemming
- Recency-weighted injection with 2000 token default budget
- Source tagged by trigger type

### Feature Flags
- Global defaults in `config/agent.php`
- Tenant overrides stricter-only (cannot weaken global policy)
- Per-flag: `compliance.plan_gate_enabled`, `compliance.verification_gate_enabled`, `compliance.elegance_gate_enabled`, `compliance.lessons_enabled`

### Policy Evaluation Timing
- Synchronous: complexity classification (fast pre-checks)
- Asynchronous: detailed verification evidence evaluation

## Metadata Contract
```json
{
  "workflow_policy_version": "string",
  "complexity_classification": "simple|non_trivial",
  "task_category": "feature|bugfix|refactor|docs|test|custom",
  "plan_required": "boolean",
  "plan_completed": "boolean",
  "plan_evidence": "object|null",
  "verification_required": "boolean",
  "verification_completed": "boolean",
  "verification_evidence": "object|null",
  "elegance_review_required": "boolean",
  "elegance_review_completed": "boolean",
  "elegance_findings": "object|null",
  "lessons_required": "boolean",
  "lessons_updated": "boolean",
  "lessons_referenced": "array",
  "autonomous_bugfix_mode": "boolean",
  "root_cause_evidence": "object|null",
  "compliance_block_reason": "string|null"
}
```

## API Contract
- Extend job/run and interrogation session payloads with `compliance_summary` object
- Fields: `status` (pass|blocked|advisory), `gates` (array of gate results), `remediation` (string|null)
- Backward compatible: new fields are optional additions

## UI Requirements (MVP)
- Badge/icon indicators on job/task cards showing pass/blocked/advisory status
- Actionable remediation reason when gate blocks completion
- Dashboard alerting for compliance events (optional Slack/email integration)

## Telemetry Metrics
- Gate pass/fail rates by gate type
- Verification completion rates by category
- Lesson utilization rate
- Re-plan trigger frequency
- Elegance score distribution
- Block duration metrics

## Rollout Phases
1. **Phase A**: Advisory-only mode, collect telemetry, no blocking (minimum 1 week)
2. **Phase B**: Enforce verification and plan gates in build flow first (>85% pass rate threshold)
3. **Phase C**: Enforce in jobs flow after stability window
4. **Phase D**: Enable elegance and lessons strict checks with operator approval

## Integration Points
- `ExecuteAgentRunJob`: Policy evaluation before run start
- `ExecuteInterrogationBuildJob`: Policy evaluation before task run creation
- `BuildTaskRunFactory`: Inject compliance metadata into run creation
- `VerificationPipeline`: Add compliance verification steps
- `InterrogationBuildTask`: Add `task_category` column (migration required)
- `FeatureFlagManager`: Extend for compliance flag resolution

## Database Changes
- Add `task_category` column to `interrogation_build_tasks` table
- Extend `metadata_json` schemas for compliance fields
- No schema changes to existing job/run tables (metadata_json extension only)

## Goals

- FR-01: Implement shared OrchestrationPolicyService for jobs and builds with unified policy evaluation interface
- FR-02: Implement ComplexityClassifier with hybrid heuristic classification (file >3, LOC >50, directories >2) plus explicit override support
- FR-03: Integrate policy evaluation into ExecuteAgentRunJob before run start with synchronous classification
- FR-04: Integrate policy evaluation into ExecuteInterrogationBuildJob before task run creation
- FR-05: Implement plan evidence requirement for non_trivial tasks with normalized storage schema
- FR-06: Implement re-plan signal detection (test failure, scope expansion, dependency conflict) with advisory/mandatory triggers
- FR-07: Implement subagent usage guidance injection for complex tasks where runner supports capability
- FR-08: Implement capability-aware degradation to advisory mode when runner lacks subagent support
- FR-09: Standardize lessons file path as tasks/lessons.md with structured append/query operations
- FR-10: Implement LessonsManager with structured lesson append flow on correction events (user correction, failed retry, task rejection, manual add)
- FR-11: Implement recency-weighted lesson injection into run/task context with 2000 token budget
- FR-12: Implement VerificationEvidenceEvaluator with category-specific requirements (feature: automated+ai_critic, bugfix: all three, refactor: automated, docs/test: none)
- FR-13: Implement STATUS_BLOCKED gating with compliance_block_reason metadata discrimination
- FR-14: Implement EleganceGate with PHPStan level 6 + PHP_CodeSniffer PSR-12 + LLM self-review (score 0-100 JSON schema)
- FR-15: Implement bug-fix mode requiring failing test + error evidence + root cause documentation + fix verification
- FR-16: Persist compliance state in run/task/session metadata_json with normalized schema
- FR-17: Emit ComplianceEvents for pass/fail/advisory outcomes with structured reason codes
- FR-18: Expose compliance_summary in API payloads for jobs/build UI consumption
- FR-19: Implement observability metrics for gate failures, verification rates, lesson utilization, elegance score distribution
- FR-20: Implement hierarchical feature flags with global defaults and tenant stricter-only overrides


## Constraints

- Preserve existing execution safety guardrails and DB protections in ExecuteAgentRunJob and ExecuteInterrogationBuildJob
- No breaking API changes for existing clients - compliance fields are optional additions only
- Policy evaluation overhead must be minimal relative to run startup - synchronous classification, async verification
- Every enforcement decision must be inspectable after execution via metadata_json
- All new checks must be configurable via config/agent.php and environment flags
- Reuse existing STATUS_BLOCKED constant rather than introducing new status values
- Tenant feature flag overrides can only make policy stricter, never weaker than global defaults
- Runner capability detection must gracefully degrade to advisory mode without hard failures
- Lessons injection must respect 2000 token budget to avoid context overflow
- EleganceGate LLM review must use structured JSON schema output (score 0-100, violations array)
- Static analysis tools pinned to PHPStan level 6 and PHP_CodeSniffer PSR-12 ruleset
- Keyword detection for lessons uses exact match plus simple stemming only - no NLP dependencies
- Custom task category defaults to non_trivial classification and inherits feature verification requirements
- Rollback mechanism must support staged transition: strict → warning-only → advisory
- Advisory phase minimum duration of 1 week before enforcement promotion
- Compliance retention follows parent record lifecycle - no separate pruning policy


## Acceptance Criteria

- OrchestrationPolicyService can evaluate policy for both AgentJobRun and InterrogationBuildTask models
- ComplexityClassifier correctly identifies non_trivial tasks when file >3 OR LOC >50 OR directories >2
- ComplexityClassifier supports explicit complexity override via metadata or API parameter
- ExecuteAgentRunJob calls policy evaluation synchronously before run execution begins
- ExecuteInterrogationBuildJob calls policy evaluation before task run creation in BuildTaskRunFactory
- Non-trivial tasks in both flows require plan evidence before completion gate passes
- Plan evidence accepts markdown, structured JSON, or reference to external document and normalizes to common schema
- Re-plan advisory triggers on single drift signal (test failure, scope expansion, dependency conflict)
- Re-plan mandatory triggers when 2+ drift signals detected in same task
- Subagent guidance injected into system prompt when runner reports subagent capability
- Advisory logged without blocking when runner lacks subagent capability
- Lessons appended to tasks/lessons.md with structured format including source trigger type
- Lessons queried with semantic keyword detection using exact match plus simple stemming
- Lessons injected into task context with recency weighting and 2000 token budget cap
- VerificationEvidenceEvaluator enforces category-specific requirements: feature (automated+ai_critic), bugfix (all three), refactor (automated), docs/test (none)
- STATUS_BLOCKED applied when verification gate fails with compliance_block_reason in metadata
- EleganceGate runs PHPStan level 6 and PHP_CodeSniffer PSR-12 for static analysis phase
- EleganceGate LLM review returns JSON with score (0-100) and violations array
- Bug-fix mode requires failing test evidence, error evidence, root cause documentation, and passing verification
- Compliance state persisted in metadata_json with all fields from metadata contract
- ComplianceEvents emitted for pass, blocked, and advisory outcomes with structured reason codes
- API payloads include compliance_summary object with status, gates array, and remediation string
- Dashboard displays badge/icon indicators for pass/blocked/advisory status on job/task cards
- Telemetry captures gate pass/fail rates, verification completion rates, lesson utilization, elegance score distribution
- Feature flags resolve hierarchically with global defaults and tenant stricter-only overrides
- Advisory mode collects telemetry without blocking execution
- Enforcement promotion requires minimum 1 week advisory duration plus >85% pass rate plus operator approval
- Staged rollback from strict to warning-only to advisory functions correctly with configurable thresholds
- Existing run lifecycle and build lifecycle behavior unchanged when compliance flags disabled
- Migration adds task_category column to interrogation_build_tasks table with default value

