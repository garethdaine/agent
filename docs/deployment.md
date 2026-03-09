# Deployment Guide

## Deploy Steps

1. **Pull latest code**
   ```bash
   git pull origin main
   ```

2. **Install dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

3. **Run migrations**
   ```bash
   php artisan migrate --force
   ```

4. **Clear and rebuild caches**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

5. **Restart queue workers gracefully**
   ```bash
   php artisan horizon:terminate
   ```
   Horizon will finish currently running jobs before restarting. The `fast_termination` option in `config/horizon.php` controls whether a new Horizon instance can start while the old one drains.

6. **Verify deployment**
   ```bash
   php artisan about
   ```

## Environment Variable Checklist

Ensure the following are set before deploying:

| Variable | Description | Required |
|----------|-------------|----------|
| `APP_ENV` | Must be `production` | Yes |
| `APP_KEY` | Application encryption key | Yes |
| `APP_URL` | Public-facing URL | Yes |
| `DB_CONNECTION` | Database driver (`pgsql`) | Yes |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` | PostgreSQL connection | Yes |
| `DB_USERNAME` / `DB_PASSWORD` | Database credentials | Yes |
| `REDIS_HOST` / `REDIS_PORT` | Redis connection | Yes |
| `HORIZON_NAME` | Horizon instance identifier | Yes |
| `SENTRY_DSN` | Sentry error tracking endpoint | Recommended |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | OpenTelemetry collector | Optional |
| `MEMORY_API_BASE_URL` | Memory service endpoint | Optional |
| `LOG_CHANNEL` | Logging channel (`stack`) | Yes |
| `QUEUE_CONNECTION` | Queue driver (`redis`) | Yes |

## Health Check Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /` | Application root — confirms app is serving |
| `GET /horizon` | Horizon dashboard — confirms queue system is running |
| `GET /up` | Laravel's built-in health check (returns 200 if app is healthy) |

For automated monitoring, poll `GET /up` and alert if non-200 response persists for >2 consecutive checks.

## Rollback Procedure

1. **Terminate Horizon** to stop processing new jobs:
   ```bash
   php artisan horizon:terminate
   ```

2. **Revert to previous release:**
   ```bash
   git checkout <previous-tag-or-commit>
   ```

3. **Rollback migrations** (if the release included migrations):
   ```bash
   php artisan migrate:rollback --step=<number-of-migrations-in-release>
   ```

4. **Reinstall dependencies and rebuild:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

5. **Rebuild caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

6. **Restart Horizon:**
   ```bash
   php artisan horizon:terminate
   ```

7. **Verify rollback** by checking the health check endpoints above.
