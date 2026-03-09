# Requirements Discovery Summary

Session: 15

# Atom-of-Thought (AoT) Reasoning Integration for Agent

## Executive Summary

Integrate an AoT-style decomposition workflow into Agent as an orchestration enhancement, not a full paper-faithful runtime rewrite. The codebase already has strong reasoning primitives (STAR preamble, reasoning capture, targeted retry, trust scoring), but planning and build-task generation are still mostly linear outputs. AoT should be introduced first where it has highest leverage: planning and task generation.

Recommendation:
- Ship **AoT-lite** in interrogation planning + build-task generation first.
- Keep summary phase mostly unchanged (synthesis only).
- Apply AoT at execution time only when a task is detected as compound.
- Run in shadow mode first with A/B instrumentation before any gating.

## Legitimacy and Product Position

- The AoT concept is legitimate enough to evaluate, but social-post benchmark claims should be treated as unverified marketing unless reproduced in our workload.
- We should not replace existing STAR workflow. STAR remains useful for run-time articulation and failure analysis; AoT complements it upstream by improving decomposition quality.
- Success criteria should be internal and measurable (re-plan rate, build-task regeneration rate, first-pass completion, blocker frequency), not external benchmark numbers.

## Current Codebase Baseline (Verified)

### Existing strengths
- STAR runtime primitives already exist:
  - `app/Support/Agent/StarPreambleGenerator.php`
  - `app/Support/Agent/ReasoningStepParser.php`
  - `app/Support/Agent/TargetedRetryService.php`
  - `app/Jobs/ExecuteAgentRunJob.php`
- Planning pipeline exists with strict payload control:
  - `app/Jobs/ExecuteInterrogationPlanJob.php`
  - `app/Support/Interrogation/PlanPayloadGuard.php`
  - `app/Support/Interrogation/PlanPayloadNormalizer.php`
  - `app/Support/Interrogation/Adapters/ClaudeAdapter.php`
  - `app/Support/Interrogation/Adapters/CodexAdapter.php`
- Build-task generation pipeline exists:
  - `app/Support/Interrogation/BuildTaskGenerator.php`
  - `app/Jobs/GenerateInterrogationBuildTasksJob.php`
- Build execution + compliance gates exist:
  - `app/Jobs/ExecuteInterrogationBuildJob.php`
  - `app/Support/Interrogation/BuildTaskRunFactory.php`
  - `app/Support/Compliance/OrchestrationPolicyService.php`

### Gaps relevant to AoT
- Plan schema has no explicit machine-readable decomposition graph (atoms/dependencies/validation per atom).
- Build tasks are sequence-based, not explicitly linked to atomic reasoning units.
- Execution is currently single-active-task orchestration in build flow; no atom-level parallel scheduling.
- No metrics currently distinguish decomposition quality from execution quality.

## Where AoT Fits in the Workflow

### 1) Planning (primary target)
- Best fit. AoT decomposition should happen when generating `plan_json`.
- Output should include atomic components with dependencies and validation criteria.

### 2) Task generation (secondary target, required for value)
- Strong fit. Build tasks should be derived from atoms and preserve dependency links.
- Enables cleaner retries and better blocker isolation.

### 3) Task completion (conditional)
- Only trigger AoT-at-execution for compound tasks (heuristic-based).
- Keep simple tasks linear to avoid token/cost/latency overhead.

### 4) Summary (minimal)
- Keep as synthesis-oriented; add optional atom traceability section only.

## Proposed AoT-Lite Architecture

## A. Plan Contract Extension (non-breaking)

Extend plan output schema with optional atom structures.

Proposed additions to `plan_json`:
- `atoms`: array of atomic work units
- `atom_edges`: dependency edges (`from`, `to`)
- `atom_validation_strategy`: per-atom verification plan

Atom shape:
- `id` (string, stable within plan)
- `name` (string)
- `objective` (string)
- `inputs` (string[])
- `outputs` (string[])
- `depends_on` (string[])
- `validation_checks` (string[])
- `risk_level` (`low|medium|high`)
- `parallelizable` (boolean)

Integration points:
- `app/Jobs/ExecuteInterrogationPlanJob.php` (`buildPlanningPrompt`)
- `app/Support/Interrogation/Adapters/ClaudeAdapter.php` (`planSchema`)
- `app/Support/Interrogation/Adapters/CodexAdapter.php` (`planSchema`)
- `app/Support/Interrogation/PlanPayloadNormalizer.php`
- `app/Support/Interrogation/PlanPayloadGuard.php`

## B. Build Task Generation from Atoms

Map each atom to one or more executable build tasks while preserving traceability.

Proposed build-task metadata additions (`InterrogationBuildTask.metadata_json`):
- `atom_id`
- `atom_depends_on`
- `atom_validation_checks`
- `atom_parallelizable`

Integration points:
- `app/Support/Interrogation/BuildTaskGenerator.php` prompt + parse normalization
- `app/Jobs/GenerateInterrogationBuildTasksJob.php` persistence logic

## C. Execution-Time Compound Task Handling (optional flag)

If a task is detected as compound at run-time, prepend a short AoT decomposition directive before existing STAR preamble.

Directive pattern:
- Decompose into independent components.
- Validate each component explicitly.
- Synthesize only after component-level checks.

Integration points:
- `app/Support/Agent/StarPreambleGenerator.php` or a new `AotPreambleGenerator`
- `app/Jobs/ExecuteAgentRunJob.php` injection order + metadata flags

## D. Observability + Experimentation

Add AoT-specific metadata and analytics dimensions.

Run/session metadata (examples):
- `aot.enabled`
- `aot.mode` (`shadow|advisory|enforced`)
- `aot.atom_count`
- `aot.dependency_edge_count`
- `aot.compound_task_detected`

Primary evaluation metrics:
- Plan revision rate
- Build-task regeneration rate
- Build blocker rate (`failed` + `blocked`)
- First-pass build task success ratio
- Retry chain depth
- Median time-to-completion per build task (for relative comparison only)

## E. Feature Flags and Config

Add config gates under `config/agent.php`:
- `interrogation.aot.enabled`
- `interrogation.aot.plan_enabled`
- `interrogation.aot.build_task_generation_enabled`
- `interrogation.aot.execution_compound_only`
- `interrogation.aot.mode` (`shadow|advisory|enforced`)
- `interrogation.aot.ab_test_enabled`
- `interrogation.aot.ab_test_treatment_percent`

Optional managed flags in `FeatureFlagManager` for UI/runtime toggles if operator control is required.

## Rollout Strategy

1. Phase A: Shadow mode
- Generate atom fields but do not gate behavior.
- Keep existing plan/build behavior as source of truth.

2. Phase B: Advisory mode
- Surface warnings when plan lacks valid atoms/dependencies for non-trivial work.
- Do not block execution.

3. Phase C: Enforced for planning/task generation only
- Require valid atom graph for non-trivial feature sessions.
- Keep execution-time AoT optional and compound-only.

4. Phase D: Optimization
- Use metrics to refine compound-task detection and prompt templates.
- Evaluate whether selective parallel execution is worth introducing later.

## Goals

- FR-01: Add optional AoT atom graph fields to plan schema for both Claude and Codex adapters.
- FR-02: Extend planning prompt to require independent component decomposition with explicit dependencies.
- FR-03: Normalize and persist atom graph fields in `plan_json` without breaking existing consumers.
- FR-04: Validate minimum atom graph quality for non-trivial plans in guard logic.
- FR-05: Extend build-task generation prompt to map tasks from atoms with traceability metadata.
- FR-06: Persist `atom_id` and atom validation metadata on each generated build task.
- FR-07: Emit AoT telemetry metadata in session/build/run lifecycle payloads.
- FR-08: Add AoT feature flags and A/B split controls in config.
- FR-09: Provide optional execution-time AoT decomposition for compound tasks only.
- FR-10: Preserve STAR reasoning capture and targeted retry compatibility.

## Constraints

- No breaking changes to existing interrogation API payloads.
- AoT fields must be additive/optional in `plan_json` and task metadata.
- Keep current safety constraints and compliance gates intact.
- Do not force AoT on simple tasks.
- Avoid large latency regressions in planning/build generation.
- Maintain runner parity (Claude/Codex) at schema and prompt levels.

## Acceptance Criteria

- Plan generation can return valid atom graph fields under schema validation.
- Existing plans without atoms remain valid and render correctly.
- Build tasks generated in AoT mode include `atom_id` metadata.
- Build execution continues to operate unchanged when AoT is disabled.
- AoT mode can be toggled via config/flags without deploy-time code edits.
- A/B cohorts are recorded and queryable in metadata.
- Telemetry exposes decomposition-quality vs execution-quality signals.
- Existing STAR tests remain green and AoT tests cover planning + build generation deltas.

## Test Strategy

- Unit tests:
  - Plan schema/normalizer/guard atom handling.
  - BuildTaskGenerator prompt construction includes AoT directives when enabled.
  - Compound-task detector logic for execution-time AoT.
- Feature tests:
  - Plan generation stores atom metadata.
  - Build-task generation persists atom linkage metadata.
  - AoT flags correctly alter behavior in shadow/advisory/enforced modes.
- Regression tests:
  - Existing STAR reasoning capture and targeted retry behavior unchanged.

## Risks and Mitigations

- Risk: Prompt bloat reduces output quality.
  - Mitigation: keep AoT prompts concise, scoped to non-trivial sessions only.
- Risk: Over-decomposition increases task count and orchestration overhead.
  - Mitigation: enforce atom quality rules and cap atom count range.
- Risk: False confidence from external benchmark claims.
  - Mitigation: gate decisions on internal A/B metrics only.

## Non-Goals (Initial Slice)

- Full Markov AoT runtime implementation from research paper.
- DAG-parallel build-task scheduler changes.
- Replacing STAR with AoT.
- New UI visualization of atom graph before telemetry confirms value.

## Open Questions

- Should atom graph be shown in UI during planning review, or stay metadata-only initially?
- Do we require explicit atom-level acceptance criteria before plan approval?
- What threshold defines a “compound task” for execution-time AoT injection?
- Should delegation graph creation optionally consume plan atoms directly in a later phase?
