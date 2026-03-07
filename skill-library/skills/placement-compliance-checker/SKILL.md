---
name: placement-compliance-checker
description: |
  Checks recruitment placements for compliance with employment law, IR35 regulations, AWR requirements, and agency worker directives. Validates right-to-work documentation, contract terms, pay rate compliance, and opt-out declarations for temporary and permanent placements.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [recruitment]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [placement, compliance, IR35, AWR, right to work, employment law, recruitment compliance]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Placement Compliance Checker

## Purpose

Validates recruitment placements against current UK employment legislation, ensuring that agencies and hiring businesses meet their statutory obligations before a worker begins an assignment. The skill checks contract documentation, right-to-work evidence, IR35 status determination statements, Agency Workers Regulations compliance, and pay rate calculations to identify gaps or non-compliance before they become enforceable liabilities.

## When to Use

- A new temporary or contract placement is being set up and needs pre-start compliance validation
- A placement is approaching the 12-week AWR qualifying period and parity terms need reviewing
- An IR35 status determination statement has been issued by a client and needs checking against HMRC guidance
- An audit of existing live placements is required to confirm ongoing compliance
- A worker has raised a query about holiday pay accrual, pay between assignments, or equal treatment entitlements
- Right-to-work documentation needs validating against Home Office prescribed document lists before a placement starts

## Instructions

1. Review the placement documentation pack, which should include the client terms of business, the worker contract or assignment schedule, the rate confirmation, and any IR35 status determination statement where applicable. Identify the placement type as temporary, fixed-term contract, or permanent introduction.

2. For temporary and contract placements, check the worker contract against the Conduct of Employment Agencies and Employment Businesses Regulations 2003. Confirm that the contract includes all mandatory terms: pay rate or method of calculation, notice period, holiday entitlement, and the nature of the employment relationship. Flag any missing or non-compliant terms.

3. Validate right-to-work documentation against the Home Office prescribed document lists. Check that the document type is acceptable for the worker's nationality and immigration status, that any expiry dates have not passed, and that the agency has recorded the date of the check. Flag any time-limited permissions that will expire during the placement period.

4. For assignments where the worker operates through a personal service company or umbrella arrangement, review the IR35 status determination statement provided by the end client. Check that the determination covers the specific engagement, that the reasoning addresses the key HMRC tests (substitution, mutuality of obligation, control), and that the determination was communicated to the worker and the agency in the correct chain. Flag any determinations that appear incomplete or inconsistent with the working arrangements described.

5. Calculate the AWR qualifying period position for the placement. If the worker has completed or will complete 12 weeks in the same role with the same hirer, check that parity terms have been assessed and documented. Review whether the worker is entitled to equal treatment on pay, holiday, rest breaks, and access to facilities and vacancies. If a Swedish Derogation style pay-between-assignments contract was previously in place, note that these are no longer valid following the April 2020 legislative change.

6. Check pay rate compliance by comparing the agreed worker rate against the current National Minimum Wage or National Living Wage for the worker's age band. For umbrella company placements, verify that the pay calculation after deductions still meets the statutory minimum. Flag any placements where margin compression or deductions risk a minimum wage breach.

7. Produce a compliance status report for the placement, grading each area as compliant, action required, or non-compliant. For any items graded as action required or non-compliant, include the specific regulation reference and a recommended remediation step.

## Output Format

The output should be a structured compliance report containing:

- **Placement Summary**: Worker name, client name, placement type, start date, expected duration, current week count
- **Compliance Status Grid**: Each compliance area (contract terms, right-to-work, IR35, AWR, pay rate) with a status indicator and summary note
- **Detailed Findings**: For each non-compliant or action-required item, a section covering the issue, the relevant regulation, the evidence reviewed, and the recommended remediation
- **Risk Assessment**: Overall placement risk rating (low, medium, high) based on the number and severity of findings
- **Action Tracker**: A numbered list of required actions with suggested owners and target completion dates

## Quality Checks

- All mandatory contract terms under the Conduct Regulations have been individually verified
- Right-to-work document types have been checked against the current Home Office prescribed list, not an outdated version
- IR35 determination review addresses all three key HMRC tests and does not simply accept the client's conclusion at face value
- AWR week count calculation accounts for any breaks in assignment that do or do not reset the qualifying period under the regulations
- Pay rate checks use the current statutory minimum wage rates for the correct age band effective from the most recent April uprating
- The report distinguishes between issues that prevent placement start and issues that require remediation within a defined timeframe

## Limitations

- Does not provide legal advice; output is a compliance screening tool and complex or disputed IR35 determinations should be referred to a qualified employment law adviser
- Cannot independently verify the authenticity of right-to-work documents; the skill checks document types and expiry dates against published lists but does not perform biometric or forgery checks
- AWR parity assessment relies on the comparable employee information provided and cannot determine parity terms where the client has not disclosed the relevant pay and conditions data
- Does not cover sector-specific licensing requirements such as SIA licensing for security placements or CSCS cards for construction; these require separate validation
- Regulatory references are based on UK employment law and do not cover placements governed by other jurisdictions
