---
name: escalation-drafter
description: |
  Drafts escalation communications for operational incidents, service failures, and compliance breaches. Produces structured escalation notices with incident classification, impact assessment, timeline of events, immediate actions taken, and required management decisions with appropriate urgency framing.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [cross-industry]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [escalation, incident, escalate, service failure, breach notification, management alert]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Escalation Drafter

## Purpose

The Escalation Drafter skill produces structured, professional escalation communications for operational incidents, service degradations, compliance breaches, and other events requiring management attention. It ensures that escalation notices contain all necessary context for decision-makers to act quickly, including severity classification, business impact quantification, chronological event timelines, and clearly stated asks. The skill enforces consistent formatting and urgency calibration so that critical issues receive proportionate attention without desensitising recipients through overuse of high-priority framing.

## When to Use

- A production incident or service outage requires notification to senior management or executive stakeholders.
- A compliance breach or regulatory violation has been detected and must be formally reported up the chain.
- An SLA threshold has been breached or is at imminent risk of breach with a customer or vendor.
- A project milestone has been missed and the delay carries financial, contractual, or reputational consequences.
- A security event requires coordinated response across multiple teams and management authorisation.
- An operational issue has exceeded the resolution timeframe defined in the incident management policy.

## Instructions

1. **Classify the incident severity.** Determine the appropriate severity level (Critical, High, Medium, Low) based on the scope of impact, number of affected users or systems, revenue exposure, and regulatory implications. Apply the organisation's severity matrix if one is referenced in the input.

2. **Identify the audience and escalation tier.** Determine whether this is a Tier 1 (team lead / on-call manager), Tier 2 (department head / VP), or Tier 3 (C-suite / board) escalation. Adjust the level of technical detail, business context, and formality accordingly.

3. **Construct the incident timeline.** Build a chronological sequence of events from first detection through to the current state. Include timestamps, key actions taken, personnel involved, and any decision points. Use UTC timestamps unless a specific timezone is indicated.

4. **Assess and quantify business impact.** Document the measurable effects of the incident: affected customer count, revenue at risk, SLA credit exposure, data records involved, or regulatory reporting deadlines triggered. Use concrete figures where available and clearly label estimates.

5. **Document immediate actions taken.** List all remediation, containment, or mitigation steps already completed or in progress. Include the responsible party for each action and its current status (completed, in progress, pending).

6. **State required decisions and asks.** Clearly articulate what the escalation recipient needs to do: approve a workaround, allocate additional resources, authorise customer communications, engage external vendors, or invoke a disaster recovery plan. Frame each ask with its deadline and consequence of inaction.

7. **Draft the escalation notice.** Assemble the communication using the standard escalation template with clearly labelled sections. Ensure the subject line conveys severity and topic at a glance. Keep the executive summary to three sentences maximum.

8. **Calibrate urgency and tone.** Review the draft to ensure the urgency level matches the severity classification. Remove inflammatory language, unsupported speculation, and blame attribution. Confirm that the tone is factual, direct, and action-oriented.

## Output Format

The escalation notice is produced as a structured document with the following sections:

- **Subject Line**: `[SEVERITY] - Brief description of incident`
- **Executive Summary**: Three-sentence overview covering what happened, current status, and what is needed.
- **Incident Details**: Severity level, incident ID, detection time, affected systems/services.
- **Timeline of Events**: Chronological table with timestamp, event description, and responsible party.
- **Business Impact**: Quantified effects on customers, revenue, compliance, and operations.
- **Actions Taken**: Numbered list of completed and in-progress remediation steps.
- **Required Decisions**: Numbered list of specific asks with owners and deadlines.
- **Next Update**: Scheduled time for the follow-up communication.
- **Distribution List**: Intended recipients and their roles.

## Quality Checks

- The severity classification is justified by the stated business impact and is not arbitrarily elevated.
- All timestamps are consistent and in the specified timezone format.
- Impact figures are labelled as confirmed or estimated, with the basis for estimates stated.
- Required decisions are specific and actionable, not vague requests for "support" or "attention."
- The executive summary can be understood in isolation without reading the full document.
- No blame attribution or speculative root cause analysis is included unless confirmed by investigation.
- The next update time is realistic and accounts for the current stage of incident response.

## Limitations

- This skill drafts escalation communications but does not send them or trigger notification workflows.
- Severity classification relies on the information provided; incomplete input may result in under- or over-classification.
- The skill does not perform root cause analysis; it documents the timeline and impact as reported.
- Industry-specific regulatory escalation requirements (e.g., 72-hour GDPR breach notification) should be validated against current legislation, as the skill applies general frameworks.
- The skill does not have access to live monitoring systems and cannot independently verify incident status or metrics.
