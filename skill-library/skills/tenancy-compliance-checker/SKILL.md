---
name: tenancy-compliance-checker
description: |
  Checks tenancy documentation and letting agent operations for compliance with landlord and tenant legislation. Validates deposit protection, EPC requirements, gas safety certificates, right to rent checks, licensing requirements, and section 21/section 8 notice validity under current housing law.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [property, real-estate]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [tenancy, compliance, deposit protection, gas safety, right to rent, section 21, letting compliance]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Tenancy Compliance Checker

## Purpose

Validates letting agent and landlord compliance with current UK residential tenancy legislation across the full tenancy lifecycle, from pre-tenancy checks through to possession proceedings. The skill reviews documentation packs against statutory requirements, identifies gaps or expiry issues, and produces actionable compliance reports that reduce the risk of failed possession claims, penalty notices, or tenancy deposit disputes.

## When to Use

- A new tenancy is being set up and the letting agent needs to confirm all pre-tenancy compliance requirements are met before the tenant moves in
- A landlord is considering serving a Section 21 notice and needs to verify that all statutory preconditions for a valid notice are satisfied
- A periodic compliance audit is being conducted across a managed property portfolio to check certificate expiry dates and documentation gaps
- A tenant has raised a complaint about property conditions and the agent needs to verify the landlord's compliance position before responding
- A selective or additional licensing application is being prepared and the agent needs to confirm that all licence conditions are currently met
- An existing tenancy is being renewed and the compliance documentation needs rechecking against any legislative changes that have taken effect since the original grant

## Instructions

1. Collect the tenancy documentation pack for the property, which should include the tenancy agreement, deposit protection certificate and prescribed information, gas safety certificate, EPC, electrical installation condition report (EICR), right-to-rent check records, and the How to Rent guide acknowledgement. Note the tenancy start date, current status (fixed-term or periodic), and any renewal dates.

2. Check deposit protection compliance. Verify that the deposit was protected with a government-authorised scheme (DPS, MyDeposits, or TDS) within 30 days of receipt, and that the prescribed information was served on the tenant within the same 30-day period. Confirm the protection is current and has not lapsed due to a scheme transfer or administrative error. Flag any deposits that were received before the tenancy agreement was signed, as the 30-day clock starts from receipt, not from tenancy commencement.

3. Validate gas safety. Confirm that a valid Gas Safe registered engineer's certificate is on file and that it was issued within the 12 months preceding the tenancy start date or the most recent anniversary. Check that a copy was provided to the tenant before they moved in or within 28 days of the annual check. Flag any properties approaching certificate expiry within the next 30 days.

4. Check the EPC. Verify that a valid Energy Performance Certificate is on file with a rating of E or above, unless the property has a registered exemption on the PRS Exemptions Register. Confirm the EPC was commissioned before marketing the property and that a copy was provided to the tenant before the tenancy was granted. EPCs are valid for 10 years from the date of issue.

5. Validate the EICR. Confirm that an Electrical Installation Condition Report has been obtained from a qualified person, that it is dated within the last 5 years, that the outcome is satisfactory, and that a copy was provided to the tenant within 28 days of the inspection. For any unsatisfactory reports, check whether remedial works were completed within 28 days and that the local authority was notified where required.

6. Verify right-to-rent checks. Confirm that a right-to-rent check was carried out on all adult occupiers before the tenancy commenced, that acceptable documents were inspected and copied, and that the check was recorded with the date. For time-limited permissions, verify that follow-up checks were scheduled and conducted before expiry.

7. Assess Section 21 notice validity by checking all statutory preconditions: deposit protection and prescribed information served, gas safety certificate provided, EPC provided, EICR provided, How to Rent guide served (current version at the date of service), and no relevant improvement notice or emergency remedial action notice outstanding against the property. If any precondition is unmet, state that a Section 21 notice cannot be validly served until the issue is remediated.

8. Produce the compliance report with a status for each requirement, the document reference and date reviewed, and any required actions with deadlines.

## Output Format

The output should be a structured compliance report containing:

- **Property Summary**: Address, landlord name, managing agent, tenancy type, tenancy start date, current status
- **Compliance Dashboard**: Each requirement area with a status of compliant, expiring soon, action required, or non-compliant
- **Detailed Findings**: For each item, the document reviewed, its validity period, the statutory requirement, and any issue identified
- **Section 21 Readiness**: A clear yes or no statement on whether a valid Section 21 notice could be served today, with reasons for any negative assessment
- **Action List**: Numbered remediation actions with the relevant legislation cited, suggested owner, and deadline

## Quality Checks

- Deposit protection is verified against the actual date of receipt, not the tenancy start date
- Gas safety certificate expiry is calculated from the date of issue, not the date provided to the tenant
- The How to Rent guide version checked corresponds to the version current at the date it was served, not the current version
- EICR requirements are applied to all tenancies granted or renewed on or after 1 July 2020 and to all existing tenancies from 1 April 2021
- Right-to-rent follow-up check dates are calculated correctly based on the permission expiry date shown on the original documentation
- Any HMO licensing requirements applicable to the property based on occupancy numbers and local authority schemes have been considered

## Limitations

- Does not provide legal advice; the report is a compliance screening tool and disputed possession proceedings or deposit claims should be referred to a qualified solicitor
- Cannot independently verify whether a gas safety engineer is currently on the Gas Safe register or whether an electrician holds a qualifying certification; document-level checks only
- Does not check the PRS Exemptions Register directly; exemption claims must be validated by the agent separately
- Local authority selective and additional licensing schemes vary by area and the skill relies on the agent confirming whether the property falls within a designated licensing zone
- Does not cover HMO management regulation compliance beyond licensing; matters such as fire safety risk assessments, room size standards, and amenity provision require separate specialist review
- Legislative references are based on English housing law; Scottish, Welsh, and Northern Irish tenancy legislation differs materially and is not covered
