# OpenClaw Security & Governance Parity — Discovery

**Status:** Discovery (Phase A)  
**Created:** 2026-03-04  
**Plan:** [openclaw-full-parity-analysis-plan.md](../plans/openclaw-full-parity-analysis-plan.md)

This document captures gaps between OpenClaw’s security and governance model and Agent Ops, and **where each capability must be surfaced in the UI** as features are completed.

---

## 1. Trust Model & Scope

| OpenClaw | Agent Ops | Gap | UI surface when complete |
|----------|-----------|-----|--------------------------|
| Personal-assistant trust model (one operator per gateway) | Jetstream teams + per-user; multi-tenant possible | We support teams/orgs; need explicit “trust boundary” docs and RBAC for enterprise | **Dashboard:** Org/team settings, “Trust boundary” or Security overview; **Docs:** Security runbook |
| `openclaw security audit` (CLI) | No equivalent single command | Need `php artisan agent:security-audit` (or similar) that checks config, perms, bind, tool policy | **Dashboard:** Settings → Security → “Run audit” button + results; **CLI:** `agent:security-audit` |
| Audit checklist (model choice, plugins, perms, browser exposure, network, open+tools) | AgentAuditLog + AuditLogger for mutations; no security-audit checklist | Extend audit to cover “security posture” (config, bind, tool profile, allowlists) | **Dashboard:** Security / Audit tab with filter by “security events”; **API:** Audit log filtered by type |
| Credential storage map (where secrets live) | .env + optional Laravel secrets / vault | Document credential map; consider sealed secrets for enterprise | **Dashboard:** Settings → Secrets (read-only list of secret names/sources, no values); **Docs:** Credential map |

---

## 2. Gateway Auth & Device Pairing

| OpenClaw | Agent Ops | Gap | UI surface when complete |
|----------|-----------|-----|--------------------------|
| `gateway.auth` token/password | Laravel Sanctum (API tokens) + Jetstream auth | Dashboard already auth’d via session; API uses Sanctum. No “gateway token” for non-browser clients | **Dashboard:** Settings → API / Gateway → “Create gateway token” for CLI/remote; **API:** Token CRUD (scoped to user/team) |
| Device pairing (Control UI first connection) | No device pairing; any logged-in user can use dashboard | Optional: device approval for dashboard access (enterprise) | **Dashboard:** Settings → Devices → list pending/approved devices, approve/revoke; **Messenger:** N/A |
| `allowedOrigins` for Control UI | Laravel CORS / session same-origin | We serve dashboard same-origin; if we add remote dashboard, need explicit allowedOrigins | **Config/Env:** `DASHBOARD_ALLOWED_ORIGINS`; **Dashboard:** Settings → Security → “Allowed origins” (when remote access enabled) |
| Loopback vs remote auth | N/A today (dashboard is same-app) | When we support “remote dashboard” (e.g. separate SPA), enforce token for non-loopback | **Dashboard:** Connection screen when origin != app origin: prompt for gateway token |

---

## 3. Tool Policy & Exec Approvals

| OpenClaw | Agent Ops | Gap | UI surface when complete |
|----------|-----------|-----|--------------------------|
| Tool profiles: messaging / minimal / full | config/runtime.php `modes` (safe / standard / full) with capabilities + approvals_required | We have mode-based policy; no named “profiles” (messaging/minimal) | **Dashboard:** Messenger → Runtime → “Mode” selector per session; **Messenger:** `/mode safe\|standard\|full` already; **Settings:** Default mode + profile labels |
| `tools.deny` allowlist (e.g. gateway, cron, sessions_spawn) | PolicyEngine + ApprovalGate by tool name; no explicit “deny list” in config | Add config `runtime.tool_deny` (and optional `tool_allow` override) for enterprise lock-down | **Dashboard:** Settings → Runtime → “Tool deny list” (and allow list); **API:** Config or dedicated runtime policy endpoint |
| Exec approvals: allowlist + ask | ApprovalGate + RuntimeApproval; allow-once/allow-always/deny | We have pending/approved/denied; clarify “allow once” vs “allow always” in UI and audit | **Dashboard:** Messenger → Runtime → Session → “Pending approvals” with Allow once / Allow always / Deny; **Messenger:** `/approve`, `/deny`; **API:** POST approve/deny with reason |
| Sandbox (non-main session in Docker) | No sandbox; runtime runs in app process | OpenClaw runs non-main in Docker; we could add optional sandbox per session (later) | **Dashboard:** Session detail “Sandbox” indicator if implemented; **Settings:** Sandbox on/off per connector or global |
| Tool blast radius (open groups + elevated) | PolicyEngine + mode; group vs DM not yet policy-driven | Add “group policy”: e.g. no full mode in groups, or require mention | **Dashboard:** Messenger → Connectors → per-connector “Group policy”; **Config:** `messenger.groups.require_mention`, `messenger.groups.max_mode` |

---

## 4. DM / Group Access (Pairing & Allowlists)

| OpenClaw | Agent Ops | Gap | UI surface when complete |
|----------|-----------|-----|--------------------------|
| DM policy: pairing / allowlist / open / disabled | Connector lifecycle; no explicit “DM policy” | Add per-connector DM policy: pairing (code + approve), allowlist, open, disabled | **Dashboard:** Messenger → Connectors → [connector] → “DM access”: Pairing / Allowlist / Open / Disabled; **Messenger:** Pairing code flow + `/pairing approve` (or in-dashboard approve) |
| Pairing approve via CLI | No pairing yet | `php artisan messenger:pairing list|approve <connector> <code>` | **Dashboard:** Messenger → Pairing → Pending requests → Approve/Reject; **CLI:** `messenger:pairing` |
| Group allowlist + mention gating | Slash commands; no per-group allowlist in config | Config: which groups/channels accepted; require mention in groups | **Dashboard:** Messenger → Connectors → Groups: allowlist + “Require @mention”; **Config:** `channels.*.groups`, `requireMention` |
| `session.dmScope`: main vs per-channel-peer | Single session per connector account (or per user) | Per-channel-peer isolation for DMs (multi-user safety) | **Dashboard:** Settings → Messenger → “DM session scope”: Main / Per channel+peer; **API:** Session creation respects scope |

---

## 5. Secrets & Config

| OpenClaw | Agent Ops | Gap | UI surface when complete |
|----------|-----------|-----|--------------------------|
| Secrets: env, file, exec, SecretRef | .env, config, optional Laravel sealed secrets | Document; optional vault/secret manager for tokens | **Dashboard:** Settings → Secrets (names only); **Docs:** Where each secret lives |
| Config schema + form (Control UI) | config/*.php; no schema-driven UI | Runtime/messenger config could be editable in UI with validation | **Dashboard:** Settings → Runtime / Messenger: form generated from config schema (with “Advanced: raw” fallback) |
| Config apply + restart with validation | Deploy/config outside app | N/A for Laravel (config cached); “Apply” = save and optional queue restart | **Dashboard:** Settings → “Save and apply” with validation errors inline |

---

## 6. Audit & Compliance

| OpenClaw | Agent Ops | Gap | UI surface when complete |
|----------|-----------|-----|--------------------------|
| Immutable audit for tool invocation + approval | AuditLogger + AgentAuditLog; runtime approvals in RuntimeApproval | Ensure every tool call + approval decision writes to AgentAuditLog (or dedicated runtime_audit) | **Dashboard:** Audit log (existing or new) with filters: type=runtime_tool_call, runtime_approval; **API:** GET audit with filters |
| Session transcript on disk | We don’t persist full transcript to disk by default | Optional: persist turns/messages for forensic (with retention policy) | **Dashboard:** Settings → Runtime → “Transcript retention”; **Storage:** Optional export/archive |
| Redact secrets in logs | OpenClaw `logging.redactSensitive` | Ensure we redact in logs (env, tokens, approval args if sensitive) | **Config:** `logging.redact_sensitive`; **Dashboard:** Settings → Logging → “Redact sensitive” toggle |

---

## 7. Hardened Baseline (60-Second)

OpenClaw suggests: loopback bind, token auth, per-channel-peer DM scope, tool profile messaging, deny automation/runtime/fs/sessions_spawn/sessions_send, fs workspaceOnly, exec security=deny ask=always, elevated off, groups requireMention.

| Item | Agent Ops | UI surface when complete |
|------|-----------|---------------------------|
| Loopback bind | N/A (app is the server) | — |
| Token auth | Sanctum | Dashboard + API token management |
| DM scope per-channel-peer | Not yet | Settings → Messenger → DM scope |
| Tool profile “messaging” | Our “safe” mode is similar | Mode selector in dashboard + messenger |
| Deny list (automation, runtime, fs, sessions_*) | Not yet | Settings → Runtime → Tool deny list |
| Exec deny/ask | ApprovalGate; no “deny” only | Settings → Runtime → Exec: Deny / Ask always |
| Elevated off | We have no “elevated” flag | Optional “elevated” capability in full mode; Settings → Runtime |
| Groups require mention | Not yet | Connectors → Groups → Require @mention |

---

## 8. UI Surfacing Summary (Where to Show What)

As each gap is closed, surface it here and in the app:

| Area | Where in UI | Notes |
|------|-------------|--------|
| Security audit | Settings → Security → Run audit | Button + results; link to CLI |
| Gateway token | Settings → API / Gateway | Create/revoke tokens for CLI/remote |
| Device pairing | Settings → Devices | Optional; pending + approve/revoke |
| Tool policy / mode | Messenger → Runtime (session) + Settings → Runtime | Mode per session; default mode + deny list in settings |
| Pending approvals | Messenger → Runtime → [Session] | List + Allow once / Allow always / Deny |
| DM policy / pairing | Messenger → Connectors → [connector] | DM access + Pairing pending list |
| Group policy | Messenger → Connectors → Groups | Allowlist + require mention |
| Audit log | Dashboard or Settings → Audit | Filter by type (runtime, approval, job, etc.) |
| Secrets (names) | Settings → Secrets | Read-only list of secret keys/sources |
| Config (runtime/messenger) | Settings → Runtime / Messenger | Form + validation + save |

---

## 9. Next Steps (Phase A Completion)

1. **Implement** security audit command + dashboard “Run audit” (UI surface).
2. **Add** tool deny list (and optional allow list) to config + Settings → Runtime (UI).
3. **Add** DM policy (pairing / allowlist / open / disabled) + pairing approve in dashboard and CLI (UI).
4. **Ensure** every runtime tool call and approval is in audit log; add Audit log page/filters (UI).
5. **Document** credential storage map and “hardened baseline” in docs.

Once these are done, Phase A is “complete” from a discovery perspective; the master gap list will track implementation and UI surfacing.
