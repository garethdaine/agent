---
name: rota-optimizer
description: |
  Optimizes staff scheduling rotas for healthcare and care providers. Analyses shift patterns, staff availability, qualification requirements, and working time regulations to produce balanced rotas that ensure adequate coverage while minimizing overtime and agency costs.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [healthcare, social-care]
  risk_level: low
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [rota, scheduling, shift, staffing, roster, overtime, working time]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Rota Optimizer

## Purpose

Analyses existing staff rotas and operational requirements to produce optimised shift schedules for healthcare and social care settings. The skill balances clinical coverage needs, staff contractual hours, qualification mix requirements, and Working Time Regulations 1998 compliance to generate rotas that reduce reliance on agency staff and distribute shifts equitably. It is designed for use across residential care homes, domiciliary care services, GP surgeries, and community healthcare teams.

## When to Use

- Building a new weekly or fortnightly rota from scratch when staff availability, contracted hours, and coverage requirements are known.
- Rebalancing an existing rota after unplanned absences, long-term sickness, or staff turnover to maintain safe staffing levels.
- Evaluating whether current shift patterns comply with the Working Time Regulations 1998, including maximum weekly hours, rest break entitlements, and night worker protections.
- Reducing agency and bank staff expenditure by identifying opportunities to fill gaps with substantive staff who have unused contracted hours.
- Ensuring that each shift has the required skill mix, such as a minimum number of registered nurses, senior carers, or staff trained in specific interventions like PEG feeding or insulin administration.
- Planning ahead for known periods of increased demand such as bank holidays, winter pressures, or planned staff annual leave.

## Instructions

1. Collect the input data: a staff list with each person's contracted hours, role, qualifications, availability constraints, and any leave already booked. Collect the shift structure including start and end times for each shift type (e.g., early, late, waking night, sleep-in) and the minimum staffing requirements per shift including role and qualification mix.
2. Validate the input against Working Time Regulations 1998 constraints. Calculate each staff member's maximum available hours for the rota period, ensuring no individual exceeds 48 hours per week on average (unless a valid opt-out is recorded), that a minimum 11-hour rest period is maintained between shifts, and that night workers do not exceed an average of 8 hours per 24-hour period.
3. Map qualification requirements to shifts. Identify which shifts require specific competencies such as medication administration, moving and handling lead, or registered nurse presence. Tag each staff member with their verified competencies and filter eligible staff for each shift accordingly.
4. Generate an initial rota allocation by assigning staff to shifts in priority order: first fill shifts requiring specialist qualifications, then fill remaining shifts by distributing hours as evenly as possible across the team relative to each person's contracted hours. Respect availability constraints and leave bookings throughout.
5. Run a fairness analysis across the draft rota. Check that weekend shifts, night shifts, and bank holiday shifts are distributed equitably among eligible staff. Flag any individual who is allocated disproportionately more unsocial hours than their peers without a contractual reason.
6. Identify any remaining gaps where the minimum staffing requirement cannot be met with available substantive staff. For each gap, note the shift date, time, role required, and qualifications needed, and flag it as requiring agency or bank cover.
7. Calculate the cost summary for the rota period, distinguishing between substantive staff costs (based on contracted and overtime hours), bank staff costs, and projected agency costs for unfilled gaps. Compare against the previous period if historical data is available.
8. Output the finalised rota along with a compliance summary, fairness metrics, gap report, and cost breakdown.

## Output Format

The output is a structured rota package in markdown containing:

- **Rota Grid**: A tabular view with dates across the top and staff names down the side, showing assigned shift codes (E = Early, L = Late, N = Night, S = Sleep-in, RD = Rest Day, AL = Annual Leave) for each day.
- **Shift Detail**: For each date, a breakdown of which staff are assigned to each shift, their role, and any specialist competencies they bring to that shift.
- **Compliance Summary**: Confirmation of Working Time Regulations adherence for each staff member, including average weekly hours, rest period compliance, and night worker status.
- **Fairness Metrics**: A table showing each staff member's total allocated hours versus contracted hours, number of weekend shifts, number of night shifts, and number of bank holiday shifts for the period.
- **Gap Report**: A list of shifts where minimum staffing could not be achieved with substantive staff, including the date, shift type, role needed, and qualifications required.
- **Cost Breakdown**: Estimated costs split by substantive hours (standard and overtime rates), bank staff hours, and projected agency fill costs.

## Quality Checks

- No staff member is scheduled in a way that violates the 11-hour minimum rest period between shifts unless a specific regulatory exemption applies to their role and setting.
- Every shift meets the defined minimum staffing level for headcount, role mix, and qualification requirements. Any shortfall is explicitly flagged in the gap report rather than silently accepted.
- Contracted hours are respected. No staff member is allocated fewer hours than their contract guarantees without an explanatory note, and overtime is only allocated where substantive hours have been fully utilised across the team.
- The fairness distribution of unsocial hours does not deviate by more than one shift from the team average per staff member per rota period, unless constrained by qualification requirements or contractual terms.
- Bank holiday shifts are allocated in accordance with the service's bank holiday rota policy, typically rotating fairly across the year rather than falling repeatedly on the same individuals.
- The cost breakdown uses accurate pay rates for standard hours, overtime, enhanced rates for nights and weekends, and current agency framework rates.

## Limitations

- The skill does not integrate with external HR or rota management systems such as Rotageek, Allocate, or NHS Rostering. Data must be provided as input files.
- Staff preferences and informal shift swap arrangements are not automatically captured. Only formally recorded availability and leave are factored into the optimisation.
- The skill applies Working Time Regulations 1998 as the baseline. Sector-specific guidance such as NHS Employers terms and conditions (Agenda for Change) or local authority terms must be specified in the input if they impose additional constraints.
- Cost calculations are estimates based on provided pay rates. Actual costs may differ due to factors such as pension contributions, employer National Insurance, and agency booking fees that are outside the scope of this skill.
- The skill does not account for travel time between locations for domiciliary care staff. Where travel time is a significant factor, it should be built into the shift definitions provided as input.
- Rotas are generated for a single service location. Multi-site optimisation across a provider's portfolio of services is not supported in a single run.
