# Agent Scheduler (Laravel 12 + Jetstream)

Local-first Laravel app for managing and running scheduled agent jobs.

## Current Status
- Laravel 12 scaffolded
- Jetstream + Inertia + SSR + dark mode installed
- Horizon installed and configured for `redis` / `agent` queue
- Reverb / Echo configured for websocket support
- Initial Phase 1 schema + models + ownership policies added
- Versioned API base scaffolded at `/agent/api/v1` with job-create validation guardrails
- Dispatcher command (`agent:dispatch-due`) and queue runner pipeline implemented
- Jobs CRUD + run-now/stop APIs + Inertia Jobs/Monitor pages implemented
- Job create/edit form includes guided schedule builder (basic frequency picker + advanced cron mode)
- Task prompt input supports both file-path mode and inline markdown editor mode (inline content is persisted to managed `.md` files)
- Monitor now surfaces approval-needed state per run in Latest Runs with a modal approval workflow (`Approve & Re-run` / `Deny/Stop`), with runner-aware approval templates for Codex and Claude
- Runner finalization now normalizes `duration_ms` to integer-safe values for PostgreSQL compatibility and resilient stop handling
- Reconciliation fingerprint checks now use immutable launch metadata captured per run to avoid false mismatches after job edits
- Reconciliation executable matching now includes launch-time command tokens/configured executable paths to support symlinked CLI binaries
- Monitor polling now de-duplicates event merges and prevents overlapping poll cycles, eliminating duplicate lifecycle lines in Event Tail
- Phases 0-7 checklist completed in `docs/minimal-cron-agent-task-list.md`
- Phase 8 maintenance baseline implemented (`agent:prune` + audit logging + retention schedules)

## Prerequisites
- PHP 8.3+
- Composer 2+
- Node.js + npm
- Redis
- PostgreSQL (local Herd database `agent`)

## Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

## Local Runtime (Canonical)
Start each in its own terminal:

```bash
php artisan serve
```

```bash
php artisan horizon
```

```bash
php artisan schedule:work
```

For frontend HMR during development:

```bash
npm run dev
```

## Validation Commands
```bash
php artisan test
php artisan route:list --path=agent/api/v1
php artisan agent:dispatch-due
php artisan agent:prune --dry-run --json
php artisan horizon:status
```

DB compatibility smoke checks:
```bash
php artisan migrate:fresh --database=pgsql --force
DB_PORT=3306 php artisan migrate:fresh --database=mysql --force --no-interaction
```

## Notes
- Reverb host is configured for Herd at `reverb.herd.test` (TLS/443).
- Sanctum SPA session auth is enabled for `/agent/api/v1/*`; ensure `SANCTUM_STATEFUL_DOMAINS` includes your local app host (for example `agent.test` or `agent.herd.test`).
- API version header middleware sets `X-Agent-Api-Version: 1.0` on `/agent/api/v1/*` routes.
- If runs appear stalled/queued unexpectedly, check `php artisan horizon:status`; start/restart workers with `php artisan horizon`.
- Horizon queue defaults:
  - `connection=redis`
  - `queue=agent`
  - `tries=1`
  - `backoff=0`
  - `timeout=86500`
  - `maxProcesses=2` (bounded to `1..8` via env)
