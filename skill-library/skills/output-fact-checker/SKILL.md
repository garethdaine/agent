---
name: output-fact-checker
description: |
  Verifies factual claims in agent-generated outputs against source documents and known references. Cross-references stated figures, dates, names, regulatory citations, and statistical claims to identify unsupported assertions, hallucinated data, and misattributed information before outputs reach end users.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [cross-industry]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read, web-search]
  trigger_keywords: [fact check, verify, accuracy, hallucination, source verification, claims]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Output Fact Checker

## Purpose

Validates factual claims present in agent-generated outputs by cross-referencing them against provided source documents, authoritative databases, and publicly available records. The skill systematically identifies numerical discrepancies, incorrect attributions, fabricated citations, and unsupported statistical claims. It produces a structured verification report that flags each claim with a confidence rating and source attribution, enabling human reviewers to focus attention on the highest-risk assertions.

## When to Use

- Before publishing or distributing any agent-generated content that contains specific facts, figures, or citations
- When an agent output references legislation, regulations, or legal precedents that must be accurate
- After generating reports containing financial data, performance metrics, or statistical summaries
- When the output includes dates, named individuals, organisational details, or geographic information that could be verified
- As a final quality gate in any workflow where factual accuracy carries reputational or compliance risk
- When combining information from multiple source documents where cross-referencing errors may occur

## Instructions

1. Receive the agent-generated output text and any source documents or reference materials that were used during generation. Catalogue each source with a unique identifier for traceability.
2. Parse the output to extract all discrete factual claims. Categorise each claim by type: numerical (figures, percentages, dates), attributive (names, titles, organisations), referential (citations, regulation numbers, case references), or statistical (trends, comparisons, aggregates).
3. For each extracted claim, locate the corresponding passage in the source documents. Record whether the claim is directly supported, partially supported, contradicted, or absent from the provided sources.
4. For claims not found in source documents, attempt verification using web search against authoritative sources. Prioritise government databases, official organisational publications, and peer-reviewed materials. Record the verification source URL and retrieval date.
5. Assign a confidence rating to each claim: VERIFIED (exact match with source), PARTIALLY VERIFIED (substance correct but details differ), UNVERIFIED (no supporting source found), or CONTRADICTED (source evidence conflicts with the claim).
6. For any claim rated UNVERIFIED or CONTRADICTED, draft a specific correction note that identifies the discrepancy and provides the correct information where available.
7. Compile the full verification report with a summary table showing total claims checked, verification distribution, and a ranked list of issues by severity.
8. Flag any patterns of systematic error, such as consistent misattribution to a single source or repeated numerical inflation, that may indicate a broader generation issue requiring upstream correction.

## Output Format

The verification report is structured as follows:

- **Summary Header**: Total claims extracted, number verified, partially verified, unverified, and contradicted, with an overall accuracy percentage.
- **Claims Table**: Each row contains the claim text, claim type, source reference, verification status, confidence rating, and correction note if applicable.
- **High-Risk Findings**: A prioritised list of contradicted or unverified claims with detailed explanations and suggested corrections.
- **Source Inventory**: A numbered list of all sources consulted during verification, including document titles, URLs, and access timestamps.
- **Pattern Analysis**: Any identified systematic issues with recommendations for upstream process adjustments.

## Quality Checks

- Every factual claim in the output must be individually assessed; no claims should be skipped or batch-approved without examination
- Numerical values must be checked for unit consistency, decimal placement, and order-of-magnitude accuracy
- Regulatory and legal citations must be verified against official legislative databases, not secondary summaries
- Date claims must be validated for chronological consistency within the document as well as factual correctness
- Attribution claims (who said what, who holds which role) must be verified against the most recent available information
- The verification report itself must not introduce new unsupported claims or speculative corrections

## Limitations

- Cannot verify claims about private or confidential information that is not present in the provided source documents or publicly available records
- Real-time data verification depends on web search availability and may not reflect information published after the last search index update
- Does not assess the quality of reasoning, logical coherence, or opinion-based statements; only factual claims are within scope
- Cannot guarantee detection of sophisticated fabrications that closely mimic plausible facts without obvious source contradictions
- Verification of claims in languages other than English requires source documents in the corresponding language
- Does not perform plagiarism detection; textual similarity to sources is not assessed beyond factual accuracy
