---
name: financial-compliance-check
description: |
  Validates agent outputs against financial compliance requirements including FCA conduct rules, SOX internal controls, and GDPR data handling obligations. Performs automated checks on financial documents, reports, and communications to identify regulatory gaps and non-compliant language.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [financial-services, accounting]
  risk_level: elevated
  requires_approval: false
  memory_blocks: [compliance-rules]
  mcp_dependencies: [database]
  tools: [file-read, web-search]
  trigger_keywords: [compliance, financial, audit, regulatory, FCA, SOX, GDPR]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Financial Compliance Check

## Purpose

This skill validates financial documents, reports, and client-facing communications against applicable regulatory frameworks including FCA conduct rules, SOX internal control requirements, and GDPR data handling obligations. It performs structured checks to identify non-compliant language, missing disclosures, inadequate risk warnings, and data protection failures before content is finalised or distributed.

## When to Use

- Before issuing any client-facing financial advice or recommendation documents
- When reviewing marketing materials or financial promotions for FCA compliance under COBS 4
- During preparation of annual or interim financial statements to verify disclosure completeness
- When processing client personal data to ensure GDPR Article 13/14 notice requirements are met
- As part of SOX control testing to validate that segregation of duties and authorisation controls are documented
- Before submitting regulatory returns to the FCA, PRA, or HMRC
- When onboarding new clients to verify KYC and AML documentation meets the Money Laundering Regulations 2017

## Instructions

1. **Identify the document type and applicable regulatory scope.** Determine whether the document is a financial promotion, client report, engagement letter, regulatory return, or internal control document. Map the document to the relevant regulatory frameworks (FCA Handbook, UK GAAP, IFRS, GDPR, MLR 2017, or SOX where applicable to US-listed entities).

2. **Extract all claims, figures, and assertions from the document.** Parse the content to identify numerical data, performance claims, forward-looking statements, risk disclosures, fee structures, and any references to past performance. Flag any unsubstantiated claims or figures without source attribution.

3. **Check financial promotion rules.** If the document is a financial promotion or contains promotional language, verify compliance with FCA COBS 4 requirements: ensure it is fair, clear, and not misleading; confirm past performance disclaimers are present where historical returns are cited; verify that risk warnings are prominent and not obscured by positive messaging.

4. **Validate data protection compliance.** Scan for personal data references (client names, account numbers, NI numbers, dates of birth). Confirm that any document containing personal data includes appropriate privacy notices, has a stated lawful basis for processing, and does not disclose personal data to unintended recipients.

5. **Assess internal control documentation.** For SOX-relevant documents, verify that control descriptions include the control objective, frequency, responsible party, evidence of performance, and exception handling procedures. Check that segregation of duties is maintained between preparer, reviewer, and approver roles.

6. **Review regulatory disclosure completeness.** Cross-reference the document against required disclosures for its type. For financial statements, check that all notes required by FRS 102 or applicable IFRS standards are present. For client agreements, verify that cancellation rights, complaints procedures, and FSCS protection details are included where required.

7. **Generate a compliance findings report.** Produce a structured report listing each finding with its severity (critical, major, minor, advisory), the specific regulatory reference, a description of the gap, and a recommended remediation action. Order findings by severity.

8. **Provide an overall compliance assessment.** Assign an overall compliance status of Compliant, Conditionally Compliant (minor issues only), or Non-Compliant (one or more critical or major findings). Include a summary count of findings by severity.

## Output Format

The output is a structured compliance report containing:

- **Header**: Document name, document type, date of review, regulatory scope applied
- **Summary**: Overall compliance status, total finding count by severity
- **Findings Table**: Each row contains a finding ID, severity level, regulatory reference (e.g., "FCA COBS 4.2.1R"), description of the issue, affected section of the document, and recommended remediation
- **Data Protection Assessment**: A separate section confirming whether personal data was detected, the lawful basis identified, and any GDPR gaps
- **Sign-off Statement**: A closing statement confirming the scope and limitations of the automated review, noting that this does not constitute legal advice

## Quality Checks

- Every finding must reference a specific regulation, rule, or statutory provision rather than generic compliance language
- Critical findings must include a clear explanation of the regulatory risk or potential enforcement consequence
- The review must not produce false positives for standard boilerplate text that is already compliant
- Past performance disclaimers must be checked against the exact wording required by FCA COBS 4.6
- GDPR checks must distinguish between personal data, special category data, and anonymised data
- SOX control assessments must verify that control evidence is testable and not merely descriptive
- The compliance status must be internally consistent with the findings (a critical finding cannot appear alongside a Compliant status)

## Limitations

- This skill performs automated text-based analysis and cannot substitute for qualified legal or compliance advice from an authorised person
- It does not access live regulatory databases; rule references are based on the compliance-rules memory block which must be kept current
- Complex financial instruments (derivatives, structured products) may require specialist compliance review beyond the scope of this skill
- The skill cannot verify the accuracy of underlying financial data or calculations, only the presence and structure of required disclosures
- Cross-border regulatory requirements beyond FCA, GDPR, and SOX are not covered; additional jurisdictional review may be needed for multi-national operations
- It does not perform AML transaction monitoring or sanctions screening; these require dedicated systems with access to sanctions lists
