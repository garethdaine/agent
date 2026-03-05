# Session Key Design & Retention

## Session Key Format

### Chat Sessions

Chat sessions use a composite key structure:

```
{connector_id}:{channel_type}:{channel_id}:{peer_id?}
```

| Component | Description | Example |
|-----------|-------------|---------|
| `connector_id` | UUID of the ConnectorAccount | `abc123` |
| `channel_type` | `dm` or `group` | `dm` |
| `channel_id` | Provider channel/guild ID | `discord:123456789` |
| `peer_id` | User ID (for per_peer DM scope) | `user:987654` |

**DM Session Scope** controls whether DMs share a single session or are per-user:
- `main` — all DMs on a connector share one session (key omits `peer_id`)
- `per_peer` — each user gets their own session (key includes `peer_id`)

Configurable per-connector via API: `PUT /connectors/{id}/policy` with `dm_session_scope`.

### Runtime Sessions

Runtime sessions use UUID primary keys with a human-readable `label`:

```
rt:{connector_id}:{turn_count}:{timestamp}
```

## Session Lifecycle

```
Created → Active → Archived
              ↓
          (timeout) → Auto-archived
```

### States

| State | Meaning |
|-------|---------|
| `active` | Currently in use, messages accepted |
| `archived` | Closed, read-only |

### Transitions

- **Active → Archived**: Manual via API (`POST /chat-sessions/{id}/archive`) or UI
- **Active → Archived (auto)**: Configurable idle timeout (default: none, opt-in)

## Pruning & Retention

### Chat Messages

Chat messages accumulate per session. Retention is managed at two levels:

1. **Compaction** — After a configurable message count, older messages are summarized into a compaction record. The `compaction_at` timestamp on `ChatSession` tracks when this last occurred.

2. **Hard deletion** — Messages older than the retention period are permanently removed.

### Configuration

```php
// config/messenger.php
'session' => [
    'idle_archive_hours' => env('MESSENGER_SESSION_IDLE_ARCHIVE_HOURS', null),
    'message_retention_days' => env('MESSENGER_MESSAGE_RETENTION_DAYS', 90),
    'compaction_threshold' => env('MESSENGER_COMPACTION_THRESHOLD', 200),
],
```

### Runtime Sessions

Runtime sessions follow a separate retention policy:

```php
// config/runtime.php
'session' => [
    'max_concurrent' => env('RUNTIME_MAX_CONCURRENT_SESSIONS', 3),
    'idle_timeout_minutes' => env('RUNTIME_SESSION_IDLE_TIMEOUT', 30),
    'retention_days' => env('RUNTIME_SESSION_RETENTION_DAYS', 30),
],
```

## Pruning Schedule

Pruning runs via the Laravel scheduler in `routes/console.php`:

- **Daily**: Archive idle chat sessions (if `idle_archive_hours` configured)
- **Daily**: Delete chat messages past retention
- **Weekly**: Hard-delete archived sessions older than 2x retention

## UI Surface

- **Messenger → Chat History**: View all sessions, filter by status, browse history
- **Messenger → Sessions (Runtime)**: View runtime sessions with tool call history
- **Settings → Configuration**: View retention and session config values (read-only)
