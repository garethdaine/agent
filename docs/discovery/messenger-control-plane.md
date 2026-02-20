# Requirements Discovery — Messenger Control Plane

## 1. Overview

**Feature Name:** Messenger Control Plane  
**Purpose:** Let users control the local Agent installation through chat in supported messenger platforms.  
**Current Scope Decision:** Messenger integrations only (native iOS/Android app deferred).  

The feature provides a unified AI chat interface that can:
1. Create/update agent cron jobs from natural language.
2. Show active jobs/runs and control in-flight runs.
3. Start new agent runs/tasks on demand.

This must work in local-first deployments where the agent runs on user infrastructure, with secure remote access for messenger providers.

---

## 2. Goals

1. **Single chat control surface** across Slack, Discord, Telegram, WhatsApp.
2. **Safe execution**: AI suggestions are converted into validated actions, never directly executed raw.
3. **Operational simplicity**: setup handled by `agent:install`.
4. **Reliable lifecycle management**: `agent:restart` gracefully restarts local runtime services.
5. **No cloud dependency for core orchestration**: control plane runs on the same machine/network as this repo.

---

## 3. Non-Goals (Current Phase)

1. Native iOS/Android client.
2. Multi-tenant hosted SaaS control plane.
3. Arbitrary shell execution from chat.
4. Replacing existing scheduler/runner internals.

---

## 4. Core User Flows

1. **Create Job via Chat**
- User sends: "Run prompt X every weekday at 9am".
- System parses schedule and job payload.
- System validates against existing policies and allowed paths.
- System creates job and returns summary + next run preview.

2. **Observe + Control Active Runs**
- User asks: "What is running now?"
- System returns active runs with status and excerpts.
- User sends a steering/control command (pause/stop/retry/continue guidance).
- System applies allowed action and returns result.

3. **Run Now / Spawn Task**
- User sends: "Run job 42 now" or "Start a codex run for task Y".
- System validates ownership, limits, and policy constraints.
- System dispatches run and streams updates back to the originating channel.

---

## 5. Functional Requirements

### 5.1 Channel Connectors

Implement connector adapters for:
1. Slack
2. Discord
3. Telegram
4. WhatsApp

Each adapter must:
1. Verify provider signatures/tokens.
2. Normalize inbound events into a common internal message contract.
3. Support async responses (ack fast, process on queue).
4. Map channel user identity to local Agent user identity.

### 5.2 Chat Orchestration

1. Add chat session + message persistence for traceability.
2. Use structured AI action output (JSON schema), not free-form command execution.
3. Support action types:
- `jobs.create`
- `jobs.update`
- `jobs.list`
- `runs.list_active`
- `runs.stop`
- `runs.run_now`
- `runs.steer`
4. Enforce explicit confirmation for destructive/high-impact actions.

### 5.3 Steering Semantics (MVP)

Given current one-shot run model, MVP steering is:
1. Stop + restart with appended clarification context, or
2. Queue a follow-up run linked to prior run.

True live stdin/PTY interactive steering is out-of-scope for MVP and treated as a later enhancement.

### 5.4 API Surface

Add versioned endpoints under existing API structure for:
1. Connector webhooks.
2. Chat session/message retrieval.
3. Chat action execution status.
4. Optional stream/poll endpoint for run updates in chat context.

All mutations must reuse existing validation/authorization/audit pathways.

---

## 6. Security Requirements

1. **AuthN/AuthZ**
- Messenger identity must map to an authorized local user.
- Per-action authorization checks are mandatory.

2. **Input Safety**
- No raw command templates accepted directly from chat.
- All generated payloads pass `CommandPolicy`, `PathPolicy`, `EnvPolicy` equivalents.

3. **Webhook Security**
- Signature verification required for all webhook providers.
- Replay protection via timestamp/nonce/idempotency windows.

4. **Least Privilege**
- Scoped capabilities for connector actions (read-only vs mutation).
- Optional approval gate for privileged actions.

5. **Auditability**
- Every chat-triggered mutation must emit audit records with actor/channel/context.

---

## 7. Connectivity and Exposure Requirements

Two supported deployment modes:

1. **Public Webhook Mode**
- Expose `https://<host>/agent/api/v1/connectors/*` through TLS reverse proxy.
- Required for providers that need public webhook callbacks.

2. **Local Connector Mode (Reduced Exposure)**
- Use outbound/socket/polling where provider supports it.
- Avoid inbound public exposure when feasible.

Both modes must be installable via `agent:install` flags/options.

---

## 8. Install and Runtime Management

### 8.1 `agent:install`

Top-level installer/orchestrator command. Must:
1. Perform preflight checks (PHP/Node/Redis/DB/network/DNS/TLS prerequisites).
2. Configure selected connector providers.
3. Configure ingress profile (public webhook mode vs local connector mode).
4. Create/update local runtime scripts/config for services.
5. Run health checks and print actionable status.

### 8.2 `agent:restart`

Operational command to gracefully restart local runtime stack. Must:
1. Gracefully terminate and restart:
- `php artisan horizon`
- `php artisan reverb:start`
- `php artisan schedule:work`
- `php artisan serve` (or configured web runtime)
- `npm run dev` (when enabled in local-dev mode)
2. Avoid killing unrelated system processes.
3. Report per-service restart success/failure.
4. Preserve/log restart events for diagnostics.

---

## 9. Data Model Additions (MVP)

1. `chat_sessions`
- user/channel/provider/thread mapping
- status + timestamps

2. `chat_messages`
- direction (inbound/outbound)
- normalized payload
- provider event IDs (idempotency)

3. `chat_actions`
- parsed intent/action type
- structured parameters
- execution status/result/error

4. `connector_accounts` (or equivalent)
- provider config references
- verification metadata
- connection status

---

## 10. Observability and Reliability

1. Structured logs with correlation IDs across webhook -> chat action -> run/job mutation.
2. Queue-backed processing for all provider callbacks.
3. Retries with dead-letter handling for transient connector failures.
4. Metrics:
- inbound message rate
- action success/failure rate
- median/95p action latency
- webhook verification failures

---

## 11. UX and Product Behavior

1. Responses should be concise, actionable, and include run/job IDs.
2. For ambiguous intent, ask a clarification question instead of guessing.
3. For denied actions, explain policy reason and suggested correction.
4. For long operations, send progress updates and terminal result.

---

## 12. Acceptance Criteria

1. User can link at least one messenger provider and issue commands.
2. User can create a cron job from natural-language chat, with policy validation.
3. User can list active runs and stop/retry/run-now through chat.
4. Every mutation is audited and attributable to chat identity + mapped user.
5. `agent:install` can bootstrap required runtime and connector configuration.
6. `agent:restart` gracefully restarts all required local runtime services.
7. System remains operational if one connector provider is degraded.

---

## 13. Delivery Phasing

1. **Phase A (MVP Core):**
- Chat action schema + orchestration
- Slack + Telegram integration
- `agent:install` baseline
- `agent:restart` baseline

2. **Phase B (Connector Expansion):**
- Discord + WhatsApp adapters
- hardened webhook verification and replay controls

3. **Phase C (Enhanced Steering):**
- richer steering semantics
- optional interactive runtime protocol (if runner architecture is extended)

---

## 14. Open Questions

1. Which messenger provider should be first-class in MVP (Slack vs Telegram)?
2. Should privileged actions require explicit "confirm" for every connector, or configurable per provider?
3. In production installs, should `php artisan serve` be excluded from `agent:restart` in favor of managed web server only?
4. Do we require per-channel RBAC (e.g., only certain Slack channels can issue mutations)?
5. What is the default connector mode in install (`local connector` vs `public webhook`)?

---

## 15. Dependencies and Constraints

1. Existing queue/horizon/reverb runtime remains the execution backbone.
2. Existing job/run authorization and policy validation remain source of truth.
3. Messenger provider platform rules (ack timing, signature checks, webhook/polling limits) must be respected per adapter.
