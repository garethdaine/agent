---
name: data-reconciliation
description: |
  Reconciles data across multiple systems and sources to identify discrepancies. Compares records by key fields, calculates match rates, identifies orphaned records, duplicate entries, and value mismatches. Produces reconciliation reports with exception lists and suggested resolution actions for data stewards.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [cross-industry]
  risk_level: low
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [reconciliation, data matching, discrepancy, duplicate detection, data quality, exception report]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Data Reconciliation

## Purpose

The Data Reconciliation skill compares datasets from multiple systems or sources to identify discrepancies, orphaned records, duplicates, and value mismatches. It is designed for operational scenarios where data consistency across systems is critical, such as financial close processes, system migration validation, and ongoing data quality monitoring. The skill produces structured reconciliation reports with quantified match rates, categorised exceptions, and actionable resolution recommendations for data stewards and system owners.

## When to Use

- Validating data consistency between a source system and a target system after a migration or integration.
- Performing periodic reconciliation between an ERP, CRM, or billing system and an external data source such as a bank statement or vendor ledger.
- Identifying duplicate records within a single dataset before a data cleansing exercise.
- Comparing inventory counts, transaction volumes, or account balances across systems at a point in time.
- Auditing data feeds between internal systems to detect dropped, duplicated, or corrupted records.
- Preparing evidence of data integrity for internal audit, external audit, or regulatory examination.

## Instructions

1. **Define the reconciliation scope.** Identify the two or more datasets to be compared, the reconciliation period, and the business context. Confirm which dataset is treated as the authoritative source of truth and which is the comparison target.

2. **Establish matching keys.** Determine the primary key fields used to link records across datasets (e.g., transaction ID, invoice number, employee ID, account reference). Document any transformations needed to normalise keys, such as trimming whitespace, case conversion, or prefix removal.

3. **Perform record-level matching.** Execute a full outer join on the matching keys to identify three categories: matched records (present in both datasets), source-only orphans (present in source but missing from target), and target-only orphans (present in target but missing from source). Count records in each category.

4. **Compare field values on matched records.** For all matched records, compare the designated comparison fields (e.g., amount, quantity, status, date). Flag any records where field values differ beyond the defined tolerance threshold. Apply absolute or percentage tolerance rules as specified.

5. **Detect duplicates within each dataset.** Scan each dataset independently for duplicate keys or near-duplicate records based on fuzzy matching criteria. Report duplicate clusters with their frequency and the specific fields that vary between duplicates.

6. **Calculate reconciliation metrics.** Compute the overall match rate, exception rate, orphan rates for each dataset, and duplicate rates. Break down exception counts by type (value mismatch, missing record, duplicate) and by any relevant dimensions such as business unit, currency, or date range.

7. **Generate the exception list.** Produce a detailed list of all exceptions with the record key, exception type, source value, target value, variance amount or description, and a suggested resolution action (e.g., investigate source entry, re-extract from target, merge duplicates).

8. **Compile the reconciliation report.** Assemble the summary statistics, exception list, and recommendations into the standard output format. Include metadata about the datasets, reconciliation parameters, and any assumptions or limitations.

## Output Format

The reconciliation report contains the following sections:

- **Reconciliation Summary**: Datasets compared, reconciliation date, period covered, total record counts per dataset.
- **Match Statistics**: Total matched records, match rate percentage, total exceptions by type.
- **Orphan Analysis**: Source-only orphan count and sample records, target-only orphan count and sample records.
- **Value Mismatch Detail**: Table of mismatched records with key, field name, source value, target value, and absolute/percentage variance.
- **Duplicate Report**: Duplicate clusters per dataset with frequency counts and varying field details.
- **Exception List**: Complete list of all exceptions with record key, type, details, and suggested resolution action.
- **Recommendations**: Prioritised list of remediation actions for data stewards, grouped by exception type and severity.
- **Reconciliation Metadata**: Parameters used (tolerance thresholds, matching keys, transformations), data extraction timestamps, and known limitations.

## Quality Checks

- The total record counts in the report sum correctly: matched + source orphans = source total, matched + target orphans = target total.
- Tolerance thresholds are explicitly stated and applied consistently across all comparisons.
- The exception list contains no false positives caused by data type mismatches, encoding issues, or timezone discrepancies.
- Duplicate detection distinguishes between true duplicates and legitimate records that share similar but distinct key values.
- Match rate calculations use the correct denominator (typically the larger of the two dataset counts or the union count, as specified).
- Suggested resolution actions are specific and reference the system or process where correction should occur.

## Limitations

- The skill operates on static dataset snapshots provided as input; it does not connect to live databases or APIs to extract data in real time.
- Fuzzy matching for duplicate detection uses basic string similarity and may not catch all near-duplicates in datasets with complex naming conventions.
- Tolerance thresholds must be defined in the input; the skill does not infer appropriate tolerances from data distributions.
- Very large datasets (exceeding hundreds of thousands of records) may require the input to be pre-segmented, as the skill processes data within the context window.
- The skill identifies discrepancies but does not determine root cause; investigation of why mismatches occurred requires domain expertise and system access.
