---
name: waste-transfer-processor
description: |
  Processes waste transfer notes and duty of care documentation for construction and facilities operations. Validates waste classification codes, checks carrier and site licence details, ensures regulatory compliance with Environment Agency requirements, and maintains audit trails for waste management records.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [construction, facilities-management, waste-management]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [waste transfer, duty of care, waste classification, EWC codes, Environment Agency, waste management]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Waste Transfer Processor

## Purpose

Processes and validates waste transfer notes and duty of care documentation in accordance with the Environmental Protection Act 1990, the Waste (England and Wales) Regulations 2011, and Environment Agency technical guidance WM3. The skill checks that European Waste Catalogue (EWC) codes are correctly assigned, verifies that carrier registrations and receiving site permits cover the declared waste types, and produces structured audit records suitable for regulatory inspection or Site Waste Management Plan (SWMP) compliance. It is designed for construction site managers, facilities managers, and waste compliance officers operating within England and Wales.

## When to Use

- When processing incoming waste transfer notes from subcontractors or waste carriers to verify completeness before filing
- During preparation or update of a Site Waste Management Plan for a construction project subject to pre-demolition or pre-refurbishment audits
- When reconciling waste consignment notes for hazardous waste movements that must be reported to the Environment Agency
- At the end of a reporting period to compile waste volumes and disposal routes for sustainability reporting or BREEAM Wst 01 credit evidence
- When auditing duty of care records ahead of an Environment Agency compliance visit or local authority inspection
- During facilities management contract reviews to verify that waste management service providers hold current registrations and permits

## Instructions

1. Read the input file containing waste transfer note data. Each record should include: the waste producer's name and site address, the waste description, the EWC code (six-digit format), the quantity and unit of measurement (tonnes or cubic metres), the waste carrier's name and registration number, the receiving facility name and environmental permit or exemption reference, the transfer date, and the Standard Industry Classification (SIC) code of the producing activity.

2. Validate each EWC code against the European Waste Catalogue list. Confirm that the six-digit code exists, that it falls within the correct chapter for the declared waste type (e.g., Chapter 17 for construction and demolition waste), and that the code matches the free-text waste description provided. Flag any mirror entries where the hazardous or non-hazardous classification depends on the concentration of dangerous substances, and note that these require assessment under WM3 Technical Guidance.

3. Check the waste carrier registration details. Verify that the registration number follows the expected format (CBDU or CBDL prefix followed by a numeric reference). Confirm that an upper tier registration is held for any carrier transporting waste commercially, and that lower tier registrations are only used where the carrier is transporting their own waste. Flag expired registrations based on the transfer date compared to any expiry date provided in the input.

4. Validate the receiving facility details. Check that the environmental permit reference or registered exemption number is present and follows the recognised format. For permits, this is typically a prefix such as EPR followed by a reference number. For exemptions, verify the exemption type code (e.g., U1, T4, T6) and confirm that the declared waste type is within the scope of that exemption category.

5. Assess each record for duty of care compliance completeness. A compliant waste transfer note must contain all fields specified in Schedule 1 of the Environmental Protection (Duty of Care) Regulations 1991 (as amended). Generate a compliance status for each record: Complete, Incomplete (listing missing fields), or Requires Review (where mirror entry assessment or hazardous waste consignment note procedures may apply).

6. For any records involving hazardous waste (identified by asterisked EWC codes or mirror entry assessment), verify that a consignment note is referenced rather than a standard transfer note, and that the consignment note includes the additional fields required under the Hazardous Waste (England and Wales) Regulations 2005: the unique consignment note code, the premises code of the producer, and the relevant dangerous substance properties (HP codes).

7. Compile a summary report showing: total number of transfer notes processed, breakdown by compliance status, total waste quantities by EWC chapter, a list of unique carriers and receiving facilities referenced, and any records requiring corrective action. Include a separate hazardous waste summary if applicable.

8. Generate an audit-ready output file structured for long-term record retention. Duty of care records must be retained for a minimum of two years (three years for hazardous waste consignment notes). Include a record retention schedule noting the earliest permissible disposal date for each document based on the transfer date.

## Output Format

The output is a structured markdown document with the following sections:

```
# Waste Transfer Processing Report
## Processing Summary
- Total records processed, date range, site reference
- Compliance breakdown: Complete / Incomplete / Requires Review
## Validation Findings
### EWC Code Validation
| Record Ref | Declared EWC | Description | Status | Notes |
### Carrier Registration Checks
| Carrier Name | Registration No | Type | Expiry Status |
### Receiving Facility Checks
| Facility Name | Permit/Exemption Ref | Waste Types Covered | Status |
## Waste Quantities Summary
| EWC Chapter | Description | Total Quantity | Unit | Disposal/Recovery Route |
## Hazardous Waste Summary (if applicable)
| Consignment Note Code | EWC Code | HP Codes | Quantity | Receiving Facility |
## Non-Compliant Records
| Record Ref | Missing Fields | Required Action | Priority |
## Record Retention Schedule
| Record Ref | Transfer Date | Retention Period | Earliest Disposal Date |
```

## Quality Checks

- Every EWC code must be a valid six-digit code from the current European Waste Catalogue as adopted into UK law
- Mirror entry EWC codes (those with hazardous and non-hazardous variants) must be explicitly flagged for WM3 assessment rather than silently accepted
- Carrier registration numbers must conform to the CBDU/CBDL format; free-text or missing registrations must be flagged as non-compliant
- Hazardous waste records must reference consignment notes, not standard transfer notes
- Quantities must be expressed in consistent units; mixed units within the same report must be normalised with conversion assumptions stated
- The record retention schedule must calculate minimum retention periods correctly: two years for standard waste, three years for hazardous waste, measured from the date of transfer
- No record should be marked as Complete if any mandatory Schedule 1 field is absent

## Limitations

- Does not connect to the Environment Agency public register to perform live validation of carrier registrations or environmental permits; checks are based on format validation and data provided in the input file
- Cannot perform WM3 hazardous waste classification assessments, which require laboratory analysis of dangerous substance concentrations; the skill only flags records that require such assessment
- Applies to the regulatory framework for England and Wales only; Scottish (SEPA) and Northern Irish (NIEA) waste regulations differ in terminology and registration formats
- Does not generate or submit electronic duty of care (edoc) records to the Environment Agency's waste tracking service
- Cannot verify physical waste descriptions against actual site conditions; the skill processes documentary records only
- Does not cover radioactive waste, which falls under separate regulatory controls (Environmental Permitting Regulations, RSR permits)
