---
name: job-costing-analyzer
description: |
  Analyses job costing data for construction and facilities projects. Compares actual costs against budgeted estimates, identifies cost overruns by trade or phase, calculates earned value metrics, and produces variance reports with trend analysis for project managers.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [construction, facilities-management]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [job costing, cost analysis, budget variance, construction costs, earned value, project costing]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Job Costing Analyzer

## Purpose

Analyses actual expenditure against budgeted estimates for construction projects and facilities maintenance contracts. The skill processes cost data broken down by trade package, RIBA work stage, or NRM cost element, calculates earned value management metrics, and produces variance reports that highlight overruns, underspends, and forecast final account positions. Output is designed for quantity surveyors, commercial managers, and project directors who need to make informed decisions about cost control and cash flow.

## When to Use

- At monthly cost reporting intervals to compare committed and actual costs against the contract sum or approved budget
- When a quantity surveyor needs to prepare a cost-value reconciliation or commercial status report for a project review meeting
- To identify which trade packages or subcontractor accounts are driving cost overruns on a live construction project
- During facilities management contract reviews where planned preventive maintenance costs need to be compared against reactive maintenance spend
- When preparing earned value analysis for NEC3/NEC4 contract compensation event assessments
- To generate trend data for inclusion in employer's agent reports or funder draw-down submissions

## Instructions

1. Read the input cost data file containing line items with the following expected fields: cost code or NRM element reference, description, budgeted amount, committed amount (orders placed), actual amount (invoices certified or paid), forecast final cost, and the work package or trade category. Accept CSV or JSON formats.

2. Validate the input data for completeness and consistency. Check that all monetary values are in GBP, that cost codes follow a recognisable structure (NRM1, NRM2, or the project's bespoke coding system), and that the sum of line items reconciles to the stated contract sum or budget total within a tolerance of 0.5%. Report any discrepancies in a data validation section.

3. Calculate the following metrics for each cost code and for the project as a whole:
   - **Budget Variance** — the difference between the budgeted amount and the forecast final cost, expressed in GBP and as a percentage
   - **Commitment Coverage** — the percentage of the budget that has been committed through placed orders or subcontract agreements
   - **Cost to Complete** — the forecast final cost minus the actual cost incurred to date
   - **Earned Value (EV)** — if percentage complete data is provided, calculate EV as budget multiplied by percentage complete
   - **Cost Performance Index (CPI)** — EV divided by actual cost; values below 1.0 indicate overrun
   - **Schedule Performance Index (SPI)** — EV divided by planned value, where planned value is derived from the programme baseline if provided

4. Identify the top five cost overruns by absolute variance and by percentage variance. For each, include the cost code, description, budget, forecast, variance amount, and a brief narrative explaining likely causes based on patterns in the data (e.g., provisional sum adjustments, scope changes, remeasurement differences).

5. Produce a cash flow comparison showing cumulative budgeted spend versus cumulative actual spend by reporting period. If historical period data is available in the input, generate a month-by-month breakdown. Flag any periods where actual spend exceeds the budgeted S-curve by more than 10%.

6. Generate a risk-adjusted forecast section that applies a contingency percentage (default 5% unless specified in the input data) to uncommitted budget elements. Present three scenarios: optimistic (contingency not required), expected (partial contingency draw), and pessimistic (full contingency plus a further 3% risk allowance).

7. Format the output as a structured markdown report with summary tables, detailed line-item breakdowns, and clearly labelled metric calculations. Include a commercial status traffic light (Green/Amber/Red) based on the overall CPI value: Green for CPI above 0.95, Amber for 0.85 to 0.95, and Red for below 0.85.

## Output Format

The report is produced as a structured markdown document:

```
# Job Costing Analysis Report
## Project Summary
- Project name, contract sum, report date, reporting period
- Overall CPI, SPI, commercial status traffic light
## Data Validation
- Reconciliation check results, any flagged discrepancies
## Cost Summary Table
| Cost Code | Description | Budget | Committed | Actual | Forecast | Variance | Variance % |
## Top Overruns
- Ranked list with narrative commentary
## Earned Value Metrics
| Metric | Value |
- EV, CPI, SPI for each major work package and overall
## Cash Flow Comparison
| Period | Budgeted Cumulative | Actual Cumulative | Variance |
## Forecast Scenarios
| Scenario | Forecast Final Account | Contingency Draw | Net Position |
## Recommendations
- Commercial actions required based on analysis
```

## Quality Checks

- All monetary values must be presented in GBP with two decimal places and consistent thousand-separator formatting
- The sum of individual line-item budgets must reconcile to the stated contract sum or total budget within the 0.5% tolerance
- CPI and SPI values must be calculated only when the underlying data (percentage complete, planned value) is available; metrics must not be fabricated from insufficient data
- Variance percentages must be calculated against the original budget, not the forecast or committed figures
- The commercial status traffic light must be consistent with the stated CPI thresholds
- Cost codes must appear in ascending order within each section for ease of cross-referencing with the project cost plan

## Limitations

- Does not replace professional quantity surveying judgement for valuation disputes, final account negotiations, or contractual claims assessment
- Cannot access live accounting systems or ERP platforms; all data must be provided as a static input file
- Earned value calculations require percentage complete data; if this is not provided, EV metrics will be omitted from the report with an explanatory note
- Does not account for VAT, CIS deductions, or retention calculations unless these are explicitly included as separate line items in the input data
- Forecasting scenarios use simplified contingency models and do not constitute a formal risk register or Monte Carlo simulation
- Currency conversion is not supported; all input values must be in GBP
