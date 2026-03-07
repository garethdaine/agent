---
name: client-communication-reviewer
description: |
  Reviews outbound client communications for tone, accuracy, regulatory compliance, and professional standards. Checks legal correspondence, advisory letters, and client-facing emails against firm communication policies and SRA/regulatory body guidelines.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [legal, accounting]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [communication, client letter, correspondence, tone review, SRA, professional standards]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Client Communication Reviewer

## Purpose

Reviews outbound client communications for professional tone, factual accuracy, regulatory compliance, and adherence to firm-wide communication standards. The skill evaluates legal correspondence, advisory letters, client-facing emails, and engagement letters against the Solicitors Regulation Authority (SRA) Standards and Regulations, firm style guides, and best practice guidelines for professional services communication. It is intended to reduce the risk of complaints, regulatory censure, and reputational damage arising from substandard client correspondence.

## When to Use

- Before sending advisory letters or formal legal opinions to clients, particularly where the advice involves complex or contentious matters
- When drafting engagement letters, costs estimates, or terms of business updates that need to comply with SRA Transparency Rules and the firm's pricing policies
- When reviewing complaint responses or sensitive correspondence where tone and precision are critical to maintaining the client relationship
- When junior fee earners or trainees have prepared client correspondence that requires a quality check before the supervising solicitor approves it
- When communicating outcomes on matters involving vulnerable clients, where the SRA Code of Conduct requires additional care around clarity and accessibility
- Before sending correspondence that references costs, billing, or payment terms, to ensure compliance with the SRA Accounts Rules and consumer information requirements

## Instructions

1. Read the full communication and identify its type (advisory letter, engagement letter, email, costs update, complaint response, general correspondence) and the intended recipient (individual client, corporate client, third-party solicitor, regulatory body). Note the matter reference and the seniority of the author.

2. Assess the tone and register of the communication. Confirm it is professional, measured, and appropriate for the recipient. Flag any language that is overly casual, unnecessarily aggressive, condescending, or that could be perceived as dismissive. For vulnerable clients, verify that the language is clear, avoids unnecessary jargon, and follows plain English principles in line with SRA Principle 7 (acting in the best interests of each client).

3. Review the communication for factual accuracy and consistency. Cross-reference any stated dates, figures, case references, statutory citations, or claimed outcomes against the information available in the matter file. Flag any unsupported assertions, speculative statements presented as fact, or outdated information that may mislead the client.

4. Check for regulatory compliance against the following standards:
   - SRA Principles (particularly Principle 2: public trust, Principle 4: honesty, Principle 7: best interests)
   - SRA Code of Conduct for Solicitors, paragraphs 8.6-8.11 (client information and publicity)
   - SRA Transparency Rules where the communication relates to costs, service descriptions, or regulatory information
   - Consumer Contracts Regulations 2013 where applicable to consumer clients
   - The firm's own communication policy and house style guide

5. Verify that appropriate caveats and disclaimers are included where necessary. Advisory letters should clearly state the basis of the advice, any assumptions made, the limitations of the advice, and whether the advice is specific to the recipient or general in nature. Costs communications must distinguish between estimates, fixed fees, and capped fees, and must reference the client's right to challenge bills under the Solicitors Act 1974.

6. Check that the communication correctly identifies the author, their role, and their regulatory status. Confirm that the firm's SRA number, complaints procedure reference, and professional indemnity information are included where required by the SRA Transparency Rules.

7. Review the communication for data protection compliance. Confirm that no personal data relating to third parties is inappropriately disclosed, that any attachments referenced are appropriate for the recipient, and that the communication includes the firm's standard confidentiality notice where sent by email.

## Output Format

**Communication Overview**
- Communication type, author, recipient, matter reference, and date

**Tone Assessment**
- Overall tone rating (appropriate / requires minor adjustment / requires significant revision)
- Specific passages flagged with suggested rewording

**Accuracy Review**
- List of factual claims checked, with status (verified / unverified / incorrect)
- Any inconsistencies with the matter file or previous correspondence

**Regulatory Compliance**
A table with columns: Requirement | Standard/Rule Reference | Status (Compliant / Non-Compliant / Not Applicable) | Notes

**Recommended Amendments**
Numbered list of specific changes required before the communication can be approved for sending, ordered by priority.

**Overall Assessment**
- Approval recommendation (approved / approved with minor amendments / requires revision and re-review)

## Quality Checks

- The tone is appropriate for the recipient type and the nature of the matter, with particular care taken for vulnerable clients and sensitive subject matter
- All costs figures and billing references comply with the SRA Transparency Rules and accurately reflect the fee arrangement on the matter
- The communication does not contain any undertakings given inadvertently or without proper authorisation from a partner
- No privileged or confidential information relating to other clients or matters has been inadvertently included
- The communication does not make guarantees about outcomes, timelines, or results that could constitute misleading statements under SRA Principle 4
- Where the communication is an advisory letter, it clearly states the scope of instructions, the factual basis of the advice, and any material assumptions
- The firm's standard footer, confidentiality notice, and regulatory information are present and current

## Limitations

- This skill reviews communications for tone, accuracy, and regulatory compliance but does not verify the underlying legal advice; the substantive correctness of legal opinions remains the responsibility of the supervising solicitor
- The skill applies SRA Standards and Regulations applicable to solicitors practising in England and Wales; communications subject to regulation by the Law Society of Scotland, Law Society of Northern Ireland, or overseas regulatory bodies require jurisdiction-specific review
- Highly technical or sector-specific communications (e.g., financial regulatory correspondence, patent prosecution letters) may require specialist review beyond the scope of this general communication check
- The skill does not assess whether the communication strategy itself is appropriate for the matter; decisions about whether to communicate in writing versus by telephone, or the timing of communications, remain matters of professional judgement for the fee earner
- Automated review cannot fully assess the broader relationship context between the firm and the client, which may affect what tone and level of formality is appropriate
