---
name: sop-generator
description: |
  Generates standard operating procedures from process descriptions, workflow notes, and operational guidelines. Produces structured SOP documents with numbered steps, role assignments, decision points, exception handling procedures, and version control metadata following ISO 9001 documentation standards.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [cross-industry]
  risk_level: low
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [SOP, standard operating procedure, process documentation, workflow, procedure, ISO 9001]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# SOP Generator

## Purpose

Transforms unstructured process descriptions, workflow notes, internal guidelines, and subject matter expert input into formally structured standard operating procedures. The generated SOPs follow ISO 9001 documentation conventions with clearly numbered steps, defined roles and responsibilities, decision trees for branching processes, and exception handling pathways. Each document includes version control metadata, approval placeholders, and review scheduling information to support ongoing document management within a quality management system.

## When to Use

- When onboarding new team members who need documented procedures for recurring operational tasks
- During ISO 9001 or similar quality management system certification preparation where process documentation gaps exist
- When a process that has been informally understood needs to be formalised due to regulatory requirements or scaling needs
- After a process change or improvement initiative where existing SOPs need to be rewritten from updated workflow descriptions
- When consolidating multiple informal guides, email instructions, and tribal knowledge into a single authoritative procedure document
- During incident post-mortems where the absence of documented procedures contributed to errors and a new SOP must be created

## Instructions

1. Collect all input materials describing the process: workflow narratives, bullet-point notes, existing draft procedures, email chains describing steps, and any referenced forms, templates, or checklists. Read each source document and identify the core process being described.
2. Identify the process scope by determining the clear starting trigger (what initiates the procedure) and the defined end state (what constitutes successful completion). Document any preconditions that must be met before the procedure can begin, including required access, materials, approvals, or system states.
3. Extract and sequence all procedural steps in chronological order. For each step, determine: the action to be performed, the role or position responsible for performing it, any tools or systems required, expected inputs and outputs, and estimated time to complete. Resolve any contradictions between source materials by flagging them for clarification.
4. Identify decision points within the process where the workflow branches based on conditions or outcomes. For each decision point, document the criteria that determine which branch to follow, and ensure each branch path is fully documented through to completion or re-joining the main flow.
5. Document exception handling procedures for foreseeable failure modes at each step. Include escalation paths with specific role titles, timeout thresholds beyond which escalation is required, and recovery procedures for returning to the normal process flow after an exception.
6. Structure the complete SOP document with the following sections: Document Header (title, SOP number, version, effective date, author, approver placeholders), Purpose, Scope, Definitions and Abbreviations, Roles and Responsibilities, Prerequisites, Procedure Steps, Exception Handling, Related Documents, and Revision History.
7. Apply consistent formatting conventions: main steps numbered sequentially (1, 2, 3), sub-steps using decimal notation (1.1, 1.2), decision points marked with conditional language ("IF... THEN... OTHERWISE..."), mandatory actions in imperative voice, and role references in bold on first mention within each step.
8. Generate the revision history table with the initial entry and include placeholder rows for future revisions. Add a review schedule note recommending the next review date based on the process risk level (high-risk: 6 months, standard: 12 months, low-risk: 24 months).

## Output Format

The generated SOP follows this structure:

- **Document Header**: SOP number, title, version number, effective date, document owner, prepared by, approved by (with signature placeholders), and classification level.
- **Purpose**: A concise statement of why the procedure exists and what it aims to achieve.
- **Scope**: Clear boundaries defining what the procedure covers and explicitly noting what is out of scope.
- **Definitions**: A glossary of technical terms, abbreviations, and role titles used in the document.
- **Roles and Responsibilities**: A table mapping each role mentioned in the procedure to their specific responsibilities within the process.
- **Prerequisites**: A checklist of conditions, materials, system access, and approvals required before starting.
- **Procedure**: Numbered steps with sub-steps, decision points, and cross-references to exception handling where applicable.
- **Exception Handling**: Numbered scenarios with trigger conditions, response actions, escalation contacts, and recovery steps.
- **Related Documents**: References to linked SOPs, forms, templates, policies, and external standards.
- **Revision History**: A table with columns for version number, date, author, description of change, and approval reference.

## Quality Checks

- Every step must have a clearly identified responsible role; no step should be assigned to an ambiguous or unnamed party
- Decision points must account for all foreseeable outcomes, including a default or fallback path for unexpected conditions
- The procedure must be executable by someone with the stated role and competency level without requiring additional undocumented knowledge
- All abbreviations and technical terms must be defined in the Definitions section before their first use in the procedure body
- Step numbering must be sequential with no gaps, and cross-references between steps must use correct step numbers
- The document must include at least one exception handling scenario for each critical step identified in the procedure

## Limitations

- Cannot validate the operational correctness of a procedure; the generated SOP reflects the logic of the input materials and does not independently verify that the described process produces the intended outcome
- Does not create visual process flow diagrams or swimlane charts; output is text-based with structural formatting only
- Role titles and organisational structures used in the SOP are taken from input materials and may need adjustment to match the deploying organisation's actual hierarchy
- Cannot automatically detect regulatory requirements that should be referenced in the procedure; compliance obligations must be specified in the input materials
- Does not manage document control workflows such as routing for approval signatures, publishing to controlled document repositories, or superseding previous versions
- Time estimates for individual steps are included only if provided in the source materials; the skill does not independently estimate task durations
