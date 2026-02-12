# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Agent Scheduler is a local-first Laravel 12 + Jetstream application for managing and running scheduled agent jobs. It dispatches jobs to queue workers that spawn local subprocesses (Claude, Codex, or custom runners) and monitors their execution in near real-time.

## Development Commands

### Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

### Running the Application
Start each in its own terminal:
```bash
php artisan serve          # Web server
php artisan horizon        # Queue worker (required)
php artisan schedule:work  # Scheduler
npm run dev                # Vite HMR for frontend development
```

Or use the combined dev script:
```bash
composer dev  # Runs serve, queue:listen, pail (logs), and vite concurrently
```

### Testing
```bash
composer test                    # Run all tests (clears config first)
php artisan test                 # Run PHPUnit directly
php artisan test --filter=AgentJobValidationTest  # Single test class
```

### Code Quality
```bash
./vendor/bin/pint               # Laravel Pint code style fixer
php artisan route:list --path=agent/api/v1  # List API routes
```

## Architecture

### Core Domain Models

The application manages scheduled agent jobs through these primary models:

- **AgentJob** (`app/Models/AgentJob.php`) - Scheduled job definitions with cron expressions, runner configuration, and ownership
- **AgentJobRun** (`app/Models/AgentJobRun.php`) - Individual job executions with lifecycle states: `queued|starting|running|stopping|succeeded|failed|killed|timed_out|skipped`
- **AgentRunEvent** (`app/Models/AgentRunEvent.php`) - Stdout/stderr/lifecycle events captured during runs
- **SchedulerHeartbeat** (`app/Models/SchedulerHeartbeat.php`) - Scheduler health monitoring
- **AgentSystemState** (`app/Models/AgentSystemState.php`) - Key-value store for system cursors (e.g., `dispatch_last_minute_utc`)
- **AgentAuditLog** (`app/Models/AgentAuditLog.php`) - Immutable audit records for mutating actions

### Validation Policy Classes

Located in `app/Support/Agent/`, these enforce strict security constraints:

- **CommandPolicy** - Validates command templates, resolves executables against allowlist, enforces placeholder rules
- **PathPolicy** - Validates absolute paths, enforces base directory restrictions, checks file existence and permissions
- **EnvPolicy** - Validates environment variable overrides, blocks forbidden keys (PATH, HOME, secrets patterns)

### API Structure

All JSON API routes are versioned under `/agent/api/v1/`:
- Routes defined in `routes/api.php`
- Version header middleware: `AgentApiVersionHeader` (sets `X-Agent-Api-Version: 1.0`)
- Request validation: `StoreAgentJobRequest` in `app/Http/Requests/Agent/`
- Controller: `AgentJobController` in `app/Http/Controllers/Api/V1/`

### Configuration

- **config/agent.php** - Core agent configuration:
  - `allowed_working_directory_bases` - Permitted working directory paths
  - `allowed_task_markdown_bases` - Permitted task file paths
  - `runner_executables` - Allowlisted executables (claude, codex, custom)
  - `default_templates` - Default command templates per runner type
  - `allowed_placeholders` - Valid template placeholders
  - `forbidden_env_keys` - Blocked environment variable names

- **config/horizon.php** - Queue worker configuration:
  - Queue: `agent`
  - Connection: `redis`
  - Defaults: `tries=1`, `backoff=0`, `timeout=86500`, `maxProcesses=2`

### Frontend Stack

- Vue 3 + Inertia.js (SSR enabled)
- Tailwind CSS with dark mode
- Laravel Echo + Reverb for websockets
- Resources in `resources/js/` and `resources/views/`

## Key Constraints

### Runner Types and Executables
Jobs use one of three runner types: `claude`, `codex`, or `custom`. The executable path is resolved from `runner_type`, not from the template. Custom runners must include `{{task_markdown_path}}` placeholder.

### Path Validation
All paths must be absolute, within allowed base directories (after `realpath()` resolution), and boundary-safe. Task markdown files must end in `.md` or `.markdown` and contain valid UTF-8 text.

### Cron Expression
Only explicit 5-part numeric cron syntax is accepted (no wildcards like `* * * * *`). Uses `dragonmantank/cron-expression` v3.

### Test Sandbox
Tests create a sandbox at `storage/framework/testing/agent-sandbox/` with mock executables. The test `setUp()` reconfigures allowed paths to point to this sandbox.

## Prerequisites

- PHP 8.3+
- Composer 2+
- Node.js + npm
- Redis (required for queue/cache)
- PostgreSQL (primary, also supports SQLite/MySQL)
