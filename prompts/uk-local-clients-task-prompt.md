# Find High-Probability Pilot Clients for Agent Ops Platform

Agent is a local-first AI agent orchestration platform designed to help businesses safely automate internal workflows using AI agents while maintaining reliability, auditability, and cost controls.

The immediate goal is to identify UK companies that would be strong candidates for an early pilot deployment where we automate one internal workflow and demonstrate measurable operational improvement.

## Target profile

Companies should:
- Be UK-based
- Have approximately 10–150 employees
- Operate in industries with repetitive operational workflows
- Be small enough to adopt new technology quickly
- Benefit from automating internal processes

Prioritise sectors:
- Accountancy firms
- Managed Service Providers (MSPs)
- Logistics companies
- Recruitment agencies
- Construction management firms
- Compliance or regulatory consultancies
- Financial advisory firms
- Professional services firms

Avoid:
- Very large enterprises
- Very early startups (fewer than 5 employees)

## Data to collect per company

Capture:
- Company name
- Industry / niche
- Website
- Location
- Estimated employee count (if available)
- Key decision maker (Founder / Managing Director / Head of Ops / Head of IT)
- LinkedIn profile (if available)
- Contact email (if available)
- Any signals they may benefit from automation (document-heavy work, reporting-heavy work, repetitive admin, compliance operations, etc.)

## Additional fields to generate

### Automation Opportunity Score
- High / Medium / Low

### Reason for score
- Brief explanation of why they are a good pilot candidate

### Potential workflow to automate
- Suggest 1 realistic internal workflow to automate using AI agents (e.g. document processing, reporting generation, compliance monitoring, data reconciliation)

## Data sources

Use any resources required, including:
- Web search
- LinkedIn
- Company websites
- X.com
- Firecrawl

## Output

Add results to:
`/Users/garethdaine/Code/agent/storage/app/public/agent-outreach.xlsx`

Required columns:
- Company
- Industry
- Website
- Location
- Employee Estimate
- Decision Maker
- LinkedIn
- Contact Email
- Automation Opportunity Score
- Reason for Score
- Potential Workflow

## Rules

- Do not add duplicates
- Add 10 new companies per run
