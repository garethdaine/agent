---
name: supplier-compliance-monitor
description: |
  Monitors supplier compliance with contractual obligations, quality standards, and regulatory requirements. Reviews supplier audit reports, certification expiry dates, corrective action responses, and performance against SLAs to maintain supply chain governance and risk visibility.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [supply-chain, manufacturing, logistics]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [supplier, compliance, vendor management, SLA, supplier audit, quality, certification]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Supplier Compliance Monitor

## Purpose

Reviews supplier documentation, audit findings, and performance records to assess compliance with contractual SLAs, quality management standards, and applicable regulatory requirements. The skill consolidates supplier governance data into actionable compliance dashboards that highlight risks, upcoming certification expiries, and unresolved corrective actions requiring escalation.

## When to Use

- A procurement or supply chain governance team needs to conduct a periodic compliance review across the active supplier base.
- A supplier audit has been completed and the findings need to be assessed against the organisation's compliance framework and risk appetite.
- Certification expiry dates (ISO 9001, ISO 14001, BRC, SEDEX, or similar) are approaching and the team needs to identify which suppliers require renewal follow-up.
- Corrective action requests (CARs) have been issued to suppliers and the team needs to track response timeliness and closure rates.
- A new regulatory requirement (e.g. UK Modern Slavery Act reporting, EUDR due diligence, CBAM declarations) necessitates a gap analysis across the existing supplier portfolio.
- Supplier rationalisation or onboarding decisions require a consolidated compliance and risk profile for comparison.

## Instructions

1. Ingest the supplier compliance dataset, which should include: supplier name and identifier, contract reference, required certifications, current certification status and expiry dates, most recent audit date and findings, SLA metrics (e.g. quality reject rate, delivery reliability, response time), and any open corrective action requests with their due dates and current status.
2. Validate certification currency by comparing expiry dates against the current date. Classify each certification as current (more than 90 days until expiry), approaching expiry (30-90 days), expired, or not held. Flag any supplier whose mandatory certifications have lapsed or will lapse within the review period.
3. Review audit findings and categorise them by severity: critical non-conformance, major non-conformance, minor non-conformance, and observation. For each finding, check whether a corrective action has been raised, whether the response was received within the contractually required timeframe, and whether the action has been verified as closed.
4. Assess SLA performance by comparing actual metrics against contracted thresholds. Calculate compliance rates for each SLA category and identify suppliers falling below minimum acceptable performance levels. Apply a rolling assessment where three or more consecutive periods of non-compliance trigger an escalation flag.
5. Evaluate regulatory compliance by checking supplier records against a checklist of applicable requirements based on their geography, industry sector, and the goods or services they provide. Note any gaps where required declarations, registrations, or due diligence documentation is absent.
6. Assign an overall compliance risk rating to each supplier using a composite score derived from certification status (weighted 25%), audit performance (weighted 30%), SLA compliance (weighted 25%), and regulatory compliance (weighted 20%). Map scores to risk bands: low, medium, high, or critical.
7. Generate an action register listing all items requiring follow-up, prioritised by risk rating and deadline, with recommended next steps for the procurement or governance team.
8. Produce the consolidated compliance dashboard summarising portfolio-level statistics and individual supplier profiles.

## Output Format

**Portfolio Summary** containing:
- Total active suppliers reviewed
- Compliance rate by risk band (percentage in each category)
- Number of expired or soon-to-expire certifications
- Open corrective actions (total, overdue, pending verification)
- Suppliers flagged for escalation

**Supplier Compliance Profiles** presented as individual records for each supplier, containing:
- Supplier name and reference
- Overall compliance risk rating and composite score
- Certification status table (certification type, issue date, expiry date, status)
- Latest audit summary (date, auditor, finding counts by severity)
- SLA performance summary (metric, target, actual, status)
- Open corrective actions (CAR reference, finding, due date, current status)

**Action Register** presented as a prioritised list with columns for: supplier, action required, category (certification renewal, CAR follow-up, SLA review, regulatory gap), priority, responsible party (if known), and target completion date.

## Quality Checks

- Certification expiry calculations account for time zones and use the supplier's local date where specified to avoid off-by-one errors at month boundaries.
- Duplicate supplier entries (e.g. same entity listed under different trading names or entity numbers) are identified and flagged rather than silently merged, to preserve data integrity.
- SLA threshold comparisons use the exact contractual values from the input data rather than assumed industry benchmarks, unless no contract values are provided.
- Corrective action timeliness is measured from the date of issue to the date of supplier response, not the date of verification closure, to accurately reflect supplier responsiveness.
- Risk rating calculations are transparent, with the individual component scores shown alongside the composite to allow the reviewer to understand which factors are driving the overall rating.
- Where data is incomplete for a supplier, the compliance profile explicitly notes which assessments could not be performed rather than assigning a default pass.

## Limitations

- Does not perform physical or on-site supplier audits; the skill analyses documentation and data records only.
- Cannot verify the authenticity of supplier-provided certifications; it checks for presence and expiry but does not validate against issuing body registries.
- Regulatory requirement checklists are based on the input configuration and are not automatically updated when legislation changes; the checklist must be maintained by the governance team.
- Financial risk indicators (e.g. credit scores, insolvency risk) are outside the scope of this skill and require integration with dedicated financial risk services.
- The composite risk scoring model uses fixed weightings; organisations with different risk priorities should adjust the weightings in the skill configuration before use.
