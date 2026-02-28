# Messenger Gateway

The Messenger Gateway is a long-lived PHP process that manages real-time connections to messaging providers for local-mode connectors. It runs alongside Laravel Horizon as a separate supervised process.

## Overview

The gateway enables "local-first" operation for messaging providers that support persistent connections:

| Provider | Local Mode | Connection Type |
|----------|------------|-----------------|
| Slack | Socket Mode API v2 | WebSocket |
| Telegram | Long-polling | HTTP getUpdates |
| Discord | Gateway v10 | WebSocket |
| WhatsApp | Not supported | Webhook only |

## Quick Start

### Development

```bash
# Start the gateway process
php artisan agent:messenger-gateway
```

### Production

Copy the supervisor configuration example and update paths:

```bash
cp config/supervisor/messenger-gateway.conf.example /etc/supervisor/conf.d/messenger-gateway.conf
# Edit the file to update paths
supervisorctl reread
supervisorctl update
supervisorctl start messenger-gateway
```

## Architecture

### Process Model

- Single PHP process using ReactPHP event loop
- One async worker per local-mode connector account
- Workers are isolated and managed independently
- Coexists with Horizon without competing for queue workers

### Worker Types

**SlackSocketWorker**
- Connects to Slack Socket Mode API
- Requires app-level token (xapp-*) and bot token (xoxb-*)
- Handles WebSocket ping/pong heartbeat
- Acknowledges events within 3 seconds

**TelegramPollingWorker**
- Uses getUpdates API with long-polling (30s timeout)
- Automatically clears any existing webhook
- Tracks update offset for incremental retrieval

**DiscordGatewayWorker**
- Connects to Discord Gateway v10 WebSocket
- Implements full Gateway protocol: IDENTIFY, HEARTBEAT, RESUME
- Handles session management and reconnection

## Configuration

### Environment Variables

```env
# Shutdown drain timeout in seconds (default: 30)
MESSENGER_GATEWAY_SHUTDOWN_TIMEOUT=30

# Health check interval in seconds (default: 10)
MESSENGER_GATEWAY_HEALTH_INTERVAL=10

# Credential change poll interval in seconds (default: 30)
MESSENGER_GATEWAY_CREDENTIAL_POLL=30

# Reconnection strategy
MESSENGER_GATEWAY_RECONNECT_INITIAL=1    # Initial delay in seconds
MESSENGER_GATEWAY_RECONNECT_MAX=300      # Maximum delay (5 minutes)
MESSENGER_GATEWAY_RECONNECT_JITTER=20    # Jitter percentage
```

### config/messenger.php

```php
'gateway' => [
    'shutdown_timeout' => env('MESSENGER_GATEWAY_SHUTDOWN_TIMEOUT', 30),
    'health_check_interval' => env('MESSENGER_GATEWAY_HEALTH_INTERVAL', 10),
    'credential_poll_interval' => env('MESSENGER_GATEWAY_CREDENTIAL_POLL', 30),
    'reconnect' => [
        'initial_delay' => env('MESSENGER_GATEWAY_RECONNECT_INITIAL', 1),
        'max_delay' => env('MESSENGER_GATEWAY_RECONNECT_MAX', 300),
        'jitter_percent' => env('MESSENGER_GATEWAY_RECONNECT_JITTER', 20),
    ],
],
```

## Signal Handling

The gateway responds to Unix signals for process control:

| Signal | Action |
|--------|--------|
| SIGTERM | Graceful shutdown (used by supervisor/systemd) |
| SIGINT | Graceful shutdown (Ctrl+C) |
| SIGUSR1 | Output health status table |

### Graceful Shutdown Sequence

1. Stop accepting new events
2. Drain in-flight messages (max 30s timeout)
3. Close all worker connections
4. Exit with code 0 (success) or 1 (timeout exceeded)

## Monitoring

### Health Checks

The gateway performs periodic health checks (default: every 10s) and updates the `runtime_state` column on connector_accounts:

| State | Description |
|-------|-------------|
| connected | Worker is connected and receiving events |
| reconnecting | Worker is attempting to reconnect |
| disconnected | Worker is not connected |
| error | Worker encountered an error |

### Debug Output

Send SIGUSR1 to get a health status table:

```bash
# Find the gateway process
ps aux | grep messenger-gateway

# Send SIGUSR1 (replace PID)
kill -USR1 <pid>
```

This outputs a table showing connector name, provider, status, and last event time.

### Logs

The gateway logs to Laravel's configured log channel. Key log entries:

- `Messenger Gateway started` - Process startup with worker count
- `Worker started` - Individual worker connection established
- `Credential change detected` - Credentials changed, worker restarting
- `Messenger Gateway shutdown initiated` - Shutdown signal received
- `Messenger Gateway shutdown complete` - All workers drained

### Health Endpoint

Use the `/messenger/health` endpoint for monitoring tool integration:

```bash
curl http://your-app.com/messenger/health
```

Returns JSON with per-connector breakdown and aggregate status.

## Supervisor Configuration

See `config/supervisor/messenger-gateway.conf.example` for a complete supervisor configuration.

Key settings:
- `stopwaitsecs=35` - Slightly longer than shutdown timeout (30s)
- `stopsignal=TERM` - Use SIGTERM for graceful shutdown
- `autorestart=true` - Restart on unexpected exit

## Troubleshooting

### Gateway won't start

1. Check if another gateway process is running:
   ```bash
   ps aux | grep messenger-gateway
   ```

2. Check for stale lock file:
   ```bash
   rm -f storage/framework/messenger-gateway.lock
   ```

3. Verify ReactPHP is installed:
   ```bash
   composer show react/event-loop
   ```

### Workers not connecting

1. Check connector account credentials in the database
2. Verify the connector is set to `connection_mode = 'local'`
3. Check application logs for connection errors
4. For Slack: ensure you have an app-level token (xapp-*)
5. For Discord: verify bot has correct intents enabled

### High reconnection rate

1. Check network connectivity to provider APIs
2. Review logs for specific error messages
3. Verify credentials haven't been revoked
4. Check if you're hitting rate limits

### Shutdown takes too long

1. The default drain timeout is 30 seconds
2. Workers processing messages will finish before stopping
3. Increase `shutdown_timeout` if needed for long-running operations
4. Check for stuck workers in logs

### Memory usage growing

1. Monitor with `ps aux` or process monitoring tools
2. Check for event leaks in worker implementations
3. Consider periodic restarts via supervisor
4. Review logs for repeated error/reconnection cycles

## Credential Rotation

The gateway automatically detects credential changes (every 30s by default):

1. Credentials are hashed and compared on each poll
2. On change: drain current worker, stop, start with fresh credentials
3. Brief gap during restart is acceptable
4. No message loss - provider will redeliver during reconnection

## Deployment Checklist

- [ ] Supervisor or systemd configuration installed
- [ ] Log rotation configured
- [ ] Health monitoring set up
- [ ] Connector accounts configured with `connection_mode = 'local'`
- [ ] Credentials validated via `agent:install`
- [ ] Process starts and workers connect
- [ ] SIGTERM triggers graceful shutdown
