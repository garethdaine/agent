# Agent Scheduler (Laravel 12 + Jetstream)

Local-first Laravel app for managing and running scheduled agent jobs.

## Current Status
- Laravel 12 scaffolded
- Jetstream + Inertia + SSR + dark mode installed
- Horizon installed and configured for `redis` / `agent` queue
- Reverb / Echo configured for websocket support
- Initial Phase 1 schema + models + ownership policies added
- Versioned API base scaffolded at `/agent/api/v1` with job-create validation guardrails

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
```

## Notes
- Reverb host is configured for Herd at `reverb.herd.test` (TLS/443).
- API version header middleware sets `X-Agent-Api-Version: 1.0` on `/agent/api/v1/*` routes.
- Horizon queue defaults:
  - `connection=redis`
  - `queue=agent`
  - `tries=1`
  - `backoff=0`
  - `timeout=86500`
  - `maxProcesses=2` (bounded to `1..8` via env)
