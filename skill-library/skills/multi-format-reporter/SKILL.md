---
name: multi-format-reporter
description: |
  Transforms structured data and analysis results into multiple output formats including executive summaries, detailed technical reports, presentation slide decks outlines, and dashboard-ready data extracts. Adapts content depth, terminology, and visual formatting to target audience requirements.
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
  trigger_keywords: [report, format, executive summary, presentation, dashboard, multi-format, output]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Multi-Format Reporter

## Purpose

Converts analysis results, structured datasets, and raw findings into multiple audience-appropriate output formats from a single source of truth. The skill eliminates the manual effort of reformatting the same information for different stakeholders by producing executive summaries for senior leadership, detailed technical reports for specialist teams, presentation slide deck outlines for meetings, and structured data extracts for dashboards and downstream systems. Each format adapts vocabulary, detail level, and emphasis to match the consumption patterns of its intended audience.

## When to Use

- When analysis results need to be communicated to multiple stakeholder groups with different information needs and technical literacy levels
- After completing a data analysis, investigation, or assessment that requires both a high-level summary and a detailed breakdown
- When preparing materials for a board meeting, steering committee, or client review where both presentation outlines and supporting detail documents are needed
- Before populating dashboards or reporting tools where data must be extracted into a specific structured format (CSV, JSON) from narrative analysis
- When project milestone reports must be produced in both management summary and technical detail variants simultaneously
- After completing an audit, review, or compliance assessment where findings must be reported at different levels of organisational hierarchy

## Instructions

1. Receive the source data or analysis results to be reported. This may include structured data files, narrative analysis text, key findings lists, statistical summaries, or a combination. Identify the core message, key findings, and supporting evidence that form the basis of all output formats.
2. Determine the requested output formats from the following options: Executive Summary, Technical Report, Presentation Outline, Dashboard Extract, or Briefing Note. If no specific formats are requested, produce an Executive Summary and a Technical Report as defaults.
3. For the Executive Summary format: distil the core findings into a single-page narrative (250-400 words). Lead with the primary conclusion or recommendation. Present no more than five key findings, each in one to two sentences. Use business language without technical jargon. Include a clear "recommended next steps" section with owner assignments where applicable. Quantify impact using metrics the target audience tracks (revenue, cost, risk exposure, time savings).
4. For the Technical Report format: produce a comprehensive document with full methodology, detailed findings, data tables, caveats, and appendices. Use section headings consistent with the organisation's reporting standards. Include all supporting evidence, statistical detail, confidence intervals, and data source references. Maintain precise technical terminology appropriate for subject matter experts.
5. For the Presentation Outline format: structure content as a sequence of slides with a title, three to five bullet points per slide, and speaker notes. Follow a narrative arc: context and objectives, methodology summary, key findings (one major finding per slide), implications, and recommended actions. Keep bullet text under 15 words each. Place detailed evidence in an appendix slide section.
6. For the Dashboard Extract format: transform findings into structured data suitable for ingestion by reporting tools. Output as JSON or CSV with clearly labelled fields including: metric name, current value, previous value, change percentage, trend direction, RAG status, and reporting period. Include a schema description header documenting each field.
7. Review all generated formats for internal consistency. Verify that the same figures, dates, and conclusions appear identically across all formats. Ensure that simplification in summary formats does not introduce inaccuracy or misrepresentation of the detailed findings.

## Output Format

Each requested format is produced as a separate clearly labelled section or document:

- **Executive Summary**: A single-page narrative document with sections for Key Message, Findings (numbered list), Impact Assessment, and Recommended Next Steps. Written in plain business English with no unexplained acronyms.
- **Technical Report**: A multi-section document with Table of Contents, Executive Summary, Introduction, Methodology, Findings (with sub-sections per topic), Discussion, Conclusions, Recommendations, Appendices, and References.
- **Presentation Outline**: A sequence of titled slides, each containing bullet points and speaker notes. Includes a title slide, agenda slide, content slides, summary slide, and appendix slides.
- **Dashboard Extract**: A structured data block in JSON or CSV format with a preceding schema definition. Each record represents a single metric or finding with standardised fields for automated ingestion.
- **Briefing Note**: A one-to-two-page document structured as Situation, Background, Assessment, and Recommendation (SBAR format) for rapid decision-maker consumption.

## Quality Checks

- All numerical values must be identical across every output format; no rounding or approximation differences between the executive summary and the technical report
- The executive summary must be genuinely concise and must not exceed one page when rendered in standard 11-point body text
- Presentation outline bullet points must each convey a single idea and must not exceed 15 words
- Dashboard extract data must be valid JSON or well-formed CSV that parses without errors in standard tools
- Technical terminology used in the technical report must be replaced with accessible language in the executive summary without changing the meaning
- Every recommendation or next step mentioned in summary formats must be traceable to supporting evidence in the technical report

## Limitations

- Does not produce finished visual assets such as charts, graphs, or formatted slide files; presentation output is a text-based outline that requires assembly in presentation software
- Cannot access live data sources or databases to refresh figures; all reporting is based on the data provided at the time of invocation
- Dashboard extract schemas are generic and may require mapping adjustments to align with specific business intelligence tool field requirements
- Does not apply organisational branding, logos, or proprietary formatting templates; outputs use structural formatting only
- The skill adapts language complexity but does not translate content between natural languages; all outputs are produced in the same language as the input
- Maximum effective input size is approximately 50,000 words of source material; beyond this, content prioritisation may result in incomplete coverage in summary formats
