# Requirements Discovery Summary

Session: 11

## Messenger Control Plane: Local-First Gateway Runtime & Provider Adapters

This feature closes the gap between the Messenger Control Plane specification and the current webhook-only implementation by building a local-first gateway runtime and adding Discord and WhatsApp provider adapters.

### Architecture Overview

**Gateway Runtime Model**
- Single PHP process (`agent:messenger-gateway`) using ReactPHP/Amp with PHP 8.1+ fibers
- One async worker per connector account (isolated, scales linearly)
- Coexists with Horizon without competing for queue workers
- All workers managed within the single process via async event loops

**Supported Providers & Modes**
| Provider | Local Mode | Webhook Mode | API Version |
|----------|------------|--------------|-------------|
| Slack | Socket Mode API v2 | URL verification | v2 |
| Telegram | getUpdates long-polling | setWebhook | Bot API 7.x |
| Discord | Gateway WebSocket | Ed25519 verification | Gateway v10 |
| WhatsApp | Not supported | Cloud API HMAC-SHA256 | v18+ |

### Core Services & Components

**MessengerGatewayManager**
- Supervises all gateway workers within single async process
- Monitors connector_accounts for credential changes
- Triggers graceful restart on credential rotation: drain in-flight → stop → start with fresh credentials
- Handles SIGTERM: stop accepting events → drain in-flight (max 30s) → exit cleanly

**GatewayWorkerInterface**
```php
interface GatewayWorkerInterface {
    public function start(): void;
    public function stop(): void;
    public function health(): WorkerHealthStatus;
    public function reconnect(): void;
}
```

**Worker Implementations**
- `SlackSocketWorker`: WebSocket to apps.connections.open, handles Socket Mode events
- `TelegramPollingWorker`: Long-polling loop via getUpdates API
- `DiscordGatewayWorker`: Gateway WebSocket (connect, identify, heartbeat, resume, event dispatch)

**Reconnection Strategy**
- Exponential backoff with jitter: 1s → 2s → 4s → 8s... up to 5 minute max
- Jitter: ±20% randomization to prevent thundering herd

**Mode Switching**
- Drain and switch: complete all in-flight messages through old mode before switching
- Brief gap acceptable; no message loss

### Provider Adapters

**DiscordAdapter**
- Implements common messenger adapter interface
- Gateway WebSocket for local mode
- Webhook endpoint with Ed25519 signature verification
- Threading: native threads in channels, edit-based updates in DMs (append to previous message)
- Slash command registration: automatic during `agent:install`, updates on app version changes
- Identity mapping via MessengerIdentityLink model

**WhatsAppAdapter**
- Cloud API only (webhook-only, official Meta integration)
- Webhook endpoint with HMAC-SHA256 verification
- Quote replies for threading, single summary message fallback
- Phone number-based identity mapping to MessengerIdentityLink
- Template message handling for outbound (24h window requirement)
- `agent:install` enforces webhook mode (no local mode option)

### Webhook Ingress Validation

During `agent:install`, webhook URLs validated with provider handshake:
1. Basic reachability: HTTP response, valid TLS (not expiring within 7 days)
2. Provider-specific verification:
   - Slack: URL verification challenge
   - Telegram: setWebhook confirmation
   - Discord: PING/PONG interaction endpoint validation
   - WhatsApp: Webhook verification token exchange

### Reliability & Observability

**Circuit Breaker (per-connector outbound)**
- Threshold: 5 consecutive failures trips circuit
- Cooldown: 1 minute pause
- Half-open: 3 test requests allowed before full reset
- Configuration via `config/messenger.php`

**Rate Limiting**
- Per-connector limits from `config/messenger.php` (requests_per_second, burst_limit)
- Enforced in MessengerHttpClient

**Dead-Letter Queue**
- Messages exceeding retry duration moved to DLQ
- Dashboard: view failed messages with error details
- Actions: manual retry (individual or bulk)

**Structured Logging**
- Correlation ID scope: single message lifecycle (webhook receipt → parse → execute → response)
- Resets for each inbound message

**Health Endpoint (MessengerHealthController)**
- Always returns HTTP 200 with per-connector breakdown
- Includes aggregate summary field for monitoring tools
- Reports real worker state from gateway manager, not static config

**Queue Health Metrics**
- Aligned with actual Horizon queue names

### Configuration

**config/messenger.php additions**
```php
'gateway' => [
    'shutdown_timeout' => 30, // seconds
    'reconnect' => [
        'initial_delay' => 1, // seconds
        'max_delay' => 300, // 5 minutes
        'jitter_percent' => 20,
    ],
],
'circuit_breaker' => [
    'failure_threshold' => 5,
    'cooldown_seconds' => 60,
    'half_open_requests' => 3,
],
```

### Database Changes

**connector_accounts model**
- `connection_mode`: local | webhook (enforced at runtime)
- `runtime_state`: connected | reconnecting | disconnected | error (reflects actual worker health)
- `last_health_check_at`: timestamp of last worker heartbeat

### CLI Commands

**agent:messenger-gateway**
- Long-lived process alongside Horizon
- Manages per-connector worker lifecycles
- Heartbeat monitoring with automatic reconnection
- Graceful shutdown on SIGTERM (30s drain timeout)

**agent:install enhancements**
- Mode-aware credential validation per provider
- Skip webhook URL for local mode, require/validate for webhook mode
- Provider callback readiness probes with actionable diagnostics
- Discord slash command auto-registration

### Test Coverage Requirements

Core flows plus edge cases for each provider/mode:
- Inbound message → parse → execute → outbound response
- Rate limiting behavior
- Circuit breaker trips and recovery
- Reconnection sequences
- Deduplication
- Attachment handling

## Goals

- Build MessengerGatewayManager service supervising async workers within a single PHP process using ReactPHP/Amp
- Implement SlackSocketWorker for Slack Socket Mode API v2 (WebSocket to apps.connections.open)
- Implement TelegramPollingWorker for Telegram Bot API 7.x long-polling via getUpdates
- Implement DiscordGatewayWorker for Discord Gateway v10 WebSocket protocol
- Build DiscordAdapter with Ed25519 webhook verification, native channel threads, and edit-based DM responses
- Build WhatsAppAdapter for Cloud API v18+ with HMAC-SHA256 webhook verification and template message support
- Create agent:messenger-gateway Artisan command as long-lived process coexisting with Horizon
- Implement mode-aware credential validation in agent:install (different token sets per mode)
- Add provider callback readiness probes during webhook URL validation in agent:install
- Implement automatic Discord slash command registration during agent:install
- Add runtime state persistence on connector_accounts reflecting actual worker health
- Wire per-connector circuit breaker (5 failures, 1 min cooldown, 3 half-open requests)
- Implement dead-letter queue with dashboard for viewing and manual/bulk retry
- Add correlation ID logging scoped to single message lifecycle
- Update MessengerHealthController to report real gateway worker state with tiered response format
- Align queue health metrics with actual Horizon queue names


## Constraints

- PHP 8.1+ required for ReactPHP/Amp fiber support
- Gateway process must coexist with Horizon without competing for queue workers
- Slack Socket Mode API v2 compatibility required
- Telegram Bot API 7.x compatibility required
- Discord Gateway v10 compatibility required
- WhatsApp Cloud API v18+ compatibility required
- WhatsApp supports webhook mode only (no local mode option)
- config/messenger.php remains single source for rate limit configuration (requests_per_second, burst_limit)
- One async worker per connector account (isolated process model)
- Graceful shutdown must drain in-flight messages within 30 seconds on SIGTERM
- Mode switching must drain all in-flight messages before switching (no message loss)
- Credential changes trigger graceful restart (drain → stop → start with new credentials)
- Non-goals: native mobile clients, multi-tenant SaaS hosting, live stdin/PTY steering, replacing scheduler/runner internals


## Acceptance Criteria

- Slack connector runs in Socket Mode without a public webhook URL
- Telegram connector runs in long-poll mode without a public webhook URL
- Discord connector handles events via Gateway WebSocket in local mode
- Discord connector handles events via webhook with Ed25519 signature verification in webhook mode
- WhatsApp connector handles events via Cloud API webhook with HMAC-SHA256 verification
- Webhook mode includes validated ingress with passing provider-specific callback checks (Slack URL verification, Telegram setWebhook, Discord PING/PONG)
- Connection mode shown in UI equals active runtime mode
- Connector health endpoint reflects real worker state (connected/reconnecting/disconnected/error), not static config
- Discord slash commands auto-register during agent:install and update on app version changes
- Discord DM responses edit the previous message rather than sending new messages
- Gateway supervisor reconnects with exponential backoff (1s→2s→4s→8s... max 5min) with ±20% jitter
- Circuit breaker trips after 5 consecutive outbound failures, pauses 1 minute, allows 3 half-open requests
- Dead-letter queue dashboard displays failed messages with error details and supports individual/bulk retry
- Correlation IDs trace single message lifecycle from webhook receipt through response
- Health endpoint returns HTTP 200 with per-connector breakdown and aggregate summary field
- SIGTERM triggers graceful shutdown: stop accepting events, drain in-flight (max 30s), exit cleanly
- End-to-end tests cover core flows plus edge cases (rate limiting, circuit breaker, reconnection, deduplication, attachments) for each provider/mode combination

