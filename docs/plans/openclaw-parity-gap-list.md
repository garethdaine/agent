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
| A2 | Tool deny list (and optional allow list) in config | P0 | Open | Settings → Runtime → Tool deny/allow list |
| A3 | DM policy (pairing / allowlist / open / disabled) per connector | P0 | Open | Messenger → Connectors → [connector] → DM access |
| A4 | Pairing approve flow (pending list + approve/revoke) | P0 | Open | Messenger → Pairing (or Connectors → Pairing); CLI `messenger:pairing` |
| A5 | Every runtime tool call + approval in audit log | P0 | Open | Dashboard/Settings → Audit with filters (runtime, approval) |
| A6 | Gateway token for CLI/remote (create/revoke) | P1 | Open | Settings → API / Gateway → Tokens |
| A7 | Group policy (allowlist + require @mention) | P1 | Open | Messenger → Connectors → Groups |
| A8 | DM session scope (main vs per-channel-peer) | P1 | Open | Settings → Messenger → DM session scope |
| A9 | “Allow once” vs “Allow always” in approval UX | P1 | Open | Runtime → [Session] → Pending approvals: Allow once / Allow always / Deny |
| A10 | Credential storage map (docs) + optional Secrets page | P2 | Open | Settings → Secrets (names only); Docs |
| A11 | Config form for runtime/messenger (schema-driven) | P2 | Open | Settings → Runtime / Messenger |
| A12 | Redact sensitive in logs (config + toggle) | P2 | Open | Config; Settings → Logging |

---

## Phase B: Ease of Use & Onboarding

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| B1 | First-run / onboarding flow (connect first channel in few steps) | P1 | Open | Dashboard first login or “Get started” wizard |
| B2 | Doctor/diagnostics command + dashboard diagnostics | P1 | Open | Settings → Diagnostics or Dashboard → Health; CLI `agent:doctor` |
| B3 | Configuration UX: one place to edit (runtime, messenger) | P1 | Open | Settings → Runtime, Messenger (see A11) |
| B4 | Troubleshooting runbook (docs) | P2 | Open | In-app docs / Help |

---

## Phase C: Dashboard & Control UI

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| C1 | Runtime sessions list + detail (existing; verify completeness) | P1 | Done | Messenger → Runtime (index + show) |
| C2 | Pending approvals in session detail + approve/deny actions | P1 | Partial | Runtime → [Session] (pending approvals; add Allow once/always) |
| C3 | Channels/connectors status + config in dashboard | P1 | Partial | Tools → Messenger; expand with status + per-connector config |
| C4 | Config view/edit (runtime, messenger) with validation | P2 | Open | Settings → Runtime / Messenger |
| C5 | Debug: status, health, models, event log (read-only) | P2 | Open | Dashboard → Debug or Settings → Debug |
| C6 | Logs: live tail with filter/export | P2 | Open | Settings → Logs or Dashboard → Logs |

---

## Phase D: Messenger & Slash Commands

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| D1 | Slash command parity table (see discovery when written) | P1 | Open | Messenger (in-chat); list in docs/help |
| D2 | Channel routing + group rules (allowlist, mention) | P1 | Open | Config + Connectors UI (see A7) |
| D3 | Streaming/chunking behaviour per connector | P2 | Open | Behaviour; document in docs |

---

## Phase E: Sessions, Memory, Agent Loop

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| E1 | Session key design + pruning/retention (docs) | P2 | Open | Settings → Runtime (retention); Docs |
| E2 | sessions_* tools (list/history/send) if we add them | P2 | Open | Messenger / API |
| E3 | Memory in chat vs our pipeline (docs) | P2 | Open | Docs |

---

## Phase F: Automation

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| F1 | Cron delivery (announce, webhook) vs our scheduler | P2 | Open | Jobs UI (existing); webhook delivery if needed |
| F2 | Webhook auth + allowed session key prefixes | P2 | Open | Config; Settings → Webhooks if we add |

---

## Phase G: Config & Protocol

| # | Gap | Priority | Status | UI surface when complete |
|---|-----|----------|--------|---------------------------|
| G1 | Config reference (keys we support) + schema for UI | P2 | Open | Settings forms; Docs |
| G2 | Gateway protocol (WS methods we might adopt) | P3 | Open | API/WS if we add real-time dashboard |

---

## How to Use This List

1. **Implement** a gap (code + tests).
2. **Surface in UI** per the “UI surface when complete” column (dashboard, settings, messenger).
3. **Update** this table: set Status = Done, add “UI: [location]” in notes if needed.
4. **Link** to discovery doc or PR for detail.
