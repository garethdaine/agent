---
name: care-compliance-checker
description: |
  Checks care service operations against CQC regulatory standards, safeguarding requirements, and sector-specific compliance frameworks. Reviews care plans, incident reports, staffing records, and operational procedures for regulatory alignment and identifies gaps requiring remediation.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [healthcare, social-care]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [CQC, care compliance, safeguarding, care quality, regulatory, care plan, inspection]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Care Compliance Checker

## Purpose

Evaluates care service documentation and operational records against the Care Quality Commission's five key lines of enquiry: Safe, Effective, Caring, Responsive, and Well-led. The skill systematically reviews submitted materials to identify regulatory gaps, missing evidence, and areas of non-compliance before they surface during formal CQC inspection. It produces a structured compliance report with prioritised remediation actions aligned to current regulatory expectations.

## When to Use

- Preparing for a scheduled or anticipated CQC inspection and needing to assess readiness across all five domains.
- Reviewing updated care plans or policies to confirm they meet the latest version of the Health and Social Care Act 2008 (Regulated Activities) Regulations 2014.
- Investigating whether incident reports, safeguarding referrals, and complaints have been handled and documented in line with local authority and CQC expectations.
- Auditing staffing records to verify DBS check currency, mandatory training completion, and supervision frequency against regulatory minimums.
- Assessing whether a service's quality assurance processes produce sufficient evidence to demonstrate compliance during a Well-led review.
- Checking that medication administration records (MARs), risk assessments, and consent documentation meet current best practice standards.

## Instructions

1. Receive the set of documents to be reviewed. These may include care plans, risk assessments, incident and accident reports, safeguarding logs, medication administration records, staffing rotas, training matrices, supervision records, complaints logs, and quality audit reports.
2. Categorise each document against the relevant CQC key line of enquiry (KLOE). A single document may be relevant to more than one domain. Map each to Safe, Effective, Caring, Responsive, or Well-led as appropriate.
3. For each KLOE domain, check the documents against the corresponding regulatory requirements from the Health and Social Care Act 2008 (Regulated Activities) Regulations 2014 and the CQC fundamental standards. Identify whether the required evidence is present, partially present, or absent.
4. Assess safeguarding documentation specifically. Verify that safeguarding policies reference the local Safeguarding Adults Board procedures, that staff training records show completion of safeguarding awareness at the appropriate level, and that any safeguarding referrals include documented outcomes and follow-up actions.
5. Review staffing records for compliance with working time regulations, DBS renewal schedules, and mandatory training requirements including fire safety, moving and handling, infection prevention and control, and first aid. Flag any expired certifications or overdue training.
6. Examine incident and accident reports for completeness. Each should contain a description, immediate actions taken, root cause analysis, duty of candour compliance where applicable, and evidence that lessons learned have been communicated to the wider team.
7. Compile findings into a structured compliance report organised by KLOE domain. For each gap or concern, assign a severity rating of Critical, High, Medium, or Low based on the potential impact on people who use the service and the likelihood of regulatory action.
8. Produce a prioritised remediation plan listing each identified gap, the regulation it relates to, the recommended corrective action, a suggested responsible person role, and a target completion timeframe.

## Output Format

The output is a compliance report in structured markdown containing:

- **Summary**: Overall compliance posture with a rating per KLOE domain (Compliant, Partially Compliant, Non-Compliant).
- **Findings by Domain**: For each of the five KLOE domains, a table listing the regulation reference, finding description, evidence reviewed, severity rating, and compliance status.
- **Safeguarding Section**: A dedicated subsection covering safeguarding policy alignment, training compliance rates, and open referral status.
- **Remediation Plan**: A prioritised action table with columns for finding reference, corrective action, responsible role, target date, and severity.
- **Evidence Gaps**: A list of documents or records that were expected but not provided for review.

## Quality Checks

- Every finding references a specific regulation number from the Health and Social Care Act 2008 (Regulated Activities) Regulations 2014 or a named CQC fundamental standard.
- Severity ratings are consistently applied using the defined four-tier scale and reflect genuine risk to people who use the service rather than administrative inconvenience.
- The remediation plan includes realistic timeframes that account for the severity of each gap, with critical items targeted for resolution within 48 hours and high items within 14 days.
- Safeguarding findings are cross-referenced against the local authority Safeguarding Adults Board multi-agency procedures where the locality is known.
- No finding is listed without a corresponding recommended corrective action in the remediation plan.
- The report distinguishes between breaches of regulation (which could lead to enforcement action) and areas for improvement (which would be noted but not enforced).

## Limitations

- This skill reviews documentation as provided and cannot verify the accuracy of the content within those documents. Fabricated or misleading records will not be detected through document review alone.
- The skill does not replace a formal CQC inspection, which includes interviews with staff, people who use the service, and their families, as well as direct observation of care delivery.
- Clinical judgements within care plans and risk assessments are not evaluated for clinical correctness. The skill checks for the presence and structure of required elements, not the appropriateness of clinical decisions.
- Local authority-specific safeguarding thresholds and referral pathways vary by region. Where the locality is not specified, the skill applies general multi-agency safeguarding guidance rather than area-specific procedures.
- The skill does not have access to external systems such as the CQC Provider Information Return portal, NHS Spine, or local authority databases, so it cannot verify registration status or cross-reference external records.
- Working time regulation checks rely on the accuracy of the submitted rota and timesheets. Informal overtime or off-record shifts will not be captured.
