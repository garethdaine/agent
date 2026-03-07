---
name: stock-reconciliation
description: |
  Performs stock reconciliation across inventory management systems for manufacturing and retail operations. Compares physical stock counts against system records, identifies variances by SKU and location, calculates shrinkage rates, and generates exception reports with recommended write-off or adjustment entries.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [manufacturing, retail, food-manufacturing]
  risk_level: low
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [stock, reconciliation, inventory, stocktake, shrinkage, variance, warehouse]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Stock Reconciliation

## Purpose

Reconciles physical stock count data against system inventory records to identify variances, calculate shrinkage rates, and produce exception reports with recommended adjustment entries. The skill supports manufacturing, retail, and food production environments where accurate stock records are essential for production planning, financial reporting, and regulatory compliance including food traceability requirements.

## When to Use

- A full or partial physical stocktake has been completed and the count data needs reconciling against the inventory management system
- Month-end or period-end stock valuation is due and the finance team needs a variance report before closing the books
- A cycle count programme has flagged discrepancies on specific SKUs or storage locations that need investigation
- Production yield variances suggest that raw material stock records may not reflect actual consumption accurately
- An audit requirement demands documented evidence of stock reconciliation procedures and adjustments
- A food manufacturing site needs to verify batch-level traceability records align with physical stock positions before a retailer audit

## Instructions

1. Ingest the physical stock count data, which should include the SKU or item code, item description, storage location, batch or lot number where applicable, unit of measure, and the counted quantity. Also ingest the system inventory extract for the same date or cut-off point, containing the same fields plus the system-recorded quantity.

2. Match each physical count line to the corresponding system record by SKU and location. Where batch-level tracking is in use, match at the SKU, location, and batch level. Identify any items present in the physical count but absent from the system records (unrecorded stock) and any items in the system records with no corresponding physical count line (missing stock).

3. Calculate the variance for each matched line as the physical count quantity minus the system quantity. Express each variance in absolute units and as a percentage of the system quantity. Classify each variance against the tolerance thresholds provided, or apply default thresholds of plus or minus 2% for raw materials, 1% for finished goods, and 5% for packaging and consumables.

4. For items exceeding the variance threshold, attempt to identify probable causes by cross-referencing against recent goods received notes, despatch records, production orders, waste and scrap logs, and inter-location transfer records. Flag any variances where no probable cause can be identified, as these may indicate process failures, data entry errors, or stock loss requiring further investigation.

5. Calculate the overall shrinkage rate for the reconciliation period as the total negative variance value divided by the total system stock value, expressed as a percentage. Break down shrinkage by category (raw materials, work-in-progress, finished goods, packaging) and by location where multiple storage areas are in scope.

6. Generate the recommended stock adjustment entries for each variance. For items within tolerance, recommend automatic adjustment. For items exceeding tolerance where a probable cause has been identified, recommend adjustment with the cause code noted. For unexplained variances exceeding tolerance, recommend the item be placed on hold for recount or investigation before any adjustment is processed.

7. For food manufacturing environments, verify that any batch-level adjustments do not break traceability chains. Confirm that the adjusted batch quantities remain consistent with goods-in records and production batch records so that a forward or backward trace would still produce a complete and accurate result.

## Output Format

The output should be a structured reconciliation report containing:

- **Reconciliation Summary**: Date, scope, total SKUs counted, total SKUs in system, match rate, total stock value at system quantities, total stock value at counted quantities
- **Variance Summary**: Total positive variance (overstocks), total negative variance (shortages), net variance, overall shrinkage rate percentage
- **Category Breakdown**: Shrinkage and variance statistics broken down by stock category and storage location
- **Exception Report**: All items exceeding tolerance thresholds, showing the SKU, location, batch, system quantity, counted quantity, variance, percentage variance, probable cause where identified, and recommended action
- **Adjustment Register**: List of all recommended adjustment entries with SKU, location, batch, adjustment quantity, adjustment value, cause code, and approval status (auto-approve or requires investigation)
- **Unreconciled Items**: Separate lists for unrecorded stock found during the count and system records with no physical match

## Quality Checks

- Every line in the physical count data has been processed and either matched or reported as unreconciled
- Variance percentages are calculated using the system quantity as the denominator, not the physical count
- Shrinkage rate calculation uses stock values, not unit quantities, to avoid distortion from high-volume low-value items
- Tolerance thresholds applied match those specified in the input or the documented defaults; no thresholds have been silently overridden
- Batch-level traceability has been preserved in all recommended adjustments for food manufacturing reconciliations
- The adjustment register net total reconciles exactly to the net variance reported in the summary

## Limitations

- Cannot perform physical stock counts; the skill operates on count data provided and does not validate counting accuracy
- Does not have access to live inventory management system data; reconciliation is performed against a point-in-time extract provided by the user
- Probable cause identification is based on pattern matching against related transaction records and is suggestive rather than conclusive; unexplained variances still require human investigation
- Stock valuation uses the unit cost provided in the system extract and does not recalculate costs using FIFO, weighted average, or other costing methods unless the extract already reflects the correct valuation basis
- Cannot assess whether variance patterns indicate theft, fraud, or systematic process failure; this requires operational management judgement beyond the scope of a reconciliation report
- For food manufacturing, the skill checks traceability record consistency but does not validate whether physical segregation of batches is maintained in the warehouse
