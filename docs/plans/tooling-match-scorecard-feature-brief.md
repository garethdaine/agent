# Tooling Match Scorecard — Feature Brief

## Status
- Draft
- Category: additive standalone feature
- Interview/showcase priority: high

## Intent

Build a standalone scoring tool that evaluates a workflow or initiative against multiple implementation approaches and recommends the most appropriate delivery stack with transparent tradeoffs.

This should help answer:
- low-code or custom code?
- internal app or automation first?
- where does AI belong?
- what should be avoided?

## Why This Exists

This demonstrates pragmatic engineering judgment rather than framework attachment.

It maps well to a consulting/delivery business that chooses among platforms such as:
- Glide
- Airtable
- n8n
- Supabase
- Xano
- custom TypeScript/Node/Laravel/Vue/React implementations

## Additive-Only Guardrail

This must be a new feature surface. It must not alter current discovery or planning flows.

Allowed:
- standalone route and standalone storage
- optional consumption of outputs from other additive tools

Not allowed:
- changing existing interrogation or build-task behavior

## Core Product Shape

### New Route
- `/tools/tooling-match-scorecard`

### Primary Inputs
- workflow brief
- complexity level
- process variability
- exception frequency
- compliance requirements
- integration footprint
- user count
- internal maintainers
- expected change velocity
- reporting/analytics needs
- AI/automation requirements

### Primary Outputs
- ranked tooling options
- rationale by dimension
- red flags and disqualifiers
- recommendation summary

## Use Cases

- consultant deciding whether a workflow should be built in Glide, n8n, Supabase, or custom code
- operator comparing delivery paths before scoping a pilot
- engineer documenting why a custom build is or is not justified

## Scoring Dimensions

### Mandatory Dimensions
- workflow complexity
- exception handling burden
- data model complexity
- integration complexity
- compliance/security sensitivity
- internal maintenance readiness
- speed-to-value requirement
- customization pressure
- AI/human-in-the-loop fit
- cost sensitivity

### Optional Dimensions
- offline/mobile needs
- auditability requirements
- multi-tenant needs
- future extensibility expectations

## Scoring Model

Each candidate stack should receive:
- fit score
- explanation
- confidence level
- disqualifiers
- “best used when” note
- “avoid when” note

## Functional Requirements

- Support scoring a workflow against a configurable set of candidate tool stacks.
- Support operator-defined candidate sets in addition to defaults.
- Produce a transparent explanation for each score.
- Flag disqualifying conditions clearly rather than burying them in narrative.
- Allow export as markdown.

## UX Requirements

- brief intake form
- dimension-by-dimension input
- results table with expandable rationale
- recommendation card
- downloadable scorecard output

## Architecture Direction

- standalone service for scoring logic
- config-driven candidate definitions
- additive controllers/pages only
- no dependency on existing discovery runtime required for v1

## AI Runner Requirement

During AI-assisted implementation and testing of this feature, use the **Codex CLI runner**.

Requirements:
- implementation verification should be exercised through Codex-oriented execution paths
- test cases should validate deterministic scorecard generation and explanation output

## Acceptance Criteria

- A user can input a workflow profile and receive ranked tooling recommendations.
- The output explains why the top recommendation wins.
- The output highlights where a candidate stack is a poor fit.
- Existing features remain unchanged.

## Non-Goals

- live vendor pricing integration
- automated procurement recommendation
- replacing human engineering judgment
- generating implementation code directly

## Recommended V1 Cut

- default candidate stacks
- fixed scoring rubric
- ranked output
- exportable markdown summary

## Demo Narrative

"This tool turns workflow context into a transparent tooling recommendation. Instead of saying ‘use platform X because it’s trendy’, it scores the workflow against delivery constraints and explains the tradeoffs."
