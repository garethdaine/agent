---
name: security-audit-scanner
description: |
  Performs comprehensive security audits on codebase outputs including
  vulnerability scanning, dependency analysis, and compliance verification
  for enterprise security requirements.
version: "2.1.0"
author: "securityops"
license: "MIT"
x-agent:
  industries: [technology, financial-services]
  risk_level: elevated
  requires_approval: true
  memory_blocks: [security-policies]
  mcp_dependencies: [database, filesystem]
  tools: [file-read, web-search, code-analysis]
  trigger_keywords: [security, audit, vulnerability, compliance]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Security Audit Scanner

This skill performs comprehensive security audits on agent-generated code.

## Features

- Static analysis for common vulnerabilities
- Dependency version checking
- OWASP Top 10 compliance verification
- License compatibility analysis

## Configuration

Configure security policies via the `security-policies` memory block.
