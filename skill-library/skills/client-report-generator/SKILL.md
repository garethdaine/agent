---
name: client-report-generator
description: |
  Generates structured client reports for accounting and advisory practices. Produces management accounts summaries, quarterly performance reviews, tax planning reports, and advisory memoranda with consistent formatting, data tables, and executive summaries.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [accounting, financial-services]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [report, client report, management accounts, advisory, quarterly review]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Client Report Generator

## Purpose

This skill generates structured, professionally formatted client reports for accounting practices, advisory firms, and financial services providers. It produces management accounts summaries, quarterly performance reviews, tax planning reports, and advisory memoranda that follow consistent formatting conventions and include executive summaries, data tables, and actionable commentary.

## When to Use

- When preparing monthly or quarterly management accounts packs for clients
- When producing year-end tax planning reports ahead of the self-assessment or corporation tax filing deadline
- When summarising advisory work performed during an engagement period for client review
- When generating quarterly investment or portfolio performance reviews for wealth management clients
- When creating board-ready financial summaries for client governance meetings
- When drafting ad-hoc advisory memoranda on topics such as R&D tax relief, capital allowances, or VAT recovery
- When producing comparative financial analysis across reporting periods for trend identification

## Instructions

1. **Confirm the report type and reporting period.** Identify whether the output is a management accounts pack, tax planning report, quarterly review, or advisory memorandum. Establish the reporting period (month-end, quarter-end, or specific date range) and the client entity name, registration number, and accounting reference date.

2. **Gather source data and context.** Read the input files containing trial balance data, prior period comparatives, budget figures, tax computations, or advisory notes. Identify the accounting framework in use (FRS 102, FRS 105 for micro-entities, or IFRS) and the applicable tax regime (UK corporation tax, income tax, or VAT).

3. **Produce the executive summary.** Write a concise summary of no more than 300 words covering key financial highlights, significant variances from budget or prior period, and any matters requiring the client's attention or decision. Use plain language appropriate for a non-accountant audience where the client is an owner-managed business.

4. **Construct financial data tables.** Build formatted tables for the profit and loss account, balance sheet, and cash flow summary as applicable. Include columns for the current period, prior period comparative, budget, and variance (both absolute and percentage). Round figures to the nearest pound for reports under GBP 1 million turnover, or to the nearest thousand for larger entities.

5. **Write commentary and analysis sections.** For each significant line item or variance, provide a brief narrative explanation. Identify trends across periods, flag unusual items, and note any adjustments made (e.g., prepayments, accruals, depreciation). For tax planning reports, detail the available reliefs, their conditions, and the estimated tax saving.

6. **Include action items and recommendations.** List specific actions the client should take, with suggested deadlines. For tax planning reports, include filing deadlines and payment dates. For management accounts, highlight areas where cost control or revenue improvement opportunities exist.

7. **Add required disclaimers and caveats.** Include a statement clarifying the basis of preparation (e.g., "These management accounts have been prepared from the records provided and have not been audited or independently verified"). For tax planning reports, note that tax legislation may change and that the analysis is based on current law and HMRC practice.

8. **Format and finalise the report.** Apply consistent heading hierarchy, page numbering, and date formatting (DD Month YYYY). Ensure all tables are aligned, totals are correctly summed, and cross-references between the executive summary and detailed sections are accurate.

## Output Format

The output is a structured markdown document with the following sections:

- **Cover Page**: Client name, report title, reporting period, date of issue, preparer name
- **Executive Summary**: Key highlights, significant variances, matters requiring attention
- **Financial Statements**: Profit and loss account table, balance sheet table, cash flow summary (where applicable), each with current period, comparative, budget, and variance columns
- **Detailed Commentary**: Narrative analysis of revenue, cost of sales, overheads, balance sheet movements, and cash flow drivers
- **Tax Position / Advisory Section**: Applicable tax computations, available reliefs, or advisory analysis depending on report type
- **Action Items**: Numbered list of recommended actions with responsible parties and deadlines
- **Appendices**: Supporting schedules, aged debtor/creditor listings, or detailed workings as needed
- **Disclaimers**: Basis of preparation, limitations, and professional body references

## Quality Checks

- All financial figures in the executive summary must be traceable to the corresponding data tables
- Variance explanations must be provided for any line item deviating more than 10% from budget or prior period
- Tax rates and allowance thresholds must reflect the current tax year (2025/26 for income tax, or the relevant corporation tax financial year)
- Rounding must be applied consistently throughout the report with no rounding discrepancies in totals
- The report must not contain technical accounting jargon without explanation when addressed to non-accountant clients
- All dates must use DD Month YYYY format and all currency figures must use GBP notation with commas as thousand separators
- Disclaimers must be present and must accurately reflect the scope of work performed

## Limitations

- The skill generates reports from data provided in the input files and cannot independently verify the accuracy or completeness of underlying accounting records
- It does not perform audit procedures or provide assurance on the financial information presented
- Tax computations are based on current enacted legislation and published HMRC guidance; they do not account for pending legislative changes or HMRC enquiries
- The skill cannot access live accounting software (Xero, QuickBooks, Sage) directly; data must be exported and provided as input files
- Complex group structures with intercompany eliminations or foreign currency consolidation may require manual adjustment beyond the skill's output
- The skill does not produce FCA-regulated investment advice; portfolio performance commentary is factual and descriptive only
