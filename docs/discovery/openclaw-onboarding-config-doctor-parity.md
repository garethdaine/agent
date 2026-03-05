# OpenClaw Onboarding, Config & Doctor Parity — Discovery

**Status:** Discovery (Phase B)  
**Created:** 2026-03-04  
**Plan:** [openclaw-full-parity-analysis-plan.md](../plans/openclaw-full-parity-analysis-plan.md)

Phase B covers ease of use: getting started, onboarding, configuration UX, and diagnostics (doctor). Each gap includes **UI surface** when complete.

---

## 1. Getting Started & Onboarding

### 1.1 OpenClaw Flow

- **Prereqs:** Node 22+.
- **Install:** `curl | bash` or install script; alternative: npm/pnpm global, Docker, Nix.
- **Wizard:** `openclaw onboard --install-daemon` — configures auth, gateway, optional channels.
- **First chat:** `openclaw dashboard` or `http://127.0.0.1:18789/` (Control UI) — chat in browser without channel setup.
- **Optional:** `openclaw message send --target ...` once a channel is configured.

Paths: CLI wizard (macOS/Linux/WSL2) or macOS app onboarding.

### 1.2 Agent Ops Today

- **Prereqs:** PHP 8.3+, Composer, Node, Redis, PostgreSQL (AGENTS.md).
- **Install:** `composer install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate`, `npm run build`.
- **Onboarding:** Web-only — `OnboardingController` (welcome, first-job, complete); `hasCompletedOnboarding()`; optional “first job” creation.
- **First use:** Login → Dashboard; Jobs, Monitor, Messenger (connectors), Runtime sessions exist. No single “first chat” path like OpenClaw’s Control UI.

### 1.3 Gaps & UI Surface

| Gap | Priority | UI surface when complete |
|-----|----------|---------------------------|
| **B1** Single “get started” path (e.g. connect first channel in few steps) | P1 | Dashboard first login or “Get started” wizard: step 1 “Connect Messenger” → step 2 “Create first job” (optional) → Done |
| **B1b** Optional CLI install/setup script for repeatable install | P2 | Docs + optional `install.sh` or `composer create-project` flow |
| **B1c** “Chat without channel” (WebChat-only) for testing | P3 | Optional in-app WebChat or link to messenger health + sessions |

---

## 2. Configuration UX

### 2.1 OpenClaw

- Config: `~/.openclaw/openclaw.json`; env overrides (`OPENCLAW_*`).
- Control UI: config view/edit, schema-driven form, raw JSON; `config.apply` with validation; base-hash guard to avoid clobbering.
- Single place to edit: one file + optional UI.

### 2.2 Agent Ops Today

- Config: `config/*.php` + `.env`; no single JSON file. Feature flags and some settings in DB (e.g. backup settings, feature flags).
- Dashboard: per-area (Features, Backups, Credentials, Security, Messenger connectors); no unified “config schema” form for runtime/messenger.

### 2.3 Gaps & UI Surface

| Gap | Priority | UI surface when complete |
|-----|----------|---------------------------|
| **B3** One place to edit runtime/messenger (sensible defaults + form) | P1 | Settings → Runtime, Settings → Messenger (or combined “Messenger & Runtime”) with form + validation (see A11) |
| **B3b** Config validation before save (like config.apply) | P1 | Inline validation on save; reject invalid and show errors |
| **B3c** Document “where to change what” (config vs env vs UI) | P2 | In-app docs / Help → Configuration |

---

## 3. Doctor & Diagnostics

### 3.1 OpenClaw Doctor

`openclaw doctor` does:

- Config normalization and legacy key migrations.
- Legacy state migrations (sessions dir, agent dir, WhatsApp auth).
- State integrity (state dir missing, permissions, cloud-synced/SD paths, session dirs, transcript mismatch).
- Config file permissions (chmod 600).
- Model auth health (OAuth expiry, refresh, cooldowns).
- Sandbox image repair (Docker).
- Gateway service (launchd/systemd) audit + repair.
- Security warnings (open DMs, policy).
- Gateway health check + restart prompt.
- Channel status probe (from running gateway).
- Port collision diagnostics.
- Skills status.
- Gateway auth token generation.
- Writes updated config + wizard metadata.

Flags: `--yes`, `--repair`, `--repair --force`, `--non-interactive`, `--deep`, `--generate-gateway-token`.

### 3.2 Agent Ops Today

- **Health:** `GET /messenger/health` (public); `GET /agent/api/v1/health/scheduler`, `GET /agent/api/v1/health/messenger` (authenticated); MessengerHealthController dashboard (connectors, dead letters).
- **No single “doctor” command** that runs a full checklist (scheduler, queue, DB, Redis, messenger, runtime, config) and suggests fixes.

### 3.3 Gaps & UI Surface

| Gap | Priority | UI surface when complete |
|-----|----------|---------------------------|
| **B2** Doctor command: run diagnostics (scheduler, queue, DB, Redis, messenger, runtime, app key, storage) | P1 | CLI: `php artisan agent:doctor`; optional `--json` |
| **B2b** Doctor API for dashboard | P1 | GET `/agent/api/v1/diagnostics` (or `/doctor`) returning same checks as CLI |
| **B2c** Diagnostics page in dashboard | P1 | Settings → Diagnostics: “Run diagnostics” button, results (checks + status + suggested fix) |
| **B2d** Troubleshooting runbook (docs) | P2 | In-app docs / Help → Troubleshooting (what to check when X is red) |

---

## 4. Summary: Phase B Deliverables

| Item | Status | UI surface |
|------|--------|------------|
| B1 First-run / onboarding flow | Open | Dashboard or “Get started” wizard |
| B2 Doctor + dashboard diagnostics | In progress | CLI `agent:doctor`; Settings → Diagnostics; API |
| B3 Configuration UX (one place) | Open | Settings → Runtime / Messenger |
| B4 Troubleshooting runbook | Open | Docs / Help |

Implementing B2 (doctor + API + Diagnostics page) next, then B1/B3/B4 as priorities allow.
