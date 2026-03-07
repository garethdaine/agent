---
name: engagement-letter-generator
description: |
  Drafts engagement letters for accounting and professional services firms. Covers audit engagements, tax advisory, bookkeeping services, and consultancy agreements. Ensures compliance with ICAEW, ACCA, and local professional body requirements for terms of engagement.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [accounting]
  risk_level: elevated
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [engagement letter, terms of engagement, audit engagement, professional services, ICAEW, ACCA]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Engagement Letter Generator

## Purpose

This skill drafts engagement letters for accounting firms, audit practices, and professional services providers. It produces letters that set out the scope of work, responsibilities of both parties, fee arrangements, and applicable terms and conditions in accordance with ICAEW, ACCA, and other UK professional body requirements for terms of engagement.

## When to Use

- When onboarding a new client and establishing the terms of a recurring engagement (annual accounts, tax returns, payroll)
- When issuing an audit engagement letter in compliance with ISA (UK) 210 for statutory audit appointments
- When varying the scope of an existing engagement, such as adding tax advisory or consultancy services
- When re-issuing engagement letters following a change in regulatory requirements or professional body guidance
- When setting up a bookkeeping or management accounts engagement with clear deliverables and deadlines
- When documenting the terms of a one-off advisory project such as due diligence, business valuation, or R&D tax credit claims
- When a new partner takes over responsibility for an existing client and the engagement letter requires updating

## Instructions

1. **Identify the engagement type and scope.** Determine the specific services to be provided: statutory audit, accounts preparation, corporation tax, personal tax (self-assessment), VAT returns, payroll bureau, bookkeeping, management accounts, advisory, or a combination. For each service, note the applicable reporting framework and statutory deadlines.

2. **Establish the parties and entity details.** Record the full legal name of the client entity, company registration number (if applicable), registered office address, principal contact, and the accounting reference date or tax year-end. Record the firm's details including its registered name, professional body membership (ICAEW, ACCA, AAT), and relevant regulatory authorisations.

3. **Define the respective responsibilities.** Draft clauses setting out the firm's responsibilities (to perform the engagement with reasonable skill and care, to report to the appropriate parties) and the client's responsibilities (to provide complete and accurate information on a timely basis, to maintain adequate accounting records, to disclose all relevant matters). For audit engagements, include the directors' responsibilities statement as required by ISA (UK) 210.

4. **Set out the fee basis and payment terms.** Specify whether fees are fixed, time-based, or a combination. Include the hourly or day rates for different staff grades where applicable. State the billing frequency (monthly, quarterly, on completion), payment terms (e.g., 30 days from invoice date), and the consequences of late payment including the right to charge interest under the Late Payment of Commercial Debts (Interest) Act 1998.

5. **Include professional and regulatory clauses.** Add clauses covering: professional indemnity insurance and its limitations; the firm's complaints procedure; the client's right to refer unresolved complaints to the relevant professional body; anti-money laundering obligations under the Money Laundering, Terrorist Financing and Transfer of Funds (Information on the Payer) Regulations 2017; and the requirement for client identification and verification.

6. **Draft data protection provisions.** Include a data processing clause that identifies the firm as a data controller (or joint controller/processor as appropriate), states the lawful basis for processing, describes the categories of personal data processed, specifies the retention period, and references the firm's privacy notice. Ensure compliance with UK GDPR and the Data Protection Act 2018.

7. **Add limitation of liability and termination clauses.** Include a clause limiting the firm's aggregate liability to a specified multiple of fees or a fixed monetary cap, subject to the restriction that liability for death, personal injury, or fraud cannot be excluded. Draft termination provisions allowing either party to terminate with a specified notice period, and set out the obligations on termination including the return of client records and the payment of outstanding fees.

8. **Format the letter and prepare for signature.** Structure the letter with the firm's letterhead details, date, client address, a clear subject line, the body clauses with numbered paragraphs, and a signature block with space for both parties to sign and date. Include a tear-off or separate confirmation slip for the client to return.

## Output Format

The output is a formal letter in structured markdown with the following components:

- **Letterhead Block**: Firm name, address, registration details, professional body membership numbers
- **Addressee Block**: Client name, registered address, FAO contact person
- **Subject Line**: Clearly states the engagement type and period (e.g., "Terms of Engagement for Accounts Preparation and Corporation Tax Services for the year ending 31 March 2026")
- **Body Sections**: Numbered clauses covering scope of services, respective responsibilities, fees and payment, professional obligations, data protection, limitation of liability, termination, and governing law
- **Signature Block**: Space for a partner or principal to sign on behalf of the firm, and for the client to countersign indicating acceptance
- **Confirmation Slip**: A detachable section the client signs and returns to confirm agreement to the terms

## Quality Checks

- The engagement letter must reference the correct professional body guidance (ICAEW Engagement Letter guidance, ACCA Practice Information, or equivalent)
- For audit engagements, the letter must comply with the specific requirements of ISA (UK) 210 including the directors' responsibilities statement
- Fee arrangements must be unambiguous, with no scope for misinterpretation of what is included and what constitutes additional chargeable work
- AML clauses must reference the correct statutory instrument (MLR 2017 as amended) and include the firm's obligation to report suspicious activity without tipping off
- Data protection clauses must reference UK GDPR and DPA 2018, not the pre-Brexit EU GDPR regulation
- Limitation of liability must not attempt to exclude liability for matters that cannot legally be excluded under the Unfair Contract Terms Act 1977 or the Consumer Rights Act 2015 (where applicable)
- The letter must include the firm's complaints procedure and reference the relevant professional body's dispute resolution mechanism

## Limitations

- The generated letter is a draft and must be reviewed by a qualified practitioner before issue to ensure it reflects the specific circumstances of the engagement
- The skill does not provide legal advice on the enforceability of specific contractual terms; firms should seek independent legal review for non-standard clauses
- It does not cover engagement letters for regulated financial advice (investment management, insurance mediation) which require FCA-specific terms and disclosures
- Multi-jurisdictional engagements involving non-UK entities may require additional terms addressing foreign regulatory requirements, double tax treaties, or cross-border data transfers
- The skill does not automatically update when professional body guidance changes; the output should be cross-checked against the latest published templates from ICAEW or ACCA
- It does not generate separate sub-contractor or outsourcing agreements where parts of the engagement are to be delivered by third parties
