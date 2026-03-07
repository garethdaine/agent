---
name: meeting-intelligence
description: |
  Processes meeting transcripts and notes to extract actionable intelligence. Identifies decisions made, action items with owners and deadlines, key discussion topics, unresolved issues, and follow-up requirements. Produces structured meeting summaries with cross-referenced action tracking.
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
  trigger_keywords: [meeting, minutes, action items, transcript, decisions, follow-up, meeting notes]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Meeting Intelligence

## Purpose

Extracts structured, actionable information from meeting transcripts, raw notes, and recorded conversation logs. The skill identifies and categorises decisions that were made, action items with their assigned owners and deadlines, key topics discussed, questions raised but not resolved, and commitments requiring follow-up. It produces a standardised meeting summary that serves as both an immediate record and an input to project tracking and task management workflows, reducing the time between a meeting ending and its outcomes being actioned.

## When to Use

- After any meeting where decisions were made or tasks were assigned and a formal record is needed
- When processing raw transcripts from recorded meetings that need to be converted into structured minutes
- Before a follow-up meeting to extract the outstanding action items and unresolved issues from the previous session's notes
- When consolidating notes from multiple attendees who captured different aspects of the same meeting
- After workshop sessions, brainstorming meetings, or planning sessions where numerous ideas and commitments were generated rapidly
- When a project manager needs to update task tracking systems with action items that emerged from a meeting without manually reviewing the full transcript

## Instructions

1. Receive the meeting transcript, notes, or recording text. Identify the meeting metadata where available: date, time, duration, attendees, meeting title or subject, and any pre-circulated agenda. If metadata is not explicitly stated, infer what can be reasonably determined from the content.
2. Segment the transcript into topical sections by identifying shifts in discussion subject. Label each section with a descriptive topic heading that captures the substance of that portion of the conversation. Maintain chronological order to preserve the meeting's narrative flow.
3. Extract all decisions made during the meeting. A decision is identified by explicit agreement language ("we agreed", "the decision is", "let's go with"), voting outcomes, or authoritative directives from the meeting chair. Record each decision with its exact wording, the topic it relates to, and any conditions or caveats attached.
4. Extract all action items by identifying commitments to future activity. For each action item, record: the specific task description, the person assigned (by name or role), the stated or implied deadline, the priority level if mentioned, and any dependencies on other actions or external inputs. Where a deadline is not explicitly stated, flag the item as requiring a deadline assignment.
5. Identify unresolved issues and open questions that were raised but not conclusively addressed during the meeting. Record the question or issue, who raised it, and any partial responses or commitments to investigate further. These items form the basis for follow-up agenda items.
6. Note any risks, concerns, or blockers that were mentioned by participants. Capture the specific concern, who raised it, and any mitigation discussed. Distinguish between risks that were acknowledged and accepted versus those requiring further action.
7. Compile the structured meeting summary using the standardised output format. Cross-reference action items to the decisions and discussion topics they originated from, so that each action is traceable to its context.
8. Generate an action item register as a standalone table that can be exported directly to task management tools, with columns for action ID, description, owner, deadline, priority, status (default: Open), and source reference (topic section number).

## Output Format

The meeting intelligence output contains the following sections:

- **Meeting Header**: Date, time, duration, location or platform, attendees list (with roles where known), meeting title, and reference to any pre-circulated agenda.
- **Summary**: A three-to-five sentence narrative overview of the meeting covering the primary purpose, most significant outcomes, and overall tenor of the discussion.
- **Topics Discussed**: A numbered list of topic sections, each with a heading, a brief summary of the discussion (two to four sentences), and references to any decisions or actions arising from that topic.
- **Decisions Log**: A numbered list of decisions with the decision statement, rationale where provided, topic reference, and any noted conditions or review dates.
- **Action Item Register**: A table with columns for Action ID, Description, Owner, Deadline, Priority, Dependencies, and Status. Each action cross-references the topic and decision it relates to.
- **Unresolved Issues**: A list of open questions and unresolved matters with the raiser's name and any commitments to follow up, suitable for inclusion on the next meeting's agenda.
- **Risks and Concerns**: Any blockers, risks, or concerns raised during the meeting with current mitigation status.
- **Next Meeting**: Date, time, and proposed agenda items for the following session, if discussed.

## Quality Checks

- Every action item must have an identifiable owner; items where no owner was stated must be flagged with "Owner: TBC" rather than omitted
- Decisions must reflect what was actually agreed, not what was proposed or debated; the distinction between a proposal discussed and a decision made must be preserved
- Attendee names must be spelled consistently throughout the document and must match the names as they appear in the source transcript
- Action items must not duplicate each other; where the same task was discussed multiple times, it should appear once with consolidated context
- The summary section must be genuinely concise and must not simply repeat the full content of the topics discussed section
- Cross-references between action items, decisions, and topics must use correct reference numbers and must resolve to the correct entries

## Limitations

- Cannot identify speakers in transcripts that lack speaker labels or attribution; in such cases, action owners and decision attributions may be incomplete
- Does not interpret tone, sentiment, or non-verbal communication; sarcasm, reluctance, or qualified agreement expressed through tone rather than words will not be captured
- Cannot verify the authority of decision-makers; the skill records decisions as stated without assessing whether the individual had the organisational authority to make them
- Informal side conversations or off-topic remarks included in transcripts may generate spurious action items if they contain commitment-like language
- Does not integrate with external calendar or task management systems; the action item register is produced as structured text for manual or automated import
- Accuracy of extraction depends on the quality and completeness of the source transcript; gaps, inaudible sections, or heavily abbreviated notes will result in incomplete extraction
