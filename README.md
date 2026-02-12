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
- Monitor now surfaces approval-needed output and provides UI actions (`Approve & Re-run` or `Deny/Stop`) to avoid console-only handling
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
- Horizon queue defaults:
  - `connection=redis`
  - `queue=agent`
  - `tries=1`
  - `backoff=0`
  - `timeout=86500`
  - `maxProcesses=2` (bounded to `1..8` via env)
