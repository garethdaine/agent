---
name: food-safety-compliance
description: |
  Validates food safety documentation and HACCP compliance for food manufacturing, processing, and catering operations. Reviews hazard analysis records, critical control point monitoring logs, allergen management procedures, and traceability documentation against Food Standards Agency requirements and BRC/SALSA standards.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [food-manufacturing, catering, retail]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [food safety, HACCP, allergen, food standards, BRC, SALSA, food hygiene, traceability]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Food Safety Compliance

## Purpose

Reviews food safety management documentation for manufacturing, processing, and catering operations against UK regulatory requirements and recognised industry standards. The skill validates HACCP plans, monitoring records, allergen controls, traceability systems, and hygiene procedures to identify non-conformances before they are found during Environmental Health Officer inspections, retailer audits, or third-party certification body visits.

## When to Use

- A food business is preparing for a BRC Global Standard, SALSA, or STS audit and needs a documentation gap analysis
- An Environmental Health Officer inspection is expected and the business wants to verify its Food Safety Management System documentation is current and complete
- A new product line or recipe has been introduced and the HACCP plan needs reviewing to confirm all new hazards have been assessed
- An allergen-related incident or near-miss has occurred and the allergen management procedures need urgent review
- A retailer or food service customer has issued a supplier questionnaire and the responses need cross-checking against actual documented procedures
- Annual review of the food safety management system documentation is due

## Instructions

1. Review the HACCP plan for the site or production area under assessment. Confirm that a hazard analysis has been conducted for every process step from raw material intake through to despatch or service. Verify that biological, chemical, physical, and allergen hazards have been considered at each step. Check that the HACCP team membership is documented and includes individuals with relevant competencies. Flag any process steps where the hazard analysis appears incomplete or where new ingredients or processes have been introduced since the last revision.

2. For each identified Critical Control Point, verify that the following are documented: the specific hazard being controlled, the critical limit with a measurable parameter, the monitoring procedure including frequency and responsible person, the corrective action to be taken when a critical limit is breached, and the verification activities that confirm the CCP is functioning effectively. Check that monitoring records exist for the review period and that they are completed consistently without unexplained gaps.

3. Review allergen management procedures against the requirements of the Food Information Regulations 2014 and any applicable retailer codes of practice. Confirm that a full allergen matrix is maintained for all products and is consistent with current recipes. Check that ingredient specifications from suppliers include allergen declarations. Verify that production scheduling, cleaning procedures, and labelling controls address cross-contamination risks. Review any "may contain" precautionary allergen labelling to confirm it is supported by a documented risk assessment rather than applied as a blanket measure.

4. Assess the traceability system by requesting a mock trace exercise. Select one batch of finished product and trace it backwards to all raw material supplier deliveries and batch codes, and forwards to all customer deliveries. Verify that the trace can be completed within four hours as expected by most retailer and certification body standards. Check that traceability records include supplier name, delivery date, batch or lot code, quantity, and the finished products into which the materials were incorporated.

5. Review hygiene and cleaning documentation. Confirm that documented cleaning schedules exist for all production areas, equipment, and utensils. Check that cleaning chemical data sheets are on file, that dilution rates are specified, and that cleaning verification records such as ATP swab results or microbiological surface testing are available. Verify that personal hygiene policies cover handwashing, protective clothing, illness reporting, and visitor procedures.

6. Check staff training records. Confirm that all food handlers have received food hygiene training appropriate to their role, that HACCP team members have received formal HACCP training, and that allergen awareness training is delivered and refreshed at a documented frequency. Flag any staff members with expired or missing training records.

7. Compile findings into a non-conformance report, classifying each issue as critical, major, or minor in line with the BRC Global Standard grading definitions. Critical non-conformances are those presenting an immediate food safety risk. Major non-conformances are systematic failures in a food safety control. Minor non-conformances are isolated lapses that do not compromise food safety but indicate procedural weakness.

## Output Format

The output should be a structured compliance report containing:

- **Site Summary**: Business name, site address, scope of assessment, date of review, certification standard being assessed against
- **HACCP Plan Review**: Status of each CCP with monitoring record completeness and any gaps identified
- **Allergen Management**: Allergen matrix accuracy, cross-contamination controls, labelling compliance status
- **Traceability**: Mock trace result with time to complete and any breaks in the chain identified
- **Hygiene and Cleaning**: Schedule completeness, verification record status, and any overdue items
- **Training Records**: Staff compliance summary with counts of current, expiring, and overdue training
- **Non-Conformance Register**: Each finding classified as critical, major, or minor with the clause reference, evidence, and recommended corrective action
- **Audit Readiness Score**: Overall percentage readiness based on the number and severity of findings

## Quality Checks

- Every CCP in the HACCP plan has been individually assessed for monitoring record completeness
- The allergen matrix has been compared against current recipes and not simply accepted as provided
- The traceability exercise tests both backward and forward trace, not just one direction
- Cleaning verification records are checked for the actual results, not just for the existence of a schedule
- Training record checks cover all staff including temporary and agency workers, not just permanent employees
- Non-conformance classifications are consistent with the stated standard's grading criteria

## Limitations

- Cannot perform physical site inspections, temperature checks, or environmental sampling; the review is documentation-based only
- Does not replace a formal audit by an accredited certification body; output is a preparation tool for identifying gaps before the official audit
- Microbiological test results and ATP readings are reviewed for completeness and trend but the skill does not interpret borderline results against specific organism limits, which requires a food microbiologist
- Cannot verify supplier approval status beyond checking that specifications and certificates are on file; supplier site audits are outside scope
- Regulatory references are based on UK food safety law including retained EU regulations as amended; businesses exporting to the EU or other territories may have additional requirements not covered
- Does not cover food defence, food fraud vulnerability assessments, or environmental and sustainability requirements that form part of some certification standards
