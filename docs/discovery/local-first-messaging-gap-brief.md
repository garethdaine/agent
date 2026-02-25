# Messenger Control Plane — Local-First Messaging Gap Brief

## 1. Purpose

Document what is missing to deliver a true local-first messenger integration experience (OpenClaw-like behavior) in this codebase.

This brief is scoped to Messenger Control Plane only. It excludes the Org Layer feature.

## 2. Required Local-First Outcome

The expected outcome is:

1. Users can connect supported messenger channels without requiring a permanently public webhook endpoint where provider protocols allow outbound gateway connections.
2. Webhook fallback exists for providers or modes that require inbound HTTP.
3. Mode selection is real at runtime, not only a configuration label.

## 3. External Baseline (OpenClaw Behavior)

Current OpenClaw channel docs indicate:

1. Slack defaults to Socket Mode, with HTTP mode optional.
2. Telegram defaults to long polling, with webhook mode optional.
3. Discord runs via official gateway model.
4. WhatsApp channel is implemented via WhatsApp Web (Baileys), not Cloud API webhooks.

Implication: OpenClaw achieves local-first primarily through gateway-owned outbound sessions, not mandatory public webhooks.

## 4. Planned vs Implemented (This Repository)

Planned in discovery and plan documents:

1. Local connector mode was defined as default where supported.
2. Public webhook mode was defined as a secondary ingress profile.
3. Slack/Telegram were Phase A; Discord/WhatsApp were later phases.

Implemented in runtime:

1. Connector schema currently supports webhook mode only for all providers.
2. Adapters implemented are Slack and Telegram webhook handlers plus outbound API send.
3. No local connector lifecycle exists (no gateway socket/long-poll workers).
4. Discord and WhatsApp endpoints are present but controller logic is stubbed.

Conclusion: current implementation is webhook-centric with partial provider support, not local-first runtime behavior.

## 5. Missing Capabilities

### 5.1 Local Connector Runtime (Critical)

Missing:

1. A persistent messenger gateway worker process model for long-polling/WebSocket channels.
2. Provider lifecycle interfaces and orchestration (`start`, `stop`, `health`, reconnect, backoff).
3. Runtime state persistence for connector sessions (connected, reconnecting, failed, last heartbeat).
4. Supervisor/process-manager integration for per-connector long-running workers.

Impact:

Local mode cannot function as designed even if selected by installer/UI.

### 5.2 Mode Fidelity (Critical)

Missing:

1. Mode-aware provider schema reflecting actual supported modes.
2. Mode-dependent credential requirements and validation.
3. Mode-dependent install/test flows.

Impact:

The product advertises local mode semantics that runtime cannot execute.

### 5.3 Ingress Strategy for Webhook Mode (High)

Missing:

1. First-class ingress options and validation flow (reverse proxy or tunnel profile).
2. Provider callback readiness checks and diagnostics (TLS, reachable URL, challenge/verification).
3. Operational guidance encoded into install health checks, not only docs.

Impact:

Webhook mode remains fragile and user-dependent for networking details.

### 5.4 Provider Parity Gaps (High)

Missing:

1. Production Discord adapter and event processing.
2. Production WhatsApp adapter strategy aligned to product intent.
3. Clear product decision on WhatsApp protocol: Cloud API webhook model vs WhatsApp Web session model.

Impact:

The promised multi-channel surface is incomplete and architecture is undecided for WhatsApp relative to OpenClaw baseline.

### 5.5 Control Plane Completeness (Medium)

Missing:

1. Full action execution integration with real job/run operations across all action handlers.
2. Reliable status propagation for connector and action states across UI, queues, and audit events.
3. Queue/health metric consistency (queue names and backlog telemetry alignment).

Impact:

Users can connect and send messages in limited paths, but operational trust is reduced.

## 6. Root Cause Summary

The primary failure is not lack of discovery content. Discovery captured local-first intent.

The failure was execution gating:

1. No hard acceptance gate required a real local connector runtime before marking feature complete.
2. Installer/UI semantics were allowed to ship ahead of runtime capability.
3. Provider roadmap remained partially implemented without feature-state enforcement in UX.

## 7. Required Decisions Before Implementation

1. WhatsApp architecture decision:
   1. Option A: Cloud API webhook-first.
   2. Option B: WhatsApp Web session (OpenClaw-like).
2. Local-first definition of done:
   1. Which providers must support no-public-URL operation in Phase 1.
3. Webhook ingress productization:
   1. Built-in tunnel support vs documented reverse-proxy-only support.

## 8. Remediation Workstreams

### W1: Gateway Runtime

Build a `MessengerGatewayManager` with provider workers:

1. Slack socket worker.
2. Telegram polling worker.
3. Discord gateway worker (once provider implemented).
4. Standard lifecycle controls and heartbeats.

### W2: Mode-Aware Contracts and UX

1. Update provider schema to truthful supported modes.
2. Enforce mode-specific credentials and tests.
3. Prevent selecting unavailable modes.

### W3: Webhook Ingress Productization

1. Add ingress profiles in install flow.
2. Add callback validation probes and actionable diagnostics.
3. Add provider-specific webhook registration helpers where applicable.

### W4: Provider Completion

1. Complete Discord adapter path.
2. Implement chosen WhatsApp strategy end-to-end.
3. Remove or explicitly gate incomplete providers in UI until production-ready.

### W5: Reliability and Observability Hardening

1. Align queue health metrics with actual queue names.
2. Ensure connector/action state transitions are emitted and surfaced.
3. Add end-to-end tests per mode and provider.

## 9. Acceptance Criteria for Local-First Readiness

Feature is local-first ready only when all are true:

1. Slack runs in socket mode without public webhook URL.
2. Telegram runs in long-poll mode without public webhook URL.
3. Selected webhook mode includes validated ingress with passing provider callback checks.
4. Mode shown in UI equals active runtime mode.
5. Connector health reflects real worker state (not static config status).
6. End-to-end tests prove create/list/control run flows per supported provider/mode.

## 10. Immediate Next Step

Treat Messenger Control Plane as partially delivered and reopen it with an explicit Local-First Runtime milestone before any further feature-complete claim.
