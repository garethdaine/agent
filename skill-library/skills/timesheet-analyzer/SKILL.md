---
name: timesheet-analyzer
description: |
  Analyses timesheet data for professional services, recruitment, and project-based organisations. Calculates utilisation rates, billable vs non-billable ratios, overtime patterns, and project time allocation. Identifies anomalies such as excessive hours, missing entries, and unapproved overtime for payroll and billing accuracy.
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
  trigger_keywords: [timesheet, utilisation, billable hours, overtime, time tracking, payroll, project time]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Timesheet Analyzer

## Purpose

The Timesheet Analyzer skill processes timesheet data to calculate workforce utilisation metrics, identify billing and payroll anomalies, and provide management visibility into how time is allocated across projects, clients, and activity categories. It is designed for professional services firms, recruitment agencies, consultancies, and any project-based organisation where accurate time tracking directly affects revenue recognition, client billing, and resource planning. The skill transforms raw timesheet entries into actionable intelligence about team productivity, project health, and compliance with working time policies.

## When to Use

- A professional services firm needs weekly or monthly utilisation reporting across teams and individuals.
- Finance requires validation of timesheet data before generating client invoices or processing payroll.
- A project manager needs to assess whether a project is consuming time in line with its budget and estimate at completion.
- HR or compliance needs to identify employees who are consistently exceeding maximum working hour thresholds.
- Management wants to understand the ratio of billable to non-billable time and identify opportunities to improve utilisation.
- A resource planning team needs historical time allocation data to inform staffing decisions for upcoming projects.
- Internal audit requires evidence that overtime hours are appropriately approved and recorded.

## Instructions

1. **Ingest and validate timesheet data.** Parse the provided timesheet records, confirming that each entry contains the required fields: employee identifier, date, project or client code, activity category, hours logged, and billable/non-billable classification. Flag entries with missing or malformed fields as data quality exceptions.

2. **Calculate individual utilisation rates.** For each employee, compute the utilisation rate as total billable hours divided by total available hours for the analysis period. Available hours should account for standard working hours minus approved leave, public holidays, and any other non-working days specified in the input. Segment utilisation by week and by the full period.

3. **Analyse billable vs non-billable breakdown.** Categorise all logged hours into billable client work, non-billable client work (e.g., pre-sales, proposals), internal projects, administrative time, training, and leave. Calculate the percentage allocation across these categories at individual, team, and organisational levels.

4. **Assess project time allocation.** For each project or client code, aggregate total hours by role or grade, compare against budget if provided, calculate the percentage of budget consumed, and project the estimate at completion based on current burn rate. Flag projects that have consumed more than 80% of their time budget with more than 20% of deliverables remaining.

5. **Detect overtime and working time anomalies.** Identify entries where daily hours exceed the standard working day (typically 8 hours unless specified otherwise), where weekly totals exceed the defined maximum (typically 48 hours under working time regulations), or where time is logged on weekends or public holidays without prior approval flags. Quantify overtime hours by employee and period.

6. **Identify missing and suspicious entries.** Detect employees with missing timesheet submissions for any working day in the analysis period. Flag entries with round-number hours on every day (potential block-filling), identical daily patterns repeated across the entire period, or single entries that account for an unusually large proportion of weekly hours.

7. **Generate summary metrics and rankings.** Produce team-level and organisation-level summary statistics including average utilisation, median utilisation, standard deviation, top and bottom quartile performers, total billable hours, total overtime hours, and timesheet compliance rate (percentage of employees with complete submissions).

## Output Format

The timesheet analysis report contains the following sections:

- **Analysis Summary**: Period covered, total employees analysed, total hours logged, overall utilisation rate, and timesheet compliance rate.
- **Utilisation Dashboard**: Table of employees with their total available hours, billable hours, non-billable hours, utilisation rate, and week-over-week trend indicator.
- **Time Allocation Breakdown**: Percentage distribution across activity categories at team and organisational level, presented as a summary table.
- **Project Time Report**: Table of projects with total hours logged, budget hours, percentage consumed, estimated hours at completion, and budget variance flag.
- **Overtime Report**: List of employees with overtime hours, daily and weekly maximums reached, weekend or holiday entries, and approval status where available.
- **Anomaly List**: All flagged entries with employee identifier, date, anomaly type (missing entry, excessive hours, suspicious pattern, data quality issue), and recommended action.
- **Recommendations**: Prioritised list of management actions based on the analysis findings, such as addressing chronic under-utilisation, investigating overtime patterns, or improving timesheet compliance processes.

## Quality Checks

- Utilisation rate calculations correctly exclude approved leave and public holidays from the available hours denominator.
- Billable and non-billable hour totals for each employee sum to their total logged hours without discrepancy.
- Project time aggregations match the sum of individual timesheet entries allocated to that project code.
- Overtime detection thresholds align with the working time policy or regulatory limits specified in the input.
- Missing entry detection accounts for part-time employees, staggered start dates, and approved absences rather than flagging them as missing submissions.
- Anomaly flags distinguish between confirmed issues and patterns that warrant investigation, avoiding false accusations of timesheet fraud.

## Limitations

- The skill analyses timesheet data as provided and cannot verify whether logged hours accurately reflect actual work performed.
- Utilisation benchmarks and targets vary significantly by industry, role, and organisation; the skill reports calculated rates but does not define what constitutes "good" utilisation without explicit targets in the input.
- The skill does not integrate with payroll, billing, or HR systems in real time; results are based on the static dataset provided.
- Anomaly detection uses statistical heuristics and pattern matching; flagged entries require human review to determine whether they represent genuine issues or legitimate exceptions.
- Multi-currency rate card analysis and revenue recognition calculations are outside the scope of this skill; it focuses on hours and time allocation rather than financial values.
- Working time regulations vary by jurisdiction; the skill applies the thresholds provided in the input and does not independently determine the applicable legal framework.
