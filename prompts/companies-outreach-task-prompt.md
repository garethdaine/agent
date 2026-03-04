# Find High-Probability Contract Development Clients

Identify companies in the UK and US that are likely to hire freelance or contract developers with experience in:
- Laravel
- Vue.js / React
- AI integrations / automation
- Modern web application development

The goal is to identify digital agencies, product studios, or software consultancies that may need additional development capacity.

## Prioritisation signals

Prioritise companies that show demand signals such as:
- Currently hiring developers
- Open developer job listings
- Recently announcing new projects
- Mentioning Laravel, Vue, React, or AI work
- Specialising in SaaS or custom web application development

Prefer companies that:
- Have roughly 10–80 employees
- Work with modern web stacks
- Deliver client software projects
- Likely use contract developers during busy periods

## Data to collect per company

Capture:
- Company name
- Website
- Country (UK or US)
- Industry / company type
- Tech stack (if identifiable)
- Hiring signal (job posting, careers page, etc.)
- Key decision maker (Founder / CTO / Head of Engineering)
- LinkedIn profile (if available)
- Contact email (if available)

## Additional fields to generate

### Contract Likelihood
- High / Medium / Low

### Reason
- Short explanation of why this company may need contract support

## Data sources

Use any resources required, including:
- Web search
- LinkedIn
- Company websites
- Firecrawl

## Output

Add results to:
`/Users/garethdaine/Code/agent/storage/app/public/companies.xlsx`

Required columns:
- Company
- Website
- Country
- Industry
- Tech Stack
- Hiring Signal
- Decision Maker
- LinkedIn
- Contact Email
- Contract Likelihood
- Reason

## Rules

- Do not add duplicate companies
- Add 10 new companies per run
