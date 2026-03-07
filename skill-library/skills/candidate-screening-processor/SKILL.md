---
name: candidate-screening-processor
description: |
  Processes candidate applications for recruitment agencies and in-house hiring teams. Screens CVs against job specifications, extracts qualification and experience data, scores candidates against role requirements, and produces shortlist recommendations with justification narratives.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [recruitment, professional-services]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [candidate, screening, CV, recruitment, shortlist, hiring, job specification]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Candidate Screening Processor

## Purpose

Automates the initial screening of candidate applications against defined job specifications, reducing time-to-shortlist for recruitment consultants and in-house talent teams. The skill extracts structured data from CVs and covering letters, scores each candidate against weighted role criteria, and produces a ranked shortlist with clear justification narratives suitable for client presentation or hiring manager review.

## When to Use

- A batch of candidate CVs has been received against an open vacancy and needs initial screening
- A recruitment consultant needs to produce a shortlist report for a client within a tight turnaround
- An in-house hiring team wants consistent, auditable screening against defined person specification criteria
- Multiple roles share overlapping requirements and candidates need cross-matching to the best-fit vacancy
- A high-volume campaign has generated more applications than can be manually reviewed within the available timeframe

## Instructions

1. Ingest the job specification document and extract all essential and desirable criteria, including qualifications, years of experience, sector exposure, technical skills, and location or mobility requirements. Classify each criterion as essential or desirable and assign the weighting provided in the specification, or apply equal weighting if none is stated.

2. For each candidate CV, extract the following structured fields: full name, contact details, current job title, current employer, total years of relevant experience, listed qualifications with awarding bodies and dates, key technical skills, sector experience, notice period or availability, and stated salary expectation where provided.

3. Score each candidate against every criterion from the job specification. Award full marks for essential criteria that are clearly evidenced, partial marks where experience is adjacent or transferable, and zero where no evidence is found. Apply the same approach to desirable criteria at half the weighting.

4. Flag any candidates who fail to meet one or more essential criteria, clearly noting which criteria are unmet. These candidates should be placed in a separate "not progressed" group with a brief explanation for each exclusion.

5. Rank the remaining candidates by total weighted score and produce a shortlist of the top candidates, defaulting to the top five unless a different number is specified. For each shortlisted candidate, write a two-to-three sentence justification narrative covering their strongest matching points and any notable gaps.

6. Generate a summary comparison table showing all shortlisted candidates side by side against each criterion, using a clear pass, partial, or gap indicator for each.

7. Produce a "not progressed" appendix listing excluded candidates with the specific unmet essential criteria, suitable for record-keeping and candidate feedback purposes.

## Output Format

The output should be a structured screening report containing:

- **Header**: Job title, reference number, screening date, total applications received
- **Shortlist Table**: Ranked candidates with columns for name, current role, total score, and a one-line summary
- **Candidate Profiles**: Individual sections for each shortlisted candidate with extracted data, scoring breakdown, and justification narrative
- **Comparison Matrix**: Side-by-side grid of all shortlisted candidates against each criterion
- **Not Progressed Appendix**: List of excluded candidates with unmet essential criteria noted

All scores should be expressed as a percentage of the maximum available marks, with the raw score shown in brackets.

## Quality Checks

- Every essential criterion from the job specification has been evaluated for every candidate
- No candidate has been shortlisted while failing an essential criterion unless explicitly instructed to treat that criterion as flexible
- Justification narratives reference specific evidence from the CV rather than generic statements
- Salary expectations, where stated, have been compared against the role budget and any mismatches are flagged
- Notice periods or availability dates have been extracted and noted where relevant to start date requirements
- The comparison matrix is consistent with the individual scoring breakdowns

## Limitations

- Cannot verify the accuracy of claims made in candidate CVs; all scoring is based on information as stated by the candidate
- Does not perform reference checks, background screening, or right-to-work verification
- Salary benchmarking is limited to the budget range provided in the job specification and does not account for current market rates unless supplied
- Cannot assess soft skills, cultural fit, or interpersonal qualities from written applications alone
- Does not handle video or audio application formats; text-based CVs and covering letters only
- Screening output is a recommendation tool and should not replace human judgement at the interview selection stage
