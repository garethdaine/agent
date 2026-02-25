# Workflow Orchestration Instruction Rollout Plan

## Context
Integrate the instruction set into both orchestration surfaces:
- Scheduled and manual Agent Jobs (`AgentJob` + `ExecuteAgentRunJob` path)
- Interrogation Builds (`GenerateInterrogationBuildTasksJob` + `ExecuteInterrogationBuildJob` path)

The rollout must preserve existing run safety, queue stability, and auditability.

## Goals
- Enforce consistent planning, execution, and verification behavior across jobs and builds.
- Make instruction compliance measurable (not prompt-only best effort).
- Keep changes incremental, feature-flagged, and reversible.

## Phase 1 - Shared Policy Layer (Foundation)
- [ ] Add `WorkflowInstructionPolicy` service to define normalized rules from the instruction set.
- [ ] Add `TaskComplexityClassifier` service (`simple` vs `non_trivial`) with deterministic heuristics.
- [ ] Add config section `agent.workflow_orchestration` for rule toggles and thresholds.
- [ ] Add feature flags:
  - [ ] `agent.workflow_orchestration.enabled`
  - [ ] `agent.workflow_orchestration.enforce_verification`
  - [ ] `agent.workflow_orchestration.enforce_lessons`
- [ ] Add run/build metadata contract for compliance state:
  - [ ] `plan_required`, `plan_completed`
  - [ ] `verification_required`, `verification_evidence`
  - [ ] `elegance_review_required`, `elegance_review_completed`
  - [ ] `lessons_required`, `lessons_updated`

## Phase 2 - Plan Mode Default
- [ ] Implement pre-execution policy evaluation hook for both paths:
  - [ ] Job runs: evaluate before command launch in `ExecuteAgentRunJob`.
  - [ ] Build tasks: evaluate before task run creation in `BuildTaskRunFactory` / `ExecuteInterrogationBuildJob`.
- [ ] For non-trivial work, inject a mandatory planning checklist into task markdown/system prompt.
- [ ] Add re-plan trigger rule when failure/blocker patterns are detected ("stop and re-plan").
- [ ] Persist plan evidence in metadata (plan steps count, timestamp, source evidence).

## Phase 3 - Subagent Strategy
- [ ] Extend build-task generation prompt to require subagent usage for research/exploration on non-trivial tasks.
- [ ] Add optional per-run policy hints for subagent behavior in task markdown:
  - [ ] one task per subagent
  - [ ] parallel exploration allowed
- [ ] Parse execution events for subagent activity signals and store compliance evidence.
- [ ] Add fallback path for runners without subagent support (log advisory, do not hard-fail).

## Phase 4 - Self-Improvement Loop (Lessons)
- [ ] Standardize lessons file path to `tasks/lessons.md`.
- [ ] Add `LessonsManager` service:
  - [ ] detect user correction signals
  - [ ] append structured lesson entry (pattern, prevention rule, date, run/session id)
  - [ ] read relevant lessons at session/build start and inject into context
- [ ] Build flow integration:
  - [ ] include lesson-context block in generated task markdown when applicable
- [ ] Job flow integration:
  - [ ] include lesson-context block in run preamble when applicable

## Phase 5 - Verification Before Done
- [ ] Add `VerificationEvidenceEvaluator` service to validate evidence from run output/events.
- [ ] Define minimum evidence policy by task type:
  - [ ] code change tasks require test or lint command evidence
  - [ ] behavior changes require command output or diff proof
- [ ] Jobs: block terminal `succeeded` without required evidence when enforcement flag is on.
- [ ] Builds: block task completion and pause build with actionable remediation when evidence is missing.
- [ ] Add explicit event payloads for verification pass/fail reasons.

## Phase 6 - Demand Elegance + Autonomous Bug Fixing
- [ ] Add non-trivial checkpoint requiring "elegance pass" before completion.
- [ ] Add heuristics for hacky fix detection (temporary markers, TODO debt phrases, missing root-cause notes).
- [ ] Bug-fix mode rules:
  - [ ] on failure/bug tasks, require logs/errors/failing-test evidence before final success
  - [ ] require explicit root-cause summary in completion payload
- [ ] If elegance/root-cause checks fail, transition to blocked/failed with precise remediation guidance.

## Phase 7 - Task Management Workflow Enforcement
- [ ] Add task-file policy checks for:
  - [ ] `tasks/todo.md` includes checklist and progress updates for non-trivial work
  - [ ] review section exists in `tasks/todo.md`
- [ ] Build mode:
  - [ ] generated tasks include required task-management steps
- [ ] Jobs mode:
  - [ ] injected preamble includes task-management expectations where repository has `tasks/`

## Phase 8 - API/UI/Observability
- [ ] Extend session/job responses with compliance summary fields.
- [ ] Add event and metrics dimensions:
  - [ ] compliance gate failures by gate type
  - [ ] verification pass rate
  - [ ] re-plan frequency
  - [ ] lessons update rate after corrections
- [ ] Expose compliance state in UI:
  - [ ] Jobs monitor
  - [ ] Build panel and task details

## Phase 9 - Testing + Rollout
- [ ] Unit tests:
  - [ ] complexity classification
  - [ ] policy evaluation
  - [ ] verification evidence evaluator
  - [ ] lessons manager
- [ ] Feature tests:
  - [ ] job run blocked when required verification is missing
  - [ ] build task pauses on missing plan/verification evidence
  - [ ] lessons file updated after correction event
- [ ] Progressive rollout:
  - [ ] deploy with advisory-only mode
  - [ ] enable enforcement for builds first
  - [ ] enable enforcement for jobs after stability window

## Mapping Matrix (Instruction -> Platform)
- [ ] Plan Mode Default
  - Jobs: pre-run complexity gate + mandatory plan evidence
  - Builds: task-level plan gate + re-plan transition
- [ ] Subagent Strategy
  - Jobs: policy preamble + event evidence
  - Builds: generation prompt requirement + event evidence
- [ ] Self-Improvement Loop
  - Jobs: lesson injection + update on corrections
  - Builds: lesson injection + update on corrections
- [ ] Verification Before Done
  - Jobs: success gate tied to evidence evaluator
  - Builds: task completion gate tied to evidence evaluator
- [ ] Demand Elegance
  - Jobs: elegance checkpoint metadata + blocker on failure
  - Builds: elegance checklist in task output contract
- [ ] Autonomous Bug Fixing
  - Jobs: root-cause and failing-signal evidence gate
  - Builds: same gate in task finalization path

## Delivery Order Recommendation
- [ ] Sprint 1: Phase 1 + Phase 2 (advisory only)
- [ ] Sprint 2: Phase 5 + Phase 9 tests for builds
- [ ] Sprint 3: Phase 4 + Phase 6
- [ ] Sprint 4: jobs enforcement + UI/metrics hardening

## Review
- Scope reviewed: jobs and builds orchestration paths only.
- Non-goals: messenger workflows, delegation graph subsystem, external CI providers.
- Success criteria: all non-trivial runs/build tasks are policy-evaluated, verification-gated, and auditable.
