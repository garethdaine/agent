---
name: case-file-summarizer
description: |
  Summarizes legal case files into structured briefs for fee earners and senior partners. Extracts key facts, chronology of events, parties involved, legal issues identified, and recommended next steps from case documentation bundles.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [legal]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [case file, legal summary, brief, case management, chronology, litigation]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Case File Summarizer

## Purpose

Produces structured case summaries from legal documentation bundles, extracting key facts, parties, chronology, legal issues, and recommended next steps into a concise brief suitable for fee earners, supervising partners, and counsel. The skill is designed to reduce the time spent on file familiarisation when matters are reassigned, when partners need a rapid overview ahead of a conference or hearing, or when matter reviews are conducted as part of the firm's file management and supervision obligations under the SRA Code of Conduct.

## When to Use

- When a matter is being transferred between fee earners and the incoming solicitor needs a comprehensive file summary to get up to speed efficiently
- When a supervising partner requires a matter overview ahead of a client meeting, case management conference, or court hearing
- When conducting periodic file reviews as required by the firm's supervision and file management policies under paragraphs 3.4-3.5 of the SRA Code of Conduct for Solicitors
- When preparing instructions to counsel and a concise brief of the case history is needed alongside the formal instructions and documentation bundle
- When a matter has been dormant for an extended period and the fee earner needs to reconstruct the current position from the file before re-engaging with the client
- When the firm is conducting a risk audit or matter profitability review and needs standardised summaries across a portfolio of cases

## Instructions

1. Read all available documents in the case file, including correspondence, attendance notes, pleadings, witness statements, expert reports, court orders, and internal file notes. Identify the matter type (e.g., commercial litigation, personal injury, employment dispute, property transaction, family proceedings, debt recovery) and the current procedural stage.

2. Identify and list all parties to the matter, including the client, opposing parties, their legal representatives, any relevant third parties (insurers, guarantors, co-defendants), and key individuals (witnesses, experts, judges if proceedings have been issued). Record each party's role, known contact details where available, and their relationship to the dispute or transaction.

3. Construct a chronology of material events from the earliest relevant date to the present. Each entry should include the date, a concise description of the event, the source document, and its significance to the matter. Distinguish between disputed and undisputed facts, and flag any gaps in the chronological record that may require further investigation or disclosure.

4. Identify the core legal issues arising from the case file. For each issue, state the legal basis (statutory provision, common law principle, or contractual term), the client's position, the opposing party's known or anticipated position, and any relevant authorities or precedents referenced in the file. Note where the law is unsettled or where there is a divergence of judicial opinion that may affect the outcome.

5. Summarise the current procedural position, including: any court proceedings issued (with claim number and allocated track), relevant limitation dates, upcoming deadlines (disclosure, witness statements, expert reports, trial date), any interim applications pending or anticipated, and the status of any without prejudice negotiations or Part 36 offers.

6. Extract the key documents in the file that are critical to the matter outcome. For each, provide the document title, date, author, and a one-sentence summary of its relevance. Flag any documents that appear to be missing from the file or that are referenced in correspondence but not present in the bundle.

7. Assess the overall risk position based on the information in the file. Provide a high-level merits assessment (strong / reasonable / weak / insufficient information), identify the principal risks (evidential gaps, limitation issues, costs exposure, enforcement difficulties), and note any compliance concerns such as outstanding conflict checks, overdue costs information, or missed supervision review dates.

8. Produce a recommended next steps section listing the immediate actions required on the file, ordered by priority and urgency. Each action should include a brief description, the responsible fee earner or team, the deadline or target date, and any dependencies on third-party input or client instructions.

## Output Format

**Matter Overview**
- Matter reference, matter type, client name, supervising partner, current fee earner, date file opened, and current status (active / dormant / closing)

**Parties**
A table with columns: Party Name | Role | Legal Representative | Key Contact | Notes

**Chronology**
A table with columns: Date | Event | Source Document | Significance | Disputed/Undisputed

**Legal Issues**
For each issue:
- Issue description
- Legal basis and relevant authorities
- Client's position
- Opposing position (known or anticipated)
- Assessment (strong / arguable / weak)

**Procedural Position**
- Court and claim number (if applicable)
- Current stage and allocated track
- Key upcoming dates and deadlines
- Status of settlement discussions (without prejudice content excluded)

**Key Documents Register**
A table with columns: Document | Date | Author | Relevance | Status (Present / Missing / Incomplete)

**Risk Assessment**
- Overall merits assessment
- Principal risks identified
- Costs exposure estimate (if ascertainable from the file)
- Compliance flags

**Recommended Next Steps**
A numbered list with: Action | Responsible Person | Deadline | Dependencies

## Quality Checks

- The chronology accounts for all material events referenced in the correspondence and pleadings, with no unexplained gaps exceeding 30 days during active periods of the matter
- All parties identified in the file are included in the parties table, including any parties who have been added or removed during the course of proceedings
- Limitation dates have been calculated and verified against the relevant statutory provisions (Limitation Act 1980, or sector-specific limitation periods)
- The legal issues section does not contain substantive legal advice but accurately reflects the issues as identified in the file by the fee earner or counsel
- Key deadlines from court orders or the Civil Procedure Rules timetable are accurately extracted and cross-referenced against the procedural position section
- Without prejudice communications are noted as existing but their content is not disclosed in the summary, preserving privilege
- The summary distinguishes clearly between facts established by evidence in the file and assertions or positions taken by the parties

## Limitations

- This skill summarises the content of the case file as provided and cannot verify facts independently; inaccuracies in the underlying documents will be reflected in the summary
- The skill does not provide legal advice or a formal merits assessment; risk ratings are indicative and based solely on the information available in the file, not on independent legal research
- Privileged communications (legal advice privilege and litigation privilege) are summarised at a high level only; the skill does not assess whether privilege has been maintained or waived
- Complex multi-party litigation, group actions, or matters involving extensive electronic disclosure may exceed the practical scope of a single summarisation pass and may require segmented review
- The skill cannot access the firm's case management system, time recording system, or accounts ledger directly; financial information is limited to what appears in the documents provided
- Matters involving foreign law elements, cross-border disputes, or proceedings in tribunals with specialist procedural rules may require supplementary review by a practitioner with relevant expertise
