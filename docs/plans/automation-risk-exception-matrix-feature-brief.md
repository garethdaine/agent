# Automation Risk & Exception Matrix — Feature Brief

## Status
- Draft
- Category: additive standalone feature
- Interview/showcase priority: high

## Intent

Build a standalone assessment tool that identifies where an automation design is likely to break, where human review is needed, and what exception handling model is required before implementation.

## Why This Exists

This demonstrates mature enterprise delivery thinking:
- automation is not just happy-path flow mapping
- exception handling and approvals often determine real-world success
- AI/human-in-the-loop design must be explicit, not implied

## Additive-Only Guardrail

This must be a new feature and must not change current discovery or build orchestration behavior.

## Core Product Shape

### New Route
- `/tools/automation-risk-matrix`

### Inputs
- workflow definition
- current systems
- trigger types
- downstream actions
- exception examples
- approval requirements
- compliance context
- failure tolerance

### Outputs
- risk matrix
- exception catalogue
- approval checkpoints
- fallback recommendations
- human-in-the-loop recommendations

## Functional Requirements

- Capture workflow triggers, actions, decisions, and failure points.
- Classify risks by type:
  - data quality
  - integration
  - compliance
  - operational dependency
  - AI judgment risk
  - user harm / downstream impact
- Identify required human checkpoints.
- Generate a structured matrix that can feed later planning.

## UX Requirements

- intake for workflow and risk context
- matrix/table output
- exception detail drilldown
- approval checkpoint summary
- exportable report

## Architecture Direction

- standalone risk-analysis service
- may later plug into Workflow Interrogator outputs
- no dependency on current interrogation runtime required in v1

## AI Runner Requirement

During AI-assisted implementation and testing of this feature, use the **Codex CLI runner**.

Requirements:
- Codex should drive build/test validation sessions
- automated tests should verify classification, matrix generation, and fallback recommendation formatting

## Acceptance Criteria

- A user can describe a workflow and receive a structured risk/exception matrix.
- The output distinguishes risks from mitigations and from approval points.
- The tool explicitly surfaces where full automation is unsafe.
- Existing product behavior remains unchanged.

## Non-Goals

- legal signoff automation
- compliance certification
- real-time monitoring or incident response in v1

## Recommended V1 Cut

- manual workflow intake
- risk classification engine
- exception matrix output
- exportable markdown report

## Demo Narrative

"This tool forces the conversation most teams skip: where will the automation fail, what happens then, and where must a human stay in the loop?"
