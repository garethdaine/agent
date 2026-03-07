---
name: vendor-contract-reviewer
description: |
  Reviews vendor and supplier contracts for commercial risk, unfavourable terms, and compliance gaps. Analyses pricing structures, SLA commitments, liability caps, termination provisions, data processing clauses, and auto-renewal terms to provide negotiation recommendations and risk assessments.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [cross-industry]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [vendor contract, supplier agreement, SLA review, commercial terms, contract risk, procurement]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Vendor Contract Reviewer

## Purpose

The Vendor Contract Reviewer skill analyses vendor and supplier contracts to identify commercial risks, unfavourable terms, compliance gaps, and negotiation opportunities. It systematically evaluates key contractual provisions including pricing mechanisms, service level commitments, liability frameworks, intellectual property rights, data protection obligations, and exit terms. The skill produces a structured risk assessment with prioritised findings and actionable negotiation recommendations, enabling procurement teams and legal reviewers to focus their attention on the clauses that carry the greatest financial or operational exposure.

## When to Use

- A new vendor contract or master services agreement is under review before signature.
- An existing contract is approaching renewal and needs assessment for renegotiation priorities.
- A change order or contract amendment has been proposed by a vendor and requires impact analysis.
- Procurement is evaluating competing vendor proposals and needs a standardised risk comparison.
- A data processing agreement or sub-processor addendum requires review against privacy regulation requirements.
- Internal audit or compliance has requested a review of vendor contract terms against organisational policy.
- A vendor relationship is being escalated or terminated and the contractual provisions governing exit need clarification.

## Instructions

1. **Identify the contract type and structure.** Determine whether the document is a master services agreement, statement of work, software licence, SaaS subscription, data processing agreement, or another contract form. Note the parties, effective date, initial term, and governing law. Identify all schedules, appendices, and incorporated documents referenced in the main body.

2. **Analyse the pricing and payment structure.** Review the fee schedule, rate cards, volume commitments, and payment terms. Identify price escalation mechanisms (fixed increases, CPI-linked, uncapped), minimum spend obligations, and penalties for early termination or volume shortfalls. Flag any pricing terms that create lock-in or unpredictable cost exposure.

3. **Evaluate service level commitments.** Examine SLA definitions, measurement methodologies, reporting obligations, and service credit mechanisms. Assess whether SLA targets are measurable and enforceable, whether credits are meaningful relative to fees, and whether the contract includes SLA exclusions that materially weaken the commitments. Check for uptime definitions that exclude scheduled maintenance windows or force majeure events.

4. **Review liability and indemnification provisions.** Analyse liability caps (per-incident and aggregate), carve-outs from caps (e.g., IP infringement, data breach, wilful misconduct), indemnification obligations, and insurance requirements. Identify any unlimited liability exposure or asymmetric indemnification that disproportionately favours the vendor.

5. **Assess data protection and security clauses.** Review data processing terms, data location restrictions, sub-processor controls, breach notification timelines, audit rights, and data return/deletion obligations on termination. Compare provisions against applicable regulations (GDPR, CCPA, or as specified) and organisational data governance policies.

6. **Examine termination and exit provisions.** Analyse termination for convenience rights, notice periods, termination for cause triggers, cure periods, and the consequences of termination including transition assistance, data extraction, and surviving obligations. Identify any vendor lock-in mechanisms such as proprietary data formats, excessive exit fees, or restrictive non-compete clauses.

7. **Identify additional risk areas.** Review intellectual property ownership and licence grants, change management procedures, dispute resolution mechanisms, assignment and subcontracting rights, force majeure provisions, and confidentiality obligations. Flag any unusual or non-standard clauses that deviate from market norms.

8. **Compile findings and recommendations.** Produce the risk assessment with each finding categorised by risk level and contract section. Provide specific, actionable negotiation recommendations for each material finding, including suggested alternative language or fallback positions where appropriate.

## Output Format

The contract review report contains the following sections:

- **Contract Overview**: Parties, contract type, effective date, term, governing law, and total contract value or estimated annual spend.
- **Risk Summary**: High-level risk rating (High, Medium, Low) with a one-paragraph rationale summarising the most significant concerns.
- **Detailed Findings**: A table of findings, each with a reference to the relevant clause number, risk category (Commercial, Operational, Legal, Compliance, Data Protection), risk level (High, Medium, Low), description of the issue, and potential business impact.
- **Pricing Analysis**: Summary of fee structure, escalation mechanisms, total cost of ownership considerations, and cost risk exposure.
- **SLA Assessment**: Table of SLA targets with measurement methodology, credit mechanism, and assessment of enforceability.
- **Negotiation Recommendations**: Prioritised list of recommended changes, each with the current clause language summary, the identified risk, the recommended position, and a fallback position.
- **Compliance Gaps**: Specific areas where the contract does not meet organisational policy or regulatory requirements, with references to the applicable standard.
- **Key Dates**: Critical dates including renewal deadlines, notice periods, price review dates, and option exercise windows.

## Quality Checks

- Every finding references the specific clause or section number in the contract under review.
- Risk levels are consistently applied: High for material financial exposure or regulatory non-compliance, Medium for unfavourable but negotiable terms, Low for minor deviations from best practice.
- Negotiation recommendations are specific and include concrete alternative positions, not generic advice to "negotiate better terms."
- The pricing analysis accounts for all fee components, not just the headline rate, including implementation fees, change request charges, and exit costs.
- Data protection findings reference the specific regulatory requirement or organisational policy that the clause fails to satisfy.
- The review distinguishes between legally binding terms and non-binding statements of intent or best-effort commitments.

## Limitations

- This skill provides commercial and operational risk analysis but does not constitute legal advice. All findings should be validated by qualified legal counsel before contract execution.
- The skill reviews the text of the contract as provided; it cannot verify the vendor's actual performance history, financial stability, or market reputation.
- Jurisdiction-specific legal nuances (e.g., enforceability of limitation of liability clauses, implied statutory terms) require local legal expertise beyond the scope of this skill.
- The skill analyses the contract in isolation and does not cross-reference against other agreements the organisation may hold with the same vendor or related entities.
- Highly technical schedules such as detailed network architecture specifications or bespoke software acceptance criteria may require subject matter expert review beyond the skill's commercial analysis.
