# Cache Strategy

## Key Naming Convention

All cache keys follow the pattern: `{domain}:{entity}:{id}`

Examples:
- `connector:account:123` — a specific connector account
- `delegation:graph:456` — a delegation graph
- `interrogation:session:789` — an interrogation session
- `agent:settings:global` — global agent settings
- `org:council:42` — an organization council

For collection keys, use plurals: `connector:accounts:org:5` (all accounts for org 5).

## Invalidation Strategy

### Model Observer Events
Use Eloquent model events (`saved`, `deleted`) to invalidate related cache entries immediately. This ensures cache consistency without polling.

```php
// Example pattern in a model observer
public function saved(ConnectorAccount $account): void
{
    Cache::forget("connector:account:{$account->id}");
    Cache::forget("connector:accounts:org:{$account->organization_id}");
}
```

### TTL Fallback
All cached values must include a TTL as a safety net against stale data:

| Data Type | TTL | Rationale |
|-----------|-----|-----------|
| Configuration / settings | 1 hour | Rarely changes, low cost to re-fetch |
| Entity lookups | 15 minutes | Moderate change frequency |
| Computed aggregates | 5 minutes | Derived data that may shift |
| Rate limit counters | Matches window | Must expire with the rate limit window |

## Cache Warming

Run `php artisan cache:warm` during deployment to pre-populate frequently accessed cache entries:

```bash
# Add to deploy script after cache:clear
php artisan cache:warm
```

The warm command should populate:
- Global agent settings
- Active connector account configs
- Organization metadata for active orgs

## Redis Memory Limits

Redis databases are allocated as follows (see `config/database.php`):

| DB | Purpose | Recommended `maxmemory` |
|----|---------|------------------------|
| 0  | Default (queues, sessions) | 256MB |
| 1  | Cache | 128MB |
| 2  | Memory system | 64MB |

Set `maxmemory-policy allkeys-lru` on DB 1 (cache) so least-recently-used keys are evicted under memory pressure. Queue databases (DB 0) should use `noeviction` to prevent job loss.

## Guidelines

- Never cache user-specific sensitive data (tokens, credentials) — use `CredentialVault` instead.
- Use `Cache::tags()` only if the cache driver supports it (Redis does, file does not).
- Prefix all custom cache operations with the domain namespace to avoid key collisions.
- Monitor cache hit rates via Laravel Pulse to identify candidates for warming or TTL adjustment.
