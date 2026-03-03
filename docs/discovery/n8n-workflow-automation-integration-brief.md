# n8n Workflow Automation Integration Brief for Agent

## Metadata
- Status: Draft
- Author: Codex
- Date: 2026-03-03

## Executive Summary
Integrating n8n into Agent adds a visual, event-driven orchestration layer on top of Agent’s existing local-first scheduler and run engine. The best near-term fit is a two-way integration: Agent emits lifecycle events to n8n webhooks, and n8n calls scoped Agent APIs for run controls and operational workflows.

This gives Agent a fast path to high-value automations (incident response, approvals, notifications, ticketing, and cross-system sync) without bloating Agent’s core runtime with third-party workflow logic.

## Why This Fits Agent
Agent already has the right primitives to support orchestration:
- Job and run control APIs (`/agent/api/v1/jobs`, `/agent/api/v1/runs`, run actions like `stop`/`retry`).
- Run event stream surfaces and lifecycle states.
- Token-based integration patterns and security guidance already documented.
- Existing queue-based background architecture that pairs well with asynchronous workflow engines.

n8n complements this by adding:
- A visual workflow canvas for non-core orchestration logic.
- Large integration surface (SaaS/API connectors + webhook-first automation).
- Human-in-the-loop and wait/resume patterns useful for approvals and clarifications.
- Scalable execution patterns (queue mode with Redis and workers) for high workflow throughput.

## Proposed Feature: Agent Automations (Powered by n8n)
Build an integration feature set in Agent called `Automations`, with n8n as the first orchestrator backend.

### Core Capability
1. Outbound: Agent -> n8n
- Agent sends signed webhook events to configured n8n workflows on key lifecycle triggers:
  - `agent.run.started`
  - `agent.run.failed`
  - `agent.run.succeeded`
  - `agent.run.blocked` (approval/clarification/rate-limit)

2. Inbound: n8n -> Agent
- n8n invokes scoped Agent APIs to:
  - run a job now
  - retry a failed run
  - stop a running job
  - fetch run status/events for branching logic

### UX Surface in Agent
- `Settings > Automations > n8n`
  - n8n endpoint URL
  - signing secret
  - optional API key / auth profile
  - per-event enable/disable toggles
- `Automation Templates`
  - prebuilt recipes (Slack alert on fail, Linear incident on repeated fail, digest reports)
- `Automation Runs`
  - per-event delivery logs, retry status, last response, and failure diagnostics

## Benefits to Agent
1. Faster extensibility
- New automation behavior can be shipped as workflows, not backend releases.

2. Lower product complexity in core runtime
- Agent stays focused on execution/scheduling; n8n handles orchestration glue.

3. Better operational response
- Immediate cross-system actions on run failures or blocker states.

4. Broader team adoption
- Engineers and operators can co-own automation behavior via visual workflows.

5. Higher integration leverage
- Agent gains access to n8n’s connector ecosystem instead of building/maintaining one-off connectors.

## MVP Scope (Recommended)
### In Scope
- Outbound signed webhook delivery from Agent to n8n.
- Basic inbound action endpoints for n8n-initiated run control.
- Delivery logs + retry/backoff for failed webhook deliveries.
- One-click starter templates (3-5 high-value automations).

### Out of Scope (MVP)
- Full embedded n8n UI inside Agent.
- Custom node authoring framework.
- Multi-orchestrator abstraction layer.

## Technical Design Snapshot
### Data Flow
1. Agent run lifecycle event is created.
2. Event dispatcher enqueues outbound automation delivery job.
3. Job posts signed payload to configured n8n webhook URL.
4. n8n workflow branches by event type/severity and performs actions.
5. Optional n8n callback calls Agent action endpoint.
6. Agent writes audit + delivery/result telemetry.

### Security
- HMAC signature on outbound webhooks (timestamp + body digest).
- Short replay window + nonce for inbound callbacks where practical.
- Dedicated scoped API token for n8n integration user.
- Secret storage encrypted at rest in Agent settings.
- Outbound host allowlist and strict TLS validation.

### Reliability
- Idempotency key per outbound event delivery.
- Retry policy with capped exponential backoff.
- Dead-letter capture for permanently failed deliveries.
- Circuit-breaker behavior for repeated endpoint failures.

## Expansion Roadmap
### Phase 1: Event Bridge
- Outbound lifecycle webhooks + inbound run control.
- Templates: failure alerting, issue creation, daily summary.

### Phase 2: Guided Automation Builder
- In-Agent setup wizard for n8n connection and template installs.
- Mapping UI for event payload fields and action policies.
- Approval workflows via n8n wait/resume steps.

### Phase 3: Advanced Orchestration
- Bi-directional sync with richer state machines (incidents, escalations).
- Governance features (RBAC-scoped automation ownership, policy checks).
- Optional deeper support for n8n MCP-based patterns for AI-oriented flows.

## Key Risks and Mitigations
- Risk: event storms from noisy run transitions.
  - Mitigation: per-event rate limits, dedupe window, severity thresholds.
- Risk: external workflow failures create silent automation gaps.
  - Mitigation: delivery health dashboard + alerts + dead-letter replays.
- Risk: over-permissive API tokens.
  - Mitigation: scoped integration tokens, rotation policy, audit trails.
- Risk: enterprise-only n8n features assumed in community deployments.
  - Mitigation: capability detection and feature gating in Agent UI.

## Success Criteria
- 80%+ of common operational automations implemented via templates (no backend change).
- < 2 minutes median time from run failure to external alert/ticket action.
- < 1% webhook delivery failure after retries in steady state.
- Positive operator feedback on setup effort and observability.

## Sources
- n8n docs: [Webhook node](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.webhook/), [API authentication](https://docs.n8n.io/api/authentication/), [Queue mode](https://docs.n8n.io/hosting/scaling/queue-mode/), [Wait node](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.wait/), [n8n MCP server](https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-mcpclienttool/#accessing-n8ns-mcp-server), [Community Edition feature matrix](https://docs.n8n.io/manage-cloud/concurrency-queue-mode/)
- n8n site: [n8n product overview](https://n8n.io/), [AI agents page](https://n8n.io/ai-agents/)
