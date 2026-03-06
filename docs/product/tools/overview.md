---
slug: tools-overview
title: Tools Overview
summary: Runtime tools available to agent jobs during execution, including file operations, HTTP requests, and shell commands.
section: tools
audience: operator
status: published
version: "1.0.0"
tags:
  - tools
  - agent
  - runtime
owner: docs-team
route_names:
  - tools.index
setting_keys:
  - agent.tools_enabled
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-06
---
# Tools Overview

The Tools page provides visibility into the runtime tools available to agent jobs during execution. Tools extend agent capabilities beyond basic prompt processing to include file operations, HTTP requests, shell commands, and other system interactions.

## Interface Coverage

- **Tool listing**: all registered tools with their status, category, and configuration.
- **Enablement toggle**: global `agent.tools_enabled` setting gates tool availability.
- **Category grouping**: tools organized by capability domain (file, network, shell, etc.).

## Settings

`agent.tools_enabled` controls whether agents can invoke runtime tools during job execution.

| Setting | Purpose | Typical Value |
| --- | --- | --- |
| `agent.tools_enabled` | Master toggle for tool availability | `true` |

## Configuration Notes

- Tools are only available when `agent.tools_enabled` is true.
- Individual tools may have additional configuration via the agent config.
- Tool invocations are logged in the run output for auditability.

## User Flows

1. Navigate to Tools page.
2. Review available tools and their statuses.
3. Verify tool configuration matches intended agent capabilities.

## Troubleshooting

- If tools are unavailable during agent runs, confirm `agent.tools_enabled` is set to `true`.
- Check agent config for tool-specific restrictions or allowed path settings.

## Related Docs

- [Jobs Overview](/docs/jobs-overview)
- [Dashboard Overview](/docs/dashboard-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.index` | ok | `tools` | `GET` |

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `agent.tools_enabled` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
