---
name: clinical-document-processor
description: |
  Processes clinical documentation including discharge summaries, referral letters, and clinical notes. Extracts structured data from narrative clinical text, identifies key diagnoses, medications, and follow-up actions, and formats outputs for care coordination workflows.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [healthcare]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [clinical, discharge summary, referral, medical notes, diagnosis, medication, healthcare document]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Clinical Document Processor

## Purpose

Extracts structured, actionable data from narrative clinical documents commonly used in NHS and UK independent healthcare settings. The skill parses discharge summaries, outpatient clinic letters, GP referral letters, and clinical notes to identify diagnoses, active medications, allergies, investigation results, and required follow-up actions. Extracted data is formatted for downstream use in care coordination, multidisciplinary team handovers, and clinical audit workflows.

## When to Use

- Processing NHS hospital discharge summaries to extract medication changes, new diagnoses, and follow-up appointments for GP practice reconciliation.
- Parsing outpatient clinic letters to identify specialist recommendations, investigation requests, and referral outcomes for inclusion in shared care records.
- Extracting structured medication lists from narrative clinical text to support medicines reconciliation during care transitions between primary, secondary, and social care settings.
- Preparing patient summaries for multidisciplinary team (MDT) meetings by consolidating information from multiple clinical documents into a single structured overview.
- Supporting clinical audit by extracting coded diagnoses, procedures, and outcomes from free-text clinical correspondence for aggregate analysis.
- Processing referral letters to identify the clinical question, relevant history, current medications, and urgency to support triage and prioritisation workflows.

## Instructions

1. Receive the clinical document or set of documents to be processed. Supported formats include plain text, structured clinical letters, and discharge summary templates. Identify the document type (discharge summary, clinic letter, referral letter, clinical note, or investigation report) from its structure and content.
2. Extract patient demographic identifiers present in the document, including name, date of birth, NHS number, and hospital number. Flag any documents where key identifiers are missing or inconsistent, as this may indicate a misfiled or incomplete record.
3. Parse the clinical narrative to identify and extract diagnoses. Distinguish between primary diagnoses (the main reason for the clinical encounter), secondary diagnoses (comorbidities and background conditions), and new diagnoses established during the encounter. Where SNOMED CT or ICD-10 codes are present in the source text, capture them alongside the narrative description.
4. Extract the medication information from the document. For discharge summaries, identify medications started, stopped, or changed during the admission and capture the discharge medication list with drug name, dose, route, frequency, and duration or review date. For other document types, capture the current medication list as stated.
5. Identify allergies and adverse drug reactions documented in the text, including the causative agent and the nature of the reaction where recorded. Distinguish between confirmed allergies and stated intolerances.
6. Extract follow-up actions and outstanding tasks. These may include outpatient appointments to be arranged, investigations pending or requested, referrals to other services, medication reviews to be conducted by the GP, and safety-netting instructions given to the patient. Assign each action to the responsible party (e.g., GP, hospital team, patient, community service) where this is stated or can be inferred.
7. Identify any investigation results mentioned in the document, including blood tests, imaging, histology, and microbiology. Capture the test name, result value, date if available, and any clinical interpretation provided by the authoring clinician.
8. Compile all extracted data into the structured output format. Cross-reference medication changes against the stated diagnoses to verify internal consistency. Flag any discrepancies such as a new medication without a corresponding diagnosis or a discontinued medication without a documented reason.

## Output Format

The output is a structured clinical data extract in markdown containing:

- **Document Metadata**: Document type, date of document, authoring clinician and organisation, patient identifiers (name, DOB, NHS number).
- **Diagnoses**: A table listing each diagnosis with columns for diagnosis description, category (primary, secondary, or new), and clinical code (SNOMED CT or ICD-10) where available.
- **Medications**: A table with columns for drug name, dose, route, frequency, status (continued, started, stopped, changed), and any noted review date or duration.
- **Allergies and Adverse Reactions**: A list of stated allergies with the causative agent, reaction type, and severity where documented.
- **Investigation Results**: A table of results mentioned in the document with test name, result value, date, and clinical interpretation.
- **Follow-up Actions**: A numbered list of required actions, each tagged with the responsible party and any stated timeframe or urgency.
- **Consistency Flags**: Any discrepancies identified during cross-referencing, such as unexplained medication changes or diagnoses without supporting narrative.

## Quality Checks

- Every extracted medication entry includes at minimum the drug name, dose, and frequency. Entries missing critical fields are included but flagged as incomplete rather than silently omitted.
- Diagnoses are categorised correctly as primary, secondary, or new based on the clinical context. A diagnosis described as a background condition in a discharge summary is not labelled as primary.
- Follow-up actions are attributed to the correct responsible party. Actions described as "GP to arrange" are assigned to the GP, not to the hospital or patient.
- NHS numbers, where present, conform to the standard 10-digit format and pass the modulus 11 check digit validation. Non-conforming numbers are flagged for verification.
- The extracted data does not introduce information that is not present in the source document. The skill extracts and structures existing content; it does not infer or generate clinical information.
- Where a document contains ambiguous or contradictory information (such as conflicting medication lists), both versions are captured and the inconsistency is explicitly flagged for human review.

## Limitations

- The skill processes clinical text in English only. Documents written in other languages or containing substantial non-English terminology beyond standard Latin medical terms are not supported.
- Clinical coding is extracted only where codes are explicitly stated in the source document. The skill does not assign SNOMED CT or ICD-10 codes to narrative descriptions that lack them, as clinical coding requires trained human judgement and access to the full clinical record.
- The skill does not validate clinical appropriateness. It will extract a medication and its stated dose without assessing whether that dose is within the safe therapeutic range for the patient's age, weight, or renal function.
- Handwritten clinical notes, scanned documents, and image-based PDFs cannot be processed. Input must be in machine-readable text format.
- Patient-identifiable data is processed in the extracted output. The consuming system is responsible for ensuring that data handling complies with the UK GDPR, the Data Protection Act 2018, and the Caldicott Principles. The skill does not perform de-identification.
- Complex multi-document processing (such as reconstructing a full patient journey from dozens of letters spanning several years) is limited by the context window. For large document sets, processing in smaller batches is recommended.
