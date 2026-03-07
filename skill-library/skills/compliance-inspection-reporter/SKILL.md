---
name: compliance-inspection-reporter
description: |
  Generates compliance inspection reports for facilities management and construction sites. Documents findings from health and safety audits, fire safety inspections, and building regulation checks with standardized severity ratings, photographic evidence references, and remediation timelines.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [facilities-management, construction]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [inspection, compliance report, health and safety, fire safety, building regulations, facilities, audit]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Compliance Inspection Reporter

## Purpose

Produces structured compliance inspection reports for construction sites and managed facilities in accordance with UK regulatory standards. The skill interprets raw inspection data, categorises findings by severity using a four-tier rating system (Critical, Major, Minor, Observation), and generates reports suitable for submission to local authority building control, the Health and Safety Executive, or internal compliance teams. Reports include remediation action items with assigned owners and target completion dates.

## When to Use

- After completing a scheduled health and safety site inspection under CDM 2015 regulations
- Following a fire risk assessment review in a managed building or multi-occupancy facility
- When documenting findings from a building regulations compliance check prior to practical completion
- During periodic facilities audits required under ISO 41001 or BREEAM post-occupancy assessments
- When consolidating observations from multiple inspection visits into a single handover report for a principal contractor or client

## Instructions

1. Read the provided inspection data file, which should contain raw observations, timestamps, location references, and any photographic evidence file paths. Accept CSV, JSON, or structured markdown formats as input.

2. Validate that each observation record includes the required fields: date of inspection, location or zone reference, inspector name, observation description, and regulatory reference (e.g., Approved Document B, CDM Regulation 13, BS 5839-1). Flag any records with missing mandatory fields and list them in a data quality summary at the top of the report.

3. Classify each finding using the four-tier severity rating:
   - **Critical** — Immediate risk to life or structural safety; requires stop-work notice or emergency remediation within 24 hours
   - **Major** — Significant non-compliance that must be rectified before the next scheduled inspection or within 14 days
   - **Minor** — Low-risk non-compliance that should be addressed within 28 days as part of routine maintenance
   - **Observation** — Advisory note for best practice improvement; no regulatory breach identified

4. Group findings by inspection area or building zone, then by severity within each group. For construction sites, use the standard zone references (e.g., Substructure, Superstructure, External Works, Temporary Works). For facilities, use floor and room references consistent with the building's asset register.

5. For each finding, generate a remediation action item that includes: a unique reference number, the responsible party or trade contractor, the required corrective action, the applicable regulation or approved document, the target completion date, and a field for sign-off confirmation.

6. Append a photographic evidence register cross-referencing each finding to its associated image files. Include the photo file path, capture date, GPS coordinates or zone reference, and the finding reference number.

7. Generate an executive summary section at the top of the report containing: total findings by severity, the overall compliance status (Pass, Conditional Pass, Fail), the inspection date range, the next recommended inspection date, and any stop-work recommendations if Critical findings exist.

8. Format the final output as a structured markdown document with consistent heading levels, tables for findings and action items, and a metadata header block containing site name, project reference, principal contractor, and report version number.

## Output Format

The report is produced as a single markdown document with the following structure:

```
# Compliance Inspection Report
## Metadata
- Site / Facility name, project reference, report date, version
## Executive Summary
- Compliance status, finding counts by severity, key actions
## Data Quality Summary
- Any records with missing fields or validation warnings
## Findings by Zone
### [Zone Name]
| Ref | Severity | Description | Regulation | Remediation | Owner | Target Date |
## Photographic Evidence Register
| Photo Ref | Finding Ref | File Path | Date | Location |
## Sign-Off
- Inspector name, qualifications, signature block, date
```

## Quality Checks

- Every finding must reference at least one specific regulation, approved document, or British Standard
- Critical findings must include an explicit remediation deadline of no more than 24 hours
- The executive summary compliance status must be consistent with the severity of findings: any Critical finding results in a Fail status
- All photographic evidence references must correspond to a valid finding reference number
- Zone references must be consistent throughout the report with no duplicate or conflicting identifiers
- Date formats must follow the DD/MM/YYYY convention used in UK construction documentation
- The report must not contain generic or boilerplate remediation actions; each action must be specific to the finding

## Limitations

- Does not replace a qualified inspector's professional judgement; all findings must originate from a competent person's observations
- Cannot verify the accuracy of photographic evidence or confirm that images match the described conditions
- Does not interface directly with local authority building control portals or the HSE RIDDOR reporting system
- Severity classifications are based on the provided data and the skill's rule set; unusual or novel compliance scenarios may require manual reclassification
- Does not cover specialist inspections such as asbestos surveys (HSG264), legionella risk assessments (ACoP L8), or structural engineer certifications, which require separate specialist reporting
- Maximum input size is constrained by the agent's context window; very large inspection datasets spanning multiple sites should be split into separate reports
