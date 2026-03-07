---
name: contract-clause-reviewer
description: |
  Reviews contract clauses for legal risk, enforceability, and compliance with standard terms. Identifies unusual provisions, missing standard protections, liability exposure, and indemnity imbalances across commercial agreements, NDAs, service contracts, and licensing deals.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [legal]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [contract, clause, legal review, indemnity, liability, NDA, agreement]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Contract Clause Reviewer

## Purpose

Performs structured review of contract clauses to identify legal risk, enforceability concerns, and deviations from standard commercial terms under English and Welsh law. The skill flags unusual or onerous provisions, missing boilerplate protections, and imbalanced liability or indemnity positions that may expose the client to disproportionate risk. It is designed to support fee earners during the initial review stage of incoming or outgoing commercial agreements.

## When to Use

- When a new commercial agreement, NDA, or service contract has been received from the counterparty and requires initial clause-level review before markup
- When drafting or amending contracts and needing a second-pass check for missing standard protections such as limitation of liability caps, force majeure, or termination for convenience
- When comparing contract terms against the firm's approved precedent library or playbook positions
- When a client queries specific clauses and needs a plain-English risk summary before signing
- When reviewing licence agreements, framework agreements, or consultancy terms for SME clients who lack in-house legal teams

## Instructions

1. Read the full contract document and identify the governing law, jurisdiction clause, and contract type (e.g., SPA, NDA, MSA, consultancy agreement, licence). Note any choice of law issues, particularly where the contract purports to be governed by a non-England and Wales jurisdiction.

2. Extract and categorise each material clause by type: definitions, term and termination, liability and indemnity, intellectual property, confidentiality, data protection, warranties and representations, force majeure, dispute resolution, and any bespoke or unusual provisions.

3. Assess each liability and indemnity clause for balance. Flag uncapped liability, indemnities that extend beyond direct loss, consequential loss carve-outs that favour one party disproportionately, and any provisions that attempt to exclude liability for fraud, death, or personal injury (which are unenforceable under the Unfair Contract Terms Act 1977 and Consumer Rights Act 2015).

4. Review termination provisions for adequacy. Check for appropriate notice periods, termination for cause triggers, termination for convenience rights, and the consequences of termination including survival clauses, return of materials, and accrued rights.

5. Identify missing standard protections by comparing against expected boilerplate for the contract type. Common omissions include: entire agreement clauses, no waiver provisions, severability, assignment restrictions, third-party rights exclusions under the Contracts (Rights of Third Parties) Act 1999, and adequate data processing provisions where personal data is involved.

6. Flag any clauses that may be unenforceable or challengeable, including unreasonable restrictive covenants, penalty clauses that do not represent a genuine pre-estimate of loss (applying the Cavendish/ParkingEye test), and clauses that may fall foul of the Competition Act 1998.

7. Produce a clause-by-clause risk assessment with a severity rating (high, medium, low) for each flagged item, together with a recommended position and suggested alternative drafting where appropriate.

8. Generate an executive summary suitable for the supervising partner or client, listing the top risks, recommended negotiation points, and any clauses that require immediate attention before the contract can be executed.

## Output Format

The output should be structured as follows:

**Contract Overview**
- Document title, parties, governing law, contract type, and date

**Clause Risk Register**
A table with columns: Clause Reference | Clause Type | Risk Level (High/Medium/Low) | Issue Identified | Recommended Position

**Detailed Findings**
For each flagged clause, provide:
- The clause text or summary
- The specific risk or concern
- The legal basis for the concern (citing relevant legislation or case law where applicable)
- Suggested alternative wording or negotiation strategy

**Executive Summary**
- Total clauses reviewed
- Number of high/medium/low risk findings
- Top three priority items for negotiation
- Overall risk assessment and recommendation (proceed / proceed with amendments / do not proceed without material changes)

## Quality Checks

- All liability and indemnity provisions have been assessed for enforceability under UCTA 1977 and CRA 2015
- Termination provisions include adequate notice periods and survival clauses
- Data protection clauses have been checked for UK GDPR compliance where personal data processing is in scope
- No clauses have been overlooked in the definitions section that could alter the interpretation of operative provisions
- Restrictive covenants and non-compete clauses have been assessed for reasonableness in scope, geography, and duration
- The governing law and jurisdiction clause is consistent throughout the agreement and does not conflict with dispute resolution provisions
- All cross-references within the contract have been verified for accuracy

## Limitations

- This skill provides a structured review framework and does not constitute formal legal advice; all output should be reviewed and approved by a qualified solicitor before being relied upon or shared with the client
- The skill operates under the assumption of English and Welsh law unless otherwise specified; contracts governed by Scottish, Northern Irish, or foreign law require jurisdiction-specific expertise
- Complex bespoke provisions, particularly in financial services, construction, or heavily regulated sectors, may require specialist input beyond the scope of this general review
- The skill does not perform a conflicts check, anti-money laundering assessment, or sanctions screening, which must be completed separately in accordance with the firm's compliance procedures
- Quantitative financial risk modelling (e.g., exposure calculations based on contract value) is outside the scope of this clause review
