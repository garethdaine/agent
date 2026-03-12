# Ops Workflow Blueprint Generator — Feature Brief

## Status
- Draft
- Category: additive standalone feature
- Interview/showcase priority: medium-high

## Intent

Build a standalone generator that converts workflow discovery inputs into a structured operational blueprint describing current state, target state, actors, systems, handoffs, and rollout phases.

## Why This Exists

This gives a clean bridge between discovery and implementation without jumping straight into task execution. It demonstrates system design thinking in operational language.

## Additive-Only Guardrail

New feature only. No changes to existing discovery/planning/build features.

## Core Product Shape

### New Route
- `/tools/workflow-blueprint`

### Inputs
- workflow brief
- actors and teams
- source systems
- handoffs
- pain points
- desired future state
- constraints

### Outputs
- current-state blueprint
- target-state blueprint
- system/data interaction map
- phased rollout blueprint
- assumptions and risks

## Functional Requirements

- Capture current-state workflow shape.
- Capture desired future-state workflow shape.
- Identify actors, systems, data movements, and handoff points.
- Generate a structured blueprint artifact suitable for planning or client review.

## UX Requirements

- workflow setup form
- guided current-state capture
- target-state capture
- generated blueprint view
- export to markdown

## Architecture Direction

- standalone bounded module
- may reuse shared markdown export and card/layout components
- diagram generation may be markdown-first in v1 rather than full visual graphing

## AI Runner Requirement

During AI-assisted implementation and testing of this feature, use the **Codex CLI runner**.

Requirements:
- Codex should be used for implementation and verification sessions
- tests should verify blueprint section completeness and deterministic export structure

## Acceptance Criteria

- A user can input workflow context and receive a structured current/target-state blueprint.
- The blueprint includes actors, systems, handoffs, risks, and rollout phases.
- Existing features are unaffected.

## Non-Goals

- BPMN-grade workflow modeling in v1
- live diagram editing
- direct code generation

## Recommended V1 Cut

- current-state capture
- target-state capture
- markdown blueprint generation
- rollout section

## Demo Narrative

"This converts discovery into a clean operational blueprint. It helps move from a vague workflow problem to a structured design artifact before any build work begins."
