# Troubleshooting Runbook

## Quick Diagnostics

Run the built-in diagnostics to check all critical subsystems:

```bash
# Via API
curl -s https://agent.test/agent/api/v1/diagnostics | jq .

# Via Debug Panel
# Navigate to Tools → Diagnostics in the UI
```

## Common Issues

### 1. Queue Not Processing Jobs

**Symptoms:** Jobs stay in `queued` status indefinitely.

**Check:**
```bash
php artisan horizon:status
# or
php artisan queue:work --once --verbose
```

**Causes & Fixes:**
- Horizon not running → `php artisan horizon`
- Redis down → check `redis-cli ping`
- Queue connection misconfigured → verify `QUEUE_CONNECTION=redis` in `.env`
- Worker crashed → check `storage/logs/laravel.log` for exceptions

### 2. Messenger Not Responding

**Symptoms:** Bot doesn't reply to messages in Discord/Slack/Telegram.

**Check:**
- Tools → Messenger page shows connector status
- Tools → Diagnostics → look for messenger check failures

**Causes & Fixes:**
- Bot token expired/invalid → update in `.env` and restart
- Webhook URL not configured → set in provider dashboard
- Rate limited by provider → check `storage/logs/messenger.log` for 429 errors
- Channel policy blocking → check connector DM policy (Settings → Configuration)
- Pairing required but user not paired → navigate to Messenger → Pairings

### 3. Runtime Sessions Stuck

**Symptoms:** Sessions show `active` but no tool calls processing.

**Check:**
```bash
# List active sessions via API
curl -s https://agent.test/agent/api/v1/runtime/sessions?status=active | jq .
```

**Causes & Fixes:**
- Approval gate blocking → check pending approvals in session detail
- LLM provider unreachable → verify API keys in Settings → Credentials
- Policy engine rejecting tool → check runtime policy at Tools → Runtime
- Queue timeout → check Horizon failed jobs

### 4. Database Connection Failures

**Symptoms:** 500 errors, "could not find driver" or connection refused.

**Check:**
```bash
php artisan db:monitor
php artisan migrate:status
```

**Causes & Fixes:**
- PostgreSQL not running → `brew services start postgresql` (macOS)
- Wrong credentials → verify `DB_*` variables in `.env`
- Migration pending → `php artisan migrate`
- Connection pool exhausted → check `DB_POOL_SIZE` or reduce concurrent workers

### 5. Memory Pipeline Not Processing

**Symptoms:** Memory consolidation/formation not running.

**Check:**
```bash
php artisan memory:stats
```

**Causes & Fixes:**
- Scheduler not running → `php artisan schedule:work`
- pgvector extension missing → `CREATE EXTENSION IF NOT EXISTS vector;`
- Embedding provider key missing → check Settings → Credentials → OpenAI

### 6. Slow API Responses

**Symptoms:** UI feels sluggish, API calls take >2s.

**Check:**
- Tools → Diagnostics → check latency_ms values
- Tools → Logs → filter for `warning` level

**Causes & Fixes:**
- N+1 queries → check `storage/logs/laravel.log` for query counts
- Redis cache miss storms → verify `CACHE_DRIVER=redis`
- Large payloads → pagination parameters not set

## Health Check Matrix

| Component | Check Command | Expected |
|-----------|--------------|----------|
| Database | `php artisan db:monitor` | Connected |
| Redis | `redis-cli ping` | PONG |
| Queue | `php artisan horizon:status` | Running |
| Scheduler | Check `scheduler_heartbeats` table | < 2min old |
| Messenger | `/agent/api/v1/diagnostics` | All connectors OK |
| Storage | `php artisan storage:link` | Linked |

## Log Locations

| Log | Path | Channel |
|-----|------|---------|
| Application | `storage/logs/laravel.log` | `stack` |
| Messenger | `storage/logs/messenger.log` | `messenger` |
| Runtime | `storage/logs/runtime.log` | `runtime` |
| Documentation | `storage/logs/docs.log` | `docs` |

All logs are available via the **Tools → Logs** live tail in the UI.

## Escalation

If diagnostics pass but the system still misbehaves:

1. Enable debug mode: `APP_DEBUG=true` (never in production)
2. Enable sensitive log redaction: `LOG_REDACT_SENSITIVE=true`
3. Check the Debug Panel (Tools → Diagnostics) for environment info
4. Export logs via the Logs page for external analysis
