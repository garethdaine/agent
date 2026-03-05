# OpenClaw Parity — Master Gap List

**Purpose:** Single prioritised list of gaps from OpenClaw parity analysis. As each item is implemented, mark it complete and **note the UI surface** so everything is visible in the dashboard and messenger.

**Plan:** [openclaw-full-parity-analysis-plan.md](openclaw-full-parity-analysis-plan.md)  
**Discovery docs:** [openclaw-security-parity.md](../discovery/openclaw-security-parity.md) (and others as phases complete)

---

## Priority Legend

- **P0:** Security / data safety / governance — must have for enterprise.
- **P1:** Ease of use / quality UX — high impact for adoption and daily use.
- **P2:** Parity / completeness — important but not blocking.
- **P3:** Nice-to-have / optional (e.g. extra channels, plugins).

---

## Phase A: Security & Governance

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| A1 | Security audit command + dashboard “Run audit” | P0 | Done | **UI:** Settings → Security; CLI: `php artisan agent:security-audit` |
| A2 | Tool deny list (and optional allow list) in config | P0 | Done | **UI:** Settings → Runtime; config: `runtime.tool_deny`, `runtime.tool_allow`; env: `RUNTIME_TOOL_DENY`, `RUNTIME_TOOL_ALLOW`; API: `GET /agent/api/v1/runtime/policy` |
| A3 | DM policy (pairing / allowlist / open / disabled) per connector | P0 | Done | **Model:** `ConnectorAccount::getDmPolicy()`/`setDmPolicy()`; **API:** `GET/PUT /agent/api/v1/messenger/connectors/{id}/policy`; stored in `config.dm_policy` |
| A4 | Pairing approve flow (pending list + approve/revoke) | P0 | Done | **UI:** Messenger → Pairings (approve/revoke); **CLI:** `php artisan messenger:pairing list|approve|revoke`; **API:** `GET /agent/api/v1/messenger/pairings`, `POST .../approve`, `POST .../revoke`; **Model:** `MessengerIdentityLink.status` (pending/approved/revoked) |
| A5 | Every runtime tool call + approval in audit log | P0 | Done | **UI:** Settings → Audit Log (filterable by runtime.tool_call, runtime.approval, etc.); **API:** `GET /agent/api/v1/audit-log?action_prefix=runtime.tool_call` |
| A6 | Gateway token for CLI/remote (create/revoke) | P1 | Done | **UI:** Jetstream API Tokens page (Profile → API Tokens); **Permissions:** `runtime:read`, `runtime:execute`, `jobs:read`, `jobs:manage`, `messenger:read`, `messenger:manage`; Sanctum token auth on all API routes |
| A7 | Group policy (allowlist + require @mention) | P1 | Done | **Model:** `ConnectorAccount::getGroupPolicy()`/`setGroupPolicy()`; **API:** `GET/PUT /agent/api/v1/messenger/connectors/{id}/policy`; stored in `config.group_policy` |
| A8 | DM session scope (main vs per-channel-peer) | P1 | Done | **Model:** `ConnectorAccount::getDmSessionScope()`/`setDmSessionScope()`; **API:** `PUT /agent/api/v1/messenger/connectors/{id}/policy` with `dm_session_scope=main|per_peer`; stored in `config.dm_session_scope` |
| A9 | “Allow once” vs “Allow always” in approval UX | P1 | Done | **UI:** Runtime → Session → “Allow once” / “Allow always” / “Deny” buttons; **Backend:** ApprovalGate::approve(allowAlways) stores tool in RuntimeSession.tool_auto_approvals JSON; requiresApproval() checks auto-approval list; API: POST .../approve with allow_always=true |
| A10 | Credential storage map (docs) + optional Secrets page | P2 | Done | **Docs:** `docs/plans/credential-storage-map.md` (vault, env, connectors, API tokens, security controls). **UI:** Settings → Secrets with per-provider credential listing (set/not-set badges), inline add/replace, delete, eye toggle for input. Sidebar link added |
| A11 | Config form for runtime/messenger (schema-driven) | P2 | Done | **Service:** `ConfigSchemaService` returns typed schema (select/number/boolean/url/tags fields with constraints). **API:** `PUT /configuration` validates against schema, writes to `.env`, clears config cache. **UI:** Settings → Configuration now has Edit/View toggle; edit mode renders form from schema per section |
| A12 | Redact sensitive in logs (config + toggle) | P2 | Done | **Config:** `logging.redact_sensitive` / `LOG_REDACT_SENSITIVE=true`; **Processor:** `RedactSensitiveProcessor` via `RedactSensitiveTap` on single/daily channels; security audit warns when off |

---

## Phase B: Ease of Use & Onboarding

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| B1 | First-run / onboarding flow (connect first channel in few steps) | P1 | Done | **UI:** Onboarding Welcome checklist (connect channel, create job, diagnostics, security audit); controller passes readiness flags |
| B2 | Doctor/diagnostics command + dashboard diagnostics | P1 | Done | **UI:** Settings → Diagnostics; API: `GET /agent/api/v1/diagnostics`; CLI: `php artisan agent:doctor` |
| B3 | Configuration UX: one place to edit (runtime, messenger) | P1 | Done | **UI:** Settings → Configuration (read-only dashboard showing runtime, messenger, security, and agent config); **API:** `GET /agent/api/v1/configuration`; sidebar link added under Settings |
| B4 | Troubleshooting runbook (docs) | P2 | Done | **Docs:** `docs/plans/troubleshooting-runbook.md` — covers queue, messenger, runtime, database, memory, and performance issues with check commands, cause/fix tables, health check matrix, and log locations |

---

## Phase C: Dashboard & Control UI

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| C1 | Runtime sessions list + detail (existing; verify completeness) | P1 | Done | Messenger → Runtime (index + show) |
| C2 | Pending approvals in session detail + approve/deny actions | P1 | Done | **UI:** Runtime → Session → Pending Approvals with "Allow once" / "Allow always" / "Deny"; auto-approved tools displayed as badges in session info |
| C3 | Channels/connectors status + config in dashboard | P1 | Done | **UI:** Tools → Messenger → Connectors table now shows Policy column (DM policy, session scope, @mention badge); per-connector policy loaded via API |
| C4 | Config view/edit (runtime, messenger) with validation | P2 | Done | Covered by A11 -- same `ConfigSchemaService` + `PUT /configuration` endpoint with server-side validation. UI includes inline validation constraints (min/max/options) |
| C5 | Debug: status, health, models, event log (read-only) | P2 | Done | **API:** `GET /agent/api/v1/debug` (health, sessions, jobs, queue, env, recent events). **UI:** Tools → Diagnostics upgraded to full Debug Panel with status tiles, health checks table, environment info, queue depths, and recent audit events with auto-refresh |
| C6 | Logs: live tail with filter/export | P2 | Done | **API:** `GET /agent/api/v1/logs` (channel, level, search params) + `GET /agent/api/v1/logs/export`. **UI:** Tools → Logs with channel selector (laravel/messenger/runtime/docs), level filter, search, live tail polling (5s), and export download. Sidebar link added |

---

## Phase D: Messenger & Slash Commands

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| D1 | Slash command parity table (see discovery when written) | P1 | Done | **Commands:** 15 slash commands implemented (`/jobs`, `/runs`, `/status`, `/sessions`, `/mode`, `/approve`, `/deny`, `/browser`, `/ask`, `/context`, `/new`, `/help`, `/commands`, `/whoami`, `/compact`); `/commands` returns full list in-chat |
| D2 | Channel routing + group rules (allowlist, mention) | P1 | Done | **Service:** `ChannelPolicyGuard` evaluates DM + group policy per connector before message processing; DM: checks pairing status; Group: enforces `require_mention` and `allowed_groups` allowlist; returns `ChannelPolicyResult` (allowed/denied/ignored) |
| D3 | Streaming/chunking behaviour per connector | P2 | Done | **DTO:** `StreamingConfig::withOverrides()` + `toArray()` for merging per-connector overrides. `StreamingConfig::whatsapp()` factory added. **Model:** `ConnectorAccount::getStreamingOverrides()`/`setStreamingOverrides()`. **API:** `PUT /connectors/{id}/policy` now accepts `streaming.max_message_chars`, `streaming.throttle_ms`, `streaming.min_initial_chars` with validation. Policy response includes `streaming` key |

---

## Phase E: Sessions, Memory, Agent Loop

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| E1 | Session key design + pruning/retention (docs) | P2 | Done | **Docs:** `docs/plans/session-key-design.md` — key format, DM scope, lifecycle states, compaction, hard deletion, retention config, pruning schedule |
| E2 | sessions_* tools (list/history/send) | P2 | Done | **API:** `GET /chat-sessions` (list with pagination, status/connector filter), `GET /chat-sessions/{id}` (show), `GET /chat-sessions/{id}/history` (message history), `POST /chat-sessions/{id}/send` (send outbound message), `POST /chat-sessions/{id}/archive`. **UI:** Messenger → Chat History page with expandable sessions, message bubbles, send input, archive button. Sidebar link added |
| E3 | Memory in chat vs our pipeline (docs) | P2 | Done | **Docs:** `docs/plans/memory-pipeline-vs-chat-context.md` — comparison table, chat context flow, memory pipeline stages, how they combine at prompt-build time, diagnostic commands, UI surfaces |

---

## Phase F: Automation

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| F1 | Cron delivery (announce, webhook) vs our scheduler | P2 | Done | **Event:** `AgentJobRunFinished` dispatched at end of `finalizeTerminal()`. **Listener:** `DeliverRunWebhook` checks config, dispatches `DeliverWebhookJob` with run payload. **Config:** `agent.webhooks.enabled/url/secret/events` in `.env` |
| F2 | Webhook auth + allowed session key prefixes | P2 | Done | **Service:** `WebhookDeliveryService` with HMAC-SHA256 signing (`X-AgentOps-Signature` header). Static `verifySignature()` method for consumers. Delivery ID and event name headers included. Retry 2x with 1s backoff |

---

## Phase G: Config & Protocol

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| G1 | Config reference (keys we support) + schema for UI | P2 | Done | **API:** `GET /configuration` now returns `schema` key with full field definitions (type, options, min/max, descriptions) alongside current `values`. Schema auto-documents all editable config keys. Combined with credential-storage-map.md for secrets |
| G2 | Gateway protocol (WS methods we might adopt) | P3 | Open | API/WS if we add real-time dashboard |

---

## How to Use This List

1. **Implement** a gap (code + tests).
2. **Surface in UI** per the “UI surface when complete” column (dashboard, settings, messenger).
3. **Update** this table: set Status = Done, add “UI: [location]” in notes if needed.
4. **Link** to discovery doc or PR for detail.
