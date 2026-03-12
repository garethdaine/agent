# Quick-Wins / Pilot Finder — Feature Brief

## Status
- Draft
- Category: additive standalone feature
- Interview/showcase priority: high

## Intent

Build a standalone tool that ranks operational workflows by pilot suitability and near-term ROI, helping identify the best first wedge for automation or internal tooling.

## Why This Exists

This maps directly to the “quick wins first” consulting model:
- find the pilot with the best combination of value, speed, and feasibility
- avoid starting with the most politically risky or technically tangled workflow

## Additive-Only Guardrail

Must be a new feature. It must not alter existing discovery, planning, or build execution surfaces.

## Core Product Shape

### New Route
- `/tools/pilot-finder`

### Inputs
- a set of candidate workflows
- current pain severity
- time spent
- frequency
- staff involvement
- error rate
- data availability
- systems involved
- exception burden
- organizational readiness

### Outputs
- ranked pilot list
- score by dimension
- recommended first pilot
- reasons to defer lower-ranked candidates

## Primary Scoring Dimensions

- business pain
- time reclaimed potential
- implementation speed
- data readiness
- process stability
- exception rate
- integration complexity
- stakeholder readiness
- rollout risk
- measurable outcome clarity

## Functional Requirements

- Allow multiple workflow candidates in one session.
- Score and rank workflows.
- Show which dimensions help or hurt each workflow.
- Generate a concise “start here first” recommendation.
- Generate a “do later” list with reasons.

## UX Requirements

- candidate workflow list editor
- per-workflow scoring inputs
- ranked results board
- pilot recommendation card
- markdown export

## Architecture Direction

- standalone scoring engine
- no need to modify current discovery models
- reusable export/UI primitives allowed

## AI Runner Requirement

During AI-assisted implementation and testing of this feature, use the **Codex CLI runner**.

Requirements:
- Codex should be the default AI runner used for build/test sessions
- tests should validate ranking behavior and deterministic ordering under tied or near-tied conditions

## Acceptance Criteria

- A user can compare multiple workflows in one session.
- The tool produces a ranked pilot order.
- The top recommendation includes business and delivery rationale.
- Existing features remain unchanged.

## Non-Goals

- full business case modeling
- live finance/payback integration
- automatic implementation planning in v1

## Recommended V1 Cut

- manual workflow entry
- deterministic scoring
- ranked output
- recommendation summary

## Demo Narrative

"This feature helps choose the right first wedge. It scores candidate workflows by value, feasibility, and readiness so the team starts where momentum is most likely."
