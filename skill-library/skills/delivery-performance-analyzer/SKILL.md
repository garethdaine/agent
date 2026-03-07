---
name: delivery-performance-analyzer
description: |
  Analyses delivery performance metrics across supply chain operations. Calculates on-time delivery rates, lead time variances, delivery accuracy percentages, and carrier performance scorecards. Produces trend analysis reports identifying bottlenecks and seasonal patterns in distribution networks.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [logistics, supply-chain, retail]
  risk_level: low
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [delivery, performance, on-time, logistics metrics, carrier scorecard, supply chain KPI]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Delivery Performance Analyzer

## Purpose

Analyses delivery records and shipment tracking data to produce performance metrics, carrier scorecards, and trend reports for logistics and supply chain operations. The skill enables distribution managers to identify underperforming routes, carriers, or time periods and supports data-driven decisions on carrier selection, service level agreements, and network optimisation.

## When to Use

- A logistics manager needs a monthly or quarterly delivery performance report covering on-time delivery rates and service failures.
- Carrier contract renewals are approaching and comparative performance scorecards are required to support negotiation.
- A distribution centre is experiencing rising complaint volumes and the operations team needs to isolate whether the cause is carrier performance, warehouse dispatch delays, or order processing backlogs.
- Seasonal planning requires historical trend analysis to forecast peak period capacity requirements and identify routes prone to delay during specific months.
- A supply chain director requires KPI dashboards summarising delivery accuracy, lead time consistency, and cost-per-delivery across multiple regions or business units.

## Instructions

1. Ingest the delivery records dataset, which should include at minimum: order reference, promised delivery date, actual delivery date, carrier name, origin depot or warehouse, destination postcode or region, consignment weight, and delivery status (delivered, failed, returned, partially delivered).
2. Calculate the on-time in-full (OTIF) delivery rate by comparing actual delivery dates and quantities against promised dates and ordered quantities. Classify each delivery as on-time, late (1-2 days), significantly late (3+ days), or failed.
3. Compute lead time metrics for each route and carrier combination: average lead time, median lead time, 95th percentile lead time, and standard deviation. Identify routes where lead time variance exceeds acceptable thresholds.
4. Build carrier performance scorecards aggregating OTIF rate, average lead time, delivery failure rate, damage claim rate (if data is available), and cost per consignment. Rank carriers within each service category (next-day, 48-hour, economy, palletised freight).
5. Perform trend analysis across the reporting period, breaking performance down by week or month. Identify statistically significant changes in performance, seasonal patterns, and any correlation between volume spikes and service degradation.
6. Segment results by geography (postcode area or region), product category or order type where applicable, and customer tier if service level differentiation exists.
7. Generate an exceptions list highlighting the worst-performing routes, carriers with declining trends over three or more consecutive periods, and any individual consignments with extreme delays exceeding the 99th percentile.

## Output Format

**Performance Summary** containing headline KPIs:
- Overall OTIF rate (percentage)
- Average lead time (days/hours)
- Delivery failure rate (percentage)
- Total consignments analysed
- Reporting period covered

**Carrier Scorecards** presented as a comparison table with one row per carrier, columns for each metric, and a composite performance score normalised to 100.

**Trend Analysis** presented as a period-by-period breakdown (weekly or monthly) showing OTIF rate, average lead time, and volume for each period, with directional indicators (improving, stable, declining).

**Exceptions Report** listing individual problem areas with: route or carrier identification, metric affected, current value versus target, duration of underperformance, and suggested investigation action.

## Quality Checks

- Date parsing handles both DD/MM/YYYY (UK format) and ISO 8601 formats correctly, with explicit handling of ambiguous dates.
- Consignments with missing delivery dates are excluded from OTIF calculations but counted separately as unresolved.
- Percentage calculations use the correct denominator (total eligible consignments, not total records) to avoid skewing by incomplete data.
- Carrier names are normalised to account for variations in naming conventions (e.g. "DPD", "DPD Local", "DPD UK" mapped correctly according to context).
- Statistical trend detection uses a minimum sample size of 30 consignments per period before drawing conclusions about performance changes.
- Bank holidays and known non-delivery days (Sundays for most UK carriers) are excluded from lead time calculations.

## Limitations

- Does not integrate with live carrier tracking APIs; analysis is performed on historical data extracts provided as input files.
- Cannot determine the root cause of delivery failures (e.g. whether a failed delivery was due to an incorrect address, customer absence, or carrier error) unless cause codes are included in the source data.
- Cost analysis requires cost-per-consignment data to be present in the input; the skill does not look up carrier rate cards.
- Forecasting and predictive analytics are not performed; the skill reports on historical trends and leaves forward projections to the analyst.
- Multi-leg shipments (e.g. linehaul plus final mile) are treated as a single delivery unless the input data provides separate tracking events for each leg.
