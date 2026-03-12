# Workflow Interrogator — Feature Brief

## Status
- Draft
- Category: additive standalone feature
- Interview/showcase priority: highest

## Intent

Build a new standalone product surface that interrogates operational workflows until ambiguity is eliminated, then produces an actionable implementation brief, pilot recommendation, and tooling approach.

This is not a modification of the existing Requirements Discovery tool. It may reuse shared infrastructure patterns, but it must ship as a separate bounded feature with its own route, APIs, storage, prompts, and UI.

## Why This Exists

AgentOps already demonstrates orchestration, execution, and verification. What it does not yet demonstrate explicitly is founder-grade workflow discovery for operations-heavy businesses.

This feature is designed to showcase:
- commercial discovery thinking
- ruthless ambiguity elimination
- AI-assisted but structured requirements capture
- operational transformation framing, not just software implementation
- tool-agnostic recommendation logic

This maps directly to the No Code Sage model of workflow discovery, co-design, pilot scoping, and pragmatic tool selection.

## Core Principle

The interrogation loop is **finite per round, not finite overall**.

The system may generate a bounded set of questions for the current round, but it must continue generating new rounds until:
- assumptions are exhausted
- contradictions are resolved
- dependencies are unblocked
- required concern areas are covered
- the operator explicitly confirms the summary is complete

This feature should follow the spirit of the provided `Requirements Interrogator` process:
- classify interrogation type
- gather context
- interrogate in batches
- summarize findings
- continue if ambiguity remains
- only then generate the implementation/action plan

## Additive-Only Guardrail

Must not modify:
- existing Requirements Discovery routes, controllers, jobs, prompts, UI flows, or current behavior
- current interrogation session tables or feature flags in a way that changes live behavior
- existing build-task generation or approval workflows

Allowed:
- reuse of shared UI components, auth, event broadcasting patterns, export helpers, runner adapters, and common layout primitives
- new database tables and new bounded services
- new route group and new frontend pages

## Product Shape

### Primary Surface
- New route: `/tools/workflow-interrogator`
- New create flow
- New wizard surface
- New summary/action-plan view

### Session Model
- Separate session family from existing interrogation
- Recommended naming:
  - `WorkflowInterrogationSession`
  - `WorkflowInterrogationEvent`
  - `WorkflowInterrogationSetting`

### Output
- operational summary
- unresolved ambiguity report
- recommended pilot candidates
- recommended implementation/tooling path
- action plan for approval

## Target Users

### Primary
- founder/operator running discovery with a prospective or internal client
- consultant identifying automation opportunities
- engineer/product operator translating ops pain into delivery scope

### Secondary
- internal operator wanting a pre-implementation discovery artifact
- AI-assisted consultant preparing a workshop or scoping session

## Inputs

### Required
- company name
- company description
- workflow title or initiative title
- current brief / stated problem
- target team(s)
- current tools / systems involved

### Strongly Recommended
- workflow steps as currently understood
- team roles / actors
- volume / frequency
- current pain points
- known constraints
- desired business outcome

### Optional
- linked docs
- codebase/project directory
- screenshots / diagrams
- existing SOP text

## Interrogation Modes

### Mode A: New Workflow / Initiative
- User has a brief and wants exhaustive workflow discovery.

### Mode B: Existing System / Broad Operational Concern
- User wants to interrogate an existing workflow estate, architecture boundary, or transformation area.

## End-to-End Flow

### Phase 0: Scope
- Ask what kind of interrogation this is.
- Capture brief and operating context.

### Phase 1: Discovery
- If a codebase/project directory exists, run silent discovery.
- Build internal context from docs, structure, manifests, and interfaces.
- Do not expose raw discovery noise in the UX.

### Phase 2: Interrogation Round
- Generate a finite batch of high-signal questions.
- Batch can contain mixed question types:
  - single choice
  - multi choice
  - free text
  - structured list
- Present them in a stepped wizard.
- Do not generate questions one-by-one in the UI.

### Phase 3: Ambiguity Analysis
- Evaluate answers for:
  - unresolved assumptions
  - contradictions
  - missing dependencies
  - unaddressed concern areas
  - vague answers requiring pushback

### Phase 4: Next Round
- Generate a new finite batch based on remaining gaps.
- Repeat until the ambiguity engine determines closure criteria are met.

### Phase 5: Structured Summary
- Summarize by concern area:
  - business goals
  - workflow actors
  - systems and data
  - operational pain points
  - constraints
  - risks
  - open questions

### Phase 6: Action Plan
- Only after ambiguity is closed or explicitly accepted.
- Generate:
  - recommended pilot wedge
  - recommended implementation shape
  - recommended tooling path
  - phased rollout
  - validation approach

## Functional Requirements

### Session and State
- Create, resume, pause, archive, delete standalone workflow-interrogation sessions.
- Support session phases independent from existing discovery phases.
- Persist all answers, round outputs, ambiguity findings, and summaries.

### Batch-Based Wizard
- Render a full finite question batch at once.
- Support stepping through one question at a time without re-calling the model between each step.
- Preserve question order and dependency gating inside a batch.

### Ambiguity Engine
- Must track:
  - covered concern areas
  - unresolved ambiguity items
  - contradiction flags
  - vague-answer flags
  - dependency blockers
- Must be able to say why another round is needed.

### Pushback Logic
- If an answer is vague, the system must flag it and generate clarifying follow-up questions later.
- Example: "something modern" should not satisfy tooling requirements.

### Summary and Approval
- Summary must be confirmable and revisable.
- Action plan must be generated only after summary confirmation or explicit operator override.

### Export
- Export summary and action plan as markdown to `docs/plans/` or a dedicated `docs/workflow-interrogations/` path.
- Include metadata about date, context, and unresolved accepted risks.

## UX Requirements

### Create Screen
- concise briefing intake
- workflow/company context inputs
- optional project directory
- optional linked systems and tools

### Wizard Screen
- left rail: round progress, concern coverage, ambiguity count
- center: stepped batch Q&A interface
- right rail: context snapshot, known assumptions, contradiction alerts

### Summary Screen
- structured findings by domain
- list of residual open questions
- clear status:
  - ready for plan
  - needs another round

### Plan Screen
- pilot wedge
- tooling recommendation
- rollout suggestion
- risk notes

## Suggested Outputs

### Mandatory
- workflow summary
- ambiguity ledger
- action plan

### Strongly Recommended
- pilot ranking
- tooling recommendation
- risk and exception notes

## Architecture Direction

### Backend
- new bounded controllers under a dedicated namespace
- new job pipeline or synchronous orchestration service for round generation
- reusable runner adapter abstraction allowed, but no changes to existing discovery runtime

### Frontend
- new pages under `resources/js/Pages/Tools/WorkflowInterrogator/`
- may reuse generic card, stepper, badge, input, modal, and export components

### Storage
- separate tables preferred
- do not overload existing `interrogation_sessions`

## AI Runner Requirement

During AI-assisted implementation and testing of this feature, use the **Codex CLI runner**.

Requirements:
- test/build sessions for this feature must default to `runner_type=codex`
- verification during development should exercise Codex-oriented prompt and execution paths
- do not rely on Claude-only behavior when validating the feature

This requirement is for the build/test workflow of the feature, not a permanent restriction on future end-user runner support.

## Acceptance Criteria

- A user can create a standalone workflow-interrogation session without touching existing discovery features.
- The feature generates a finite question batch for the current round.
- The system continues generating new rounds until closure criteria are met.
- The feature can explain why another round is required.
- The final summary is structured by concern area and is reviewable.
- The final action plan includes recommended implementation shape and tooling direction.
- Existing Requirements Discovery behavior remains unchanged.

## Non-Goals

- modifying current Requirements Discovery
- replacing current feature-planning flows
- writing code automatically for the discovered workflow in v1
- CRM/sales pipeline management
- named vendor pricing or procurement automation

## Risks

- ambiguity closure may drift into infinite follow-up if closure criteria are weak
- poor batch construction may overwhelm the user
- mixing workflow discovery and implementation planning too early may reduce rigor

## Recommended V1 Cut

- standalone intake
- iterative batch interrogation
- ambiguity engine
- structured summary
- action plan
- markdown export

## Recommended Demo Narrative

"This is a standalone workflow discovery system for ops-heavy businesses. It interrogates in finite batches, tracks unresolved ambiguity, keeps going until assumptions are eliminated, and then produces an implementation-ready action plan with recommended tooling."
