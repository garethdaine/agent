---
name: financial-compliance-check
description: |
  Validates agent outputs against financial compliance requirements including
  regulatory standards, audit trail verification, and risk assessment protocols
  for financial services organizations.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [financial-services]
  risk_level: elevated
  requires_approval: false
  memory_blocks: [compliance-rules]
  mcp_dependencies: [database]
  tools: [file-read, web-search]
  trigger_keywords: [compliance, financial, audit, regulatory]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Financial Compliance Check

This skill validates agent outputs against financial compliance requirements.

## Usage

The skill will automatically check outputs for:
- Regulatory compliance violations
- Missing audit trail entries
- Risk assessment gaps

## Configuration

Configure compliance rules via the `compliance-rules` memory block.
