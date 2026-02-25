# Messenger Control Plane

Control your Agent installation through chat commands via Slack, Telegram, and other messenger platforms.

## Overview

The Messenger Control Plane provides a unified AI chat interface for controlling local Agent installations through supported messenger platforms. Users can:

- Create, update, and delete cron jobs using natural language
- Observe and control active runs
- Spawn new agent tasks

The system runs entirely on user infrastructure with no cloud dependency for core orchestration.

## Architecture

```
                                ┌─────────────────────────────────────────────┐
                                │              Agent Installation              │
                                │                                             │
┌─────────────────┐    Webhook  │  ┌─────────────────────────────────────┐   │
│  Slack/Telegram │────────────►│  │         Webhook Controller          │   │
│                 │◄────────────│  │                                     │   │
└─────────────────┘   Response  │  └────────────────┬────────────────────┘   │
                                │                   │                         │
                                │  ┌────────────────▼────────────────────┐   │
                                │  │          ConnectorAdapter           │   │
                                │  │  (Slack, Telegram, Discord, etc.)  │   │
                                │  └────────────────┬────────────────────┘   │
                                │                   │                         │
                                │  ┌────────────────▼────────────────────┐   │
                                │  │       Message Processing Queue       │   │
                                │  │            (Horizon)                 │   │
                                │  └────────────────┬────────────────────┘   │
                                │                   │                         │
                                │  ┌────────────────▼────────────────────┐   │
                                │  │         Chat Intent Parser           │   │
                                │  │      (Natural Language → Action)     │   │
                                │  └────────────────┬────────────────────┘   │
                                │                   │                         │
                                │  ┌────────────────▼────────────────────┐   │
                                │  │        Action Executor               │   │
                                │  │   (Jobs, Runs, Policy Validation)    │   │
                                │  └─────────────────────────────────────┘   │
                                │                                             │
                                └─────────────────────────────────────────────┘
```

## Supported Providers

### Phase A (Current)

| Provider | Inbound Mode | Default Mode | Threading | Status |
|----------|-------------|--------------|-----------|--------|
| **Slack** | WebSocket (Socket Mode) or Webhook | Local (Socket Mode) | Native threads | Available |
| **Telegram** | Long Polling or Webhook | Local (Long Polling) | Reply-to threads | Available |

### Phase B (Planned)

| Provider | Inbound Mode | Default Mode | Threading | Status |
|----------|-------------|--------------|-----------|--------|
| Discord | Gateway WebSocket or Webhook | Local (Gateway) | Native threads | Planned |
| WhatsApp | Webhook only (Cloud API) | Webhook (required) | Quote replies | Planned |

## Setup Guide

### Prerequisites

- PHP 8.2+
- Redis (for caching and queue processing)
- PostgreSQL database
- Horizon queue worker running

### Installation

Run the Agent installer with messenger configuration:

```bash
php artisan agent:install --connector=slack,telegram
```

#### Flags

| Flag | Description | Default |
|------|-------------|---------|
| `--connector=<providers>` | Comma-separated list of providers to configure | None (interactive) |
| `--mode=local\|webhook` | Connection mode | `local` |
| `--non-interactive` | Fail on missing required values | `false` |
| `--config=<path>` | Path to YAML configuration file | None |

### Provider-Specific Configuration

#### Slack Setup

1. Create a Slack App at [api.slack.com/apps](https://api.slack.com/apps)
2. Enable Socket Mode (for local mode) or configure Event Subscriptions URL
3. Add the following OAuth scopes:
   - `chat:write`
   - `channels:history`
   - `groups:history`
   - `im:history`
   - `mpim:history`
4. Install the app to your workspace
5. Configure credentials in Agent:

```bash
# Interactive configuration
php artisan agent:install --connector=slack

# Or via environment variables
SLACK_BOT_TOKEN=xoxb-your-token
SLACK_SIGNING_SECRET=your-signing-secret
SLACK_APP_TOKEN=xapp-1-your-socket-mode-token  # For local mode
```

#### Telegram Setup

1. Create a bot via [@BotFather](https://t.me/BotFather)
2. Copy the bot token
3. Configure credentials in Agent:

```bash
# Interactive configuration
php artisan agent:install --connector=telegram

# Or via environment variables
TELEGRAM_BOT_TOKEN=123456789:AABBCCDDEEFFyour-bot-token
TELEGRAM_SECRET_TOKEN=your-webhook-secret  # For webhook mode
```

For webhook mode, set your webhook URL:

```bash
curl "https://api.telegram.org/bot<token>/setWebhook?url=https://your-domain.com/agent/api/v1/connectors/telegram/webhook/<account_key>&secret_token=<secret>"
```

## Account Linking Flow

Users must link their messenger account to their Agent user account before commands are processed.

### Flow

1. User sends first message to bot
2. Bot replies with authentication link (signed, one-time-use token)
3. User clicks link → Redirected to Agent login page
4. User authenticates with Agent
5. User confirms link → Account linked
6. Bot confirms completion

### Link Expiration

Admins can configure per-provider link expiration:

```php
// In connector_accounts.config
'link_expiration_days' => 30,  // null = permanent
```

## Supported Commands

### Job Management

| Command | Action | Confirmation Required |
|---------|--------|----------------------|
| `list my jobs` | Show all jobs for the user | No |
| `create a job called "name" that runs "command" at <schedule>` | Create new job | No |
| `update job <id> schedule to <new_schedule>` | Update job schedule | Yes |
| `delete job <id>` | Delete job permanently | Yes |

### Run Control

| Command | Action | Confirmation Required |
|---------|--------|----------------------|
| `show active runs` | List currently running jobs | No |
| `stop run <id>` | Terminate running process | Yes |
| `retry run <id>` | Re-execute a failed run | No |
| `run job <id> now` | Trigger immediate execution | No |

### Steering (MVP Limitations)

| Command | Action | Confirmation Required |
|---------|--------|----------------------|
| `steer run <id>: <guidance>` | Provide guidance to running process | No |

**Note:** Steering is limited in MVP. Full steering capabilities planned for future releases.

## Security Model

### Signature Verification

All webhook requests are cryptographically verified:

| Provider | Scheme | Header |
|----------|--------|--------|
| Slack | HMAC-SHA256 | `X-Slack-Signature` |
| Telegram | Token-based | `X-Telegram-Bot-Api-Secret-Token` |
| Discord | Ed25519 | `X-Signature-Ed25519` |
| WhatsApp | HMAC-SHA256 | `X-Hub-Signature-256` |

### Replay Protection

Prevents replay attacks via two strategies:

| Strategy | Used By | Protection |
|----------|---------|------------|
| Timestamp | Slack, Discord | Reject messages older than 5 minutes |
| Event ID | Telegram, WhatsApp | Deduplicate by event ID with TTL |

### Authorization

1. **Identity-based:** Actions are authorized based on the linked Agent user's permissions
2. **Channel restrictions:** Admins can configure specific channels as read-only
3. **Policy validation:** All actions validated against `CommandPolicy`, `PathPolicy`, `EnvPolicy`

### Confirmation Workflow

Destructive actions require explicit confirmation:

```
User: delete job 42
Bot:  Are you sure you want to delete job "daily-backup"?
      This action cannot be undone.

      [Confirm] [Cancel]

User: [Clicks Confirm]
Bot:  Job "daily-backup" has been deleted.
```

## Troubleshooting

### Common Errors

#### "Invalid signature"

- Verify your signing secret matches the one in your Slack/provider app settings
- Check that the webhook URL is correct and accessible
- Ensure system time is synchronized (NTP)

#### "Account not linked"

- User needs to complete the account linking flow
- Check that the link token hasn't expired
- Verify the user has an Agent account

#### "Permission denied"

- User doesn't have permission for the requested action
- Check user roles and permissions in Agent
- Verify the job/run belongs to the user

#### "Rate limited"

- Provider is rate limiting requests
- System will automatically retry with exponential backoff
- Check queue health in Horizon dashboard

### Log Locations

| Log | Location | Description |
|-----|----------|-------------|
| Application logs | `storage/logs/laravel.log` | General application errors |
| Messenger logs | `storage/logs/messenger.log` | Messenger-specific activity |
| Queue logs | Horizon dashboard | Queue processing status |
| Audit logs | `agent_audit_logs` table | All messenger actions |

### Health Checks

```bash
# Check connector status
php artisan messenger:status

# Test connectivity to provider
php artisan messenger:test slack

# Verify webhook endpoint
curl -X POST https://your-domain.com/agent/api/v1/connectors/slack/webhook \
  -H "Content-Type: application/json" \
  -d '{"type": "url_verification", "challenge": "test"}'
```

### Debugging Webhooks

Enable debug logging for messenger webhooks:

```env
LOG_LEVEL=debug
MESSENGER_DEBUG=true
```

View incoming webhooks:

```bash
tail -f storage/logs/messenger.log
```

## API Reference

### Webhook Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/agent/api/v1/connectors/slack/webhook` | POST | Slack webhook receiver |
| `/agent/api/v1/connectors/telegram/webhook/{accountKey}` | POST | Telegram webhook receiver |
| `/agent/api/v1/connectors/discord/webhook` | POST | Discord webhook receiver |
| `/agent/api/v1/connectors/whatsapp/webhook` | POST | WhatsApp webhook receiver |

### Account Link Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/messenger-link/{token}` | GET | Display link confirmation page |
| `/messenger-link/{token}` | POST | Complete account link |

### Chat API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/agent/api/v1/chat/sessions` | GET | List user's chat sessions |
| `/agent/api/v1/chat/sessions/{id}/messages` | GET | Get session message history |
| `/agent/api/v1/chat/actions/{id}` | GET | Get action execution status |
| `/agent/api/v1/chat/runs/{id}/stream` | GET | SSE stream for run updates |

### Connector Management Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/agent/api/v1/connectors` | GET | List configured connectors |
| `/agent/api/v1/connectors` | POST | Add connector account |
| `/agent/api/v1/connectors/{id}` | DELETE | Remove connector |
| `/agent/api/v1/connectors/{id}/test` | POST | Test connectivity |

## Configuration Reference

### Connector Account Config

```php
'config' => [
    // Confirmation behavior
    'confirmation_required' => true,

    // Identity linking
    'link_expiration_days' => null,  // null = permanent

    // Signature verification
    'signature_verification' => [
        'scheme' => 'hmac_sha256',  // or 'ed25519', 'token'
        'signing_secret' => '...',
    ],

    // Replay protection
    'replay_protection' => [
        'strategy' => 'timestamp',  // or 'event_id_dedupe'
        'window_seconds' => 300,
    ],

    // Rate limiting
    'rate_limit' => [
        'requests_per_second' => 1,
        'burst_limit' => 5,
        'backoff_base_seconds' => 1,
        'backoff_max_seconds' => 300,
        'jitter_percent' => 20,
    ],

    // Session context
    'session_history_limit' => 20,
    'default_verbosity' => 'summary',  // full, summary, errors_only

    // Attachments
    'max_file_size_mb' => 10,
    'allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
]
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MESSENGER_DEBUG` | Enable debug logging | `false` |
| `MESSENGER_CONFIRMATION_TIMEOUT` | Confirmation prompt timeout (seconds) | `300` |
| `MESSENGER_LINK_TTL` | Account link token TTL (minutes) | `15` |
| `MESSENGER_SESSION_LIMIT` | Max messages per session | `100` |
