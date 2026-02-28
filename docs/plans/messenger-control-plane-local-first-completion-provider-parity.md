# Implementation Plan

Derived from discovery session 11.

# Messenger Control Plane: Local-First Gateway Runtime & Provider Adapters

## Executive Summary

This plan implements a local-first gateway runtime for the Messenger Control Plane, enabling Slack Socket Mode and Telegram long-polling without public webhook URLs, while adding Discord and WhatsApp provider adapters. The architecture uses a single PHP process with ReactPHP/Amp fibers managing isolated async workers per connector account.

---

## Phase 1: Gateway Runtime Foundation

### 1.1 Core Gateway Infrastructure

**GatewayWorkerInterface Definition**

Create the interface contract for all gateway workers:

```
app/Messenger/Gateway/Contracts/GatewayWorkerInterface.php
```

- Define `start(): void` - Initialize connection and begin receiving events
- Define `stop(): void` - Graceful shutdown, stop accepting new events
- Define `health(): WorkerHealthStatus` - Return current worker state with metadata
- Define `reconnect(): void` - Force reconnection with state preservation
- Create `WorkerHealthStatus` enum: `connected`, `reconnecting`, `disconnected`, `error`
- Include health metadata DTO with `last_event_at`, `connection_uptime`, `error_message`

**MessengerGatewayManager Service**

Create the supervisor service managing all gateway workers:

```
app/Messenger/Gateway/MessengerGatewayManager.php
```

- Inject ReactPHP EventLoop as primary async runtime
- Maintain worker registry keyed by connector_account_id
- Implement `bootWorkers(): void` - Load all local-mode connectors and spawn workers
- Implement `addWorker(ConnectorAccount $account): void` - Spawn worker for new connector
- Implement `removeWorker(int $accountId): void` - Gracefully stop and remove worker
- Implement `restartWorker(int $accountId): void` - Drain, stop, start with fresh credentials
- Implement `shutdown(): void` - SIGTERM handler with 30s drain timeout
- Add periodic health check loop (every 10s) updating `runtime_state` on connector_accounts
- Watch for credential changes via database polling (every 30s) triggering graceful restarts

**Reconnection Strategy Implementation**

Create reconnection handler with exponential backoff:

```
app/Messenger/Gateway/ReconnectionStrategy.php
```

- Initial delay: 1 second (from config)
- Exponential multiplier: 2x per attempt
- Maximum delay: 5 minutes (300 seconds from config)
- Jitter: ±20% randomization on each delay
- Reset attempt counter on successful connection lasting >60 seconds
- Log each reconnection attempt with attempt number and calculated delay

### 1.2 Database Schema Updates

**Migration: Add Runtime State to Connector Accounts**

```
database/migrations/xxxx_add_runtime_state_to_connector_accounts.php
```

- Add `runtime_state` enum column: `connected`, `reconnecting`, `disconnected`, `error`
- Add `last_health_check_at` timestamp column, nullable
- Add `runtime_error_message` text column, nullable
- Default `runtime_state` to `disconnected`
- Index on `connection_mode` + `runtime_state` for efficient queries

**Model Updates**

Update `ConnectorAccount` model:

- Add `runtime_state`, `last_health_check_at`, `runtime_error_message` to fillable
- Add `isLocalMode(): bool` accessor
- Add `isWebhookMode(): bool` accessor
- Add `updateRuntimeState(WorkerHealthStatus $status, ?string $error = null): void` method
- Add scope `scopeLocalMode($query)` for filtering local-mode connectors

### 1.3 Configuration Extensions

**config/messenger.php Updates**

Add gateway configuration section:

```php
'gateway' => [
    'shutdown_timeout' => 30,
    'health_check_interval' => 10,
    'credential_poll_interval' => 30,
    'reconnect' => [
        'initial_delay' => 1,
        'max_delay' => 300,
        'jitter_percent' => 20,
    ],
],
'circuit_breaker' => [
    'failure_threshold' => 5,
    'cooldown_seconds' => 60,
    'half_open_requests' => 3,
],
```

---

## Phase 2: Slack Socket Mode Worker

### 2.1 SlackSocketWorker Implementation

**Core Worker Class**

```
app/Messenger/Gateway/Workers/SlackSocketWorker.php
```

- Implement `GatewayWorkerInterface`
- Inject Ratchet/Pawl WebSocket client for async connections
- Store connector_account reference for credential access
- Maintain connection state machine: `disconnected` → `connecting` → `connected`

**Socket Mode Connection Flow**

- Call `apps.connections.open` API to obtain WebSocket URL (requires app-level token)
- Establish WebSocket connection to returned URL
- Handle `hello` event confirming connection
- Implement ping/pong heartbeat (Slack sends ping every 30s)
- Acknowledge events within 3 seconds to prevent redelivery

**Event Handling**

- Parse incoming `envelope_id` for acknowledgment
- Extract event payload from `payload` field
- Route events to existing `ChatIntentParser` via `ProcessChatIntent` job dispatch
- Send acknowledgment with `envelope_id` after successful job dispatch
- Handle `disconnect` events by triggering reconnection

**Token Validation**

- Validate app-level token (xapp-*) present for Socket Mode
- Distinguish from bot token (xoxb-*) used for API calls
- Store both tokens in connector_account credentials JSON

### 2.2 Slack Mode-Aware Credential Validation

**SlackCredentialValidator Service**

```
app/Messenger/Validation/SlackCredentialValidator.php
```

- For local mode: require `app_token` (xapp-*), `bot_token` (xoxb-*)
- For webhook mode: require `bot_token` (xoxb-*), `signing_secret`
- Validate token format with regex patterns
- Test token validity via `auth.test` API call
- Return structured validation result with field-specific errors

---

## Phase 3: Telegram Long-Polling Worker

### 3.1 TelegramPollingWorker Implementation

**Core Worker Class**

```
app/Messenger/Gateway/Workers/TelegramPollingWorker.php
```

- Implement `GatewayWorkerInterface`
- Use ReactPHP HTTP client for async polling requests
- Track `offset` parameter for incremental update retrieval
- Maintain polling loop with configurable timeout (default: 30s long-poll)

**Polling Loop Implementation**

- Call `getUpdates` with `offset`, `timeout`, `allowed_updates` parameters
- Process returned updates array sequentially
- Update `offset` to highest `update_id + 1` after processing
- Route each update to `ChatIntentParser` via `ProcessChatIntent` job
- Handle empty responses (timeout) by immediately re-polling
- Catch HTTP errors and trigger reconnection strategy

**Webhook Cleanup**

- On worker start, call `deleteWebhook` to ensure no webhook interference
- Log warning if webhook was previously configured
- Set `drop_pending_updates: false` to preserve any queued messages

### 3.2 Telegram Mode-Aware Credential Validation

**TelegramCredentialValidator Service**

```
app/Messenger/Validation/TelegramCredentialValidator.php
```

- For local mode: require `bot_token` only
- For webhook mode: require `bot_token`, `webhook_url`
- Validate bot token format (numeric_id:alphanumeric_secret)
- Test token via `getMe` API call
- Return bot username and id on success for display

---

## Phase 4: Discord Provider Adapter

### 4.1 DiscordAdapter Implementation

**Core Adapter Class**

```
app/Messenger/Adapters/DiscordAdapter.php
```

- Implement common messenger adapter interface
- Handle both local (Gateway) and webhook modes
- Map Discord snowflake IDs to internal MessengerIdentityLink records

**Message Sending**

- Implement `sendMessage(string $channelId, string $content, array $options): DiscordMessage`
- Support embeds, components, and file attachments
- Handle rate limit headers (X-RateLimit-*) with automatic retry
- Return message ID and timestamp for threading

**Threading Strategy**

- In guild channels: use native Discord threads via `POST /channels/{id}/threads`
- In DMs: edit previous bot message to append new content (no thread support)
- Track last bot message ID per DM channel in connector metadata
- Implement `appendToLastMessage(string $channelId, string $content): void` for DM updates

**Identity Mapping**

- Create/update MessengerIdentityLink on first interaction
- Map Discord user ID (snowflake) to link record
- Store username, discriminator, avatar hash in link metadata

### 4.2 DiscordGatewayWorker Implementation

**Core Worker Class**

```
app/Messenger/Gateway/Workers/DiscordGatewayWorker.php
```

- Implement `GatewayWorkerInterface`
- Target Gateway v10 (`wss://gateway.discord.gg/?v=10&encoding=json`)
- Implement full Gateway lifecycle: connect, identify, heartbeat, resume

**Connection Sequence**

1. Connect to Gateway URL from `GET /gateway/bot` response
2. Receive `HELLO` opcode with heartbeat_interval
3. Send `IDENTIFY` with bot token and intents
4. Receive `READY` with session_id and resume_gateway_url
5. Start heartbeat loop at received interval

**Heartbeat Management**

- Send `HEARTBEAT` (opcode 1) at interval from HELLO
- Track last heartbeat ACK; reconnect if missed
- Include sequence number in heartbeat payload

**Resume Logic**

- On disconnect, attempt resume before full reconnect
- Send `RESUME` with session_id, sequence, token
- Fall back to fresh IDENTIFY if resume fails (invalid session)
- Store resume_gateway_url for reconnection attempts

**Event Dispatch**

- Handle `MESSAGE_CREATE` events for incoming messages
- Handle `INTERACTION_CREATE` for slash commands
- Route to `ChatIntentParser` via `ProcessChatIntent` job
- Track sequence numbers for resume capability

### 4.3 Discord Webhook Controller

**Controller Implementation**

```
app/Http/Controllers/Messenger/DiscordWebhookController.php
```

- Single endpoint for Discord interaction webhook
- Implement Ed25519 signature verification per Discord spec
- Handle PING type with immediate PONG response
- Route other interactions to `ChatIntentParser`

**Signature Verification**

- Extract `X-Signature-Ed25519` and `X-Signature-Timestamp` headers
- Concatenate timestamp + raw body for verification message
- Verify against public key using sodium_crypto_sign_verify_detached
- Return 401 on verification failure

**Interaction Handling**

- Type 1 (PING): Return `{"type": 1}` immediately
- Type 2 (APPLICATION_COMMAND): Route to intent parser
- Type 3 (MESSAGE_COMPONENT): Route to intent parser
- Return deferred response (type 5) for long operations

### 4.4 Discord Slash Command Registration

**DiscordSlashCommandRegistrar Service**

```
app/Messenger/Discord/SlashCommandRegistrar.php
```

- Define command schema in configuration
- Register commands via `PUT /applications/{id}/commands` (bulk overwrite)
- Track registered command version in connector metadata
- Compare versions during `agent:install` to detect changes

**Command Definitions**

- `/agent` - Primary interaction command with subcommands
- Subcommands: `run`, `status`, `cancel`, `list`
- Include option definitions for parameters
- Set appropriate permissions (default: everyone)

**Integration with agent:install**

- After Discord connector setup, trigger command registration
- Display registered commands in install output
- Handle registration errors with actionable messages
- Store command IDs in connector metadata for reference

### 4.5 Discord Credential Validation

**DiscordCredentialValidator Service**

```
app/Messenger/Validation/DiscordCredentialValidator.php
```

- For local mode: require `bot_token`, `application_id`
- For webhook mode: require `bot_token`, `application_id`, `public_key`
- Validate bot token via `/users/@me` API call
- Return bot username and application info on success

---

## Phase 5: WhatsApp Provider Adapter

### 5.1 WhatsAppAdapter Implementation

**Core Adapter Class**

```
app/Messenger/Adapters/WhatsAppAdapter.php
```

- Implement common messenger adapter interface
- Target Cloud API v18+ endpoints
- Webhook-only mode (no local mode option)

**Message Sending**

- Implement `sendMessage(string $phoneNumber, string $content, array $options): WhatsAppMessage`
- Use `POST /v18.0/{phone_number_id}/messages` endpoint
- Support text, template, and media message types
- Handle rate limits and retry with backoff

**Threading via Quote Replies**

- Include `context.message_id` in replies to create quote chain
- Fall back to single summary message if quote chain too long (>5 messages)
- Track conversation context in connector metadata

**Template Message Handling**

- Required for outbound messages outside 24h conversation window
- Implement `sendTemplate(string $phoneNumber, string $templateName, array $components): WhatsAppMessage`
- Validate template exists before sending
- Handle template parameter substitution

**Identity Mapping**

- Map phone numbers (E.164 format) to MessengerIdentityLink
- Normalize phone number format on receipt
- Store profile name in link metadata when available

### 5.2 WhatsApp Webhook Controller

**Controller Implementation**

```
app/Http/Controllers/Messenger/WhatsAppWebhookController.php
```

- Handle webhook verification GET requests
- Handle incoming message POST requests
- Implement HMAC-SHA256 signature verification

**Webhook Verification**

- Handle GET with `hub.mode=subscribe`
- Verify `hub.verify_token` matches configured token
- Return `hub.challenge` value on success
- Return 403 on token mismatch

**Signature Verification**

- Extract `X-Hub-Signature-256` header
- Compute HMAC-SHA256 of raw body with app secret
- Compare with `sha256=` prefix stripped from header
- Return 401 on verification failure

**Message Processing**

- Parse webhook payload for message entries
- Extract sender phone number and message content
- Route to `ChatIntentParser` via `ProcessChatIntent` job
- Handle status updates (sent, delivered, read) for tracking

### 5.3 WhatsApp Credential Validation

**WhatsAppCredentialValidator Service**

```
app/Messenger/Validation/WhatsAppCredentialValidator.php
```

- Require: `access_token`, `phone_number_id`, `app_secret`, `verify_token`, `webhook_url`
- Validate access token via `/me` Graph API call
- Validate phone number ID via `/v18.0/{phone_number_id}` call
- Return phone number display name on success
- Enforce webhook mode only (no local mode validation path)

---

## Phase 6: Gateway Artisan Command

### 6.1 agent:messenger-gateway Command

**Command Implementation**

```
app/Console/Commands/MessengerGatewayCommand.php
```

- Signature: `agent:messenger-gateway`
- Description: "Run the messenger gateway supervisor for local-mode connectors"
- Designed to run alongside Horizon as separate long-lived process

**Process Lifecycle**

- On start: Initialize ReactPHP event loop
- Boot `MessengerGatewayManager` with all local-mode connectors
- Install SIGTERM handler for graceful shutdown
- Install SIGINT handler (same as SIGTERM)
- Run event loop until shutdown signal

**Console Output**

- Log worker start/stop events to console
- Display active worker count periodically
- Show reconnection attempts with backoff timing
- Output health status on SIGUSR1 (debug signal)

**Graceful Shutdown**

- On SIGTERM: log shutdown initiation
- Call `MessengerGatewayManager::shutdown()`
- Wait for drain (max 30s from config)
- Log completion and exit with code 0
- Exit with code 1 if drain timeout exceeded

### 6.2 Process Supervision Configuration

**Supervisor Config Example**

```
config/messenger-gateway.conf.example
```

- Document supervisor configuration for production
- Set `autorestart=true` for automatic recovery
- Set `stopwaitsecs=35` (slightly above drain timeout)
- Set `stopsignal=TERM` for graceful shutdown
- Include log rotation configuration

---

## Phase 7: Mode-Aware Install Flow

### 7.1 agent:install Enhancements

**Mode Selection UI**

Update connector setup flow in `agent:install`:

- Display available modes per provider
- Slack: Local (Socket Mode) | Webhook
- Telegram: Local (Long Polling) | Webhook
- Discord: Local (Gateway) | Webhook
- WhatsApp: Webhook only (show as disabled/unavailable for local)
- Store selected mode in connector_account `connection_mode` field

**Mode-Specific Credential Prompts**

- Invoke appropriate credential validator based on provider + mode
- Show only relevant credential prompts per mode
- For local modes: skip webhook URL configuration entirely
- For webhook modes: require and validate webhook URL

**Credential Validation Feedback**

- Test credentials immediately after entry
- Display success with account/bot info (username, phone number)
- Display clear error messages for invalid credentials
- Allow retry without restarting entire install flow

### 7.2 Webhook Ingress Validation

**IngressProbe Service**

```
app/Messenger/Validation/IngressProbe.php
```

- Validate webhook URL accessibility and TLS
- Provider-specific verification challenge execution

**Basic Reachability Checks**

- HTTP HEAD request to webhook URL
- Verify 2xx or expected response code
- Check TLS certificate validity (not expired)
- Warn if certificate expires within 7 days
- Verify certificate chain completeness

**Provider-Specific Verification**

Slack URL Verification:
- POST challenge request to webhook URL
- Verify response contains echoed challenge
- Timeout: 3 seconds (Slack's requirement)

Telegram setWebhook:
- Call `setWebhook` API with URL
- Verify success response
- Call `getWebhookInfo` to confirm URL set

Discord PING/PONG:
- POST PING interaction to webhook URL
- Verify PONG response with correct signature
- Note: requires public key for signature generation

WhatsApp Verification:
- Note: verification happens on Meta's side
- Validate verify_token will match configured value
- Provide URL and token for user to configure in Meta dashboard

**Actionable Diagnostics**

- On TLS error: "Certificate invalid. Renew at [issuer] or check your reverse proxy configuration."
- On timeout: "Webhook URL not reachable. Verify firewall rules and that the URL is publicly accessible."
- On verification failure: "Provider verification failed. Check [specific setting] in your [provider] app configuration."

---

## Phase 8: Circuit Breaker & Rate Limiting

### 8.1 Circuit Breaker Implementation

**CircuitBreaker Service**

```
app/Messenger/Reliability/CircuitBreaker.php
```

- Per-connector instance (keyed by connector_account_id)
- States: `closed` (normal), `open` (failing), `half_open` (testing)

**State Transitions**

- Closed → Open: After 5 consecutive failures (configurable)
- Open → Half-Open: After 60 second cooldown (configurable)
- Half-Open → Closed: After 3 successful requests (configurable)
- Half-Open → Open: On any failure during half-open

**Integration with MessengerHttpClient**

- Check circuit state before outbound request
- Throw `CircuitOpenException` if circuit is open
- Record success/failure after request completion
- Log state transitions with connector context

**Circuit State Persistence**

- Store circuit state in cache (Redis) with connector key
- Include failure count, last failure time, state
- TTL: 10 minutes (auto-reset if no activity)

### 8.2 Rate Limiter Wiring

**Existing Rate Limiter Verification**

- Confirm per-connector rate limits enforced in MessengerHttpClient
- Verify limits read from `config/messenger.php`
- Add rate limit metrics emission for monitoring

**Rate Limit Configuration**

```php
'rate_limits' => [
    'slack' => ['requests_per_second' => 1, 'burst_limit' => 5],
    'telegram' => ['requests_per_second' => 30, 'burst_limit' => 30],
    'discord' => ['requests_per_second' => 50, 'burst_limit' => 100],
    'whatsapp' => ['requests_per_second' => 80, 'burst_limit' => 200],
],
```

---

## Phase 9: Dead-Letter Queue

### 9.1 Dead-Letter Queue Implementation

**DeadLetterManager Service**

```
app/Messenger/Reliability/DeadLetterManager.php
```

- Move messages to DLQ after retry duration exceeded
- Store original message, connector context, error history
- Provide retrieval and retry interfaces

**Database Schema**

```
database/migrations/xxxx_create_messenger_dead_letters_table.php
```

- `id` - Primary key
- `connector_account_id` - Foreign key
- `original_payload` - JSON of original message
- `error_message` - Last error encountered
- `error_history` - JSON array of all errors with timestamps
- `attempts` - Number of delivery attempts
- `failed_at` - When moved to DLQ
- `retried_at` - When last retry attempted (nullable)
- `created_at`, `updated_at` - Timestamps

**Retry Mechanism**

- `retry(int $deadLetterId): bool` - Attempt single message retry
- `retryBulk(array $deadLetterIds): array` - Batch retry with results
- On successful retry: delete from DLQ
- On failed retry: update error_history, increment attempts

### 9.2 Dead-Letter Dashboard

**Route Registration**

```
routes/web.php addition
```

- `GET /messenger/dead-letters` - List dead letters with pagination
- `POST /messenger/dead-letters/{id}/retry` - Retry single message
- `POST /messenger/dead-letters/retry-bulk` - Bulk retry selected messages
- Apply appropriate auth middleware

**DeadLetterController**

```
app/Http/Controllers/Messenger/DeadLetterController.php
```

- `index()` - Paginated list with connector filter
- `show(int $id)` - Full details including error history
- `retry(int $id)` - Single retry with redirect and flash message
- `retryBulk(Request $request)` - Bulk retry with JSON response

**View Implementation**

```
resources/views/messenger/dead-letters/index.blade.php
```

- Table: connector name, message preview, error, failed_at, attempts
- Filters: connector dropdown, date range
- Bulk actions: select multiple, retry selected
- Individual actions: view details, retry
- Navigation: Add to messenger settings sidebar

**In-App Discoverability**

- Add "Failed Messages" link to Messenger settings navigation
- Show badge count of unretried dead letters
- Link from connector detail page to filtered dead letter view

---

## Phase 10: Observability & Health

### 10.1 Correlation ID Logging

**CorrelationContext Service**

```
app/Messenger/Observability/CorrelationContext.php
```

- Generate UUID for each inbound message
- Store in thread-local context (Laravel context)
- Reset on each new inbound message
- Provide `getCorrelationId(): string` accessor

**Log Integration**

- Add correlation_id to all messenger log entries
- Configure in logging channel tap
- Include in structured log format: `[correlation_id] [level] message {context}`

**Correlation Scope**

- Start: Webhook receipt or gateway event receipt
- End: Outbound response sent or job completed
- Include: Parse, execute, all outbound API calls
- New ID for each independent inbound message

### 10.2 MessengerHealthController Updates

**Health Response Format**

```json
{
  "status": "healthy",
  "summary": {
    "total_connectors": 5,
    "connected": 4,
    "degraded": 1,
    "disconnected": 0
  },
  "connectors": [
    {
      "id": 1,
      "provider": "slack",
      "mode": "local",
      "runtime_state": "connected",
      "last_health_check_at": "2024-01-15T10:30:00Z",
      "uptime_seconds": 3600
    }
  ]
}
```

**Implementation Updates**

- Query connector_accounts for runtime_state, last_health_check_at
- For local-mode: report state from database (updated by gateway manager)
- For webhook-mode: report based on recent activity and circuit state
- Always return HTTP 200 (healthy/degraded status in body)
- Include aggregate summary for monitoring tool parsing

**Route Exposure**

- Existing route: `GET /messenger/health`
- Ensure no auth required for monitoring tools
- Add rate limiting to prevent abuse

### 10.3 Queue Health Alignment

**Horizon Queue Verification**

- Audit actual queue names used in ProcessChatIntent and other messenger jobs
- Update MessengerMetricsController to query correct Horizon queues
- Include queue depth, job throughput, failure rate per queue

**Metrics Endpoint**

- `GET /messenger/metrics` - Prometheus-compatible metrics
- Include: `messenger_queue_depth`, `messenger_jobs_processed_total`, `messenger_job_failures_total`
- Label by queue name, provider, job type

---

## Phase 11: Messenger Settings UI Updates

### 11.1 Mode Display in Connector List

**ConnectorAccount Index View Updates**

```
resources/views/messenger/connectors/index.blade.php
```

- Add "Mode" column showing Local or Webhook
- Add "Status" column showing runtime_state with color indicator
- Connected: green dot
- Reconnecting: yellow dot with spinner
- Disconnected: gray dot
- Error: red dot with hover for error message

**Status Refresh**

- Poll status endpoint every 30 seconds via JavaScript
- Update status indicators without full page reload
- Show last updated timestamp

### 11.2 Connector Detail View

**ConnectorAccount Show View Updates**

```
resources/views/messenger/connectors/show.blade.php
```

- Display connection mode prominently
- Show runtime_state with detailed status
- Display last_health_check_at timestamp
- Show error message if in error state
- Link to dead letters filtered by this connector

**Mode Switching UI**

- Display current mode
- Show "Change Mode" button if provider supports both modes
- Modal confirmation explaining drain behavior
- Progress indicator during mode switch
- Note: WhatsApp hides mode switching (webhook only)

### 11.3 Navigation Updates

**Messenger Settings Sidebar**

- Connectors (existing)
- Failed Messages (new - links to dead letter dashboard)
- Health (new - links to health overview page)

**Health Overview Page**

```
resources/views/messenger/health/index.blade.php
```

- Dashboard showing all connectors with status
- Gateway process status (if local-mode connectors exist)
- Queue health metrics
- Recent errors summary
- Route: `GET /messenger/health/dashboard`

---

## Phase 12: Testing

### 12.1 Unit Tests

**Gateway Worker Tests**

```
tests/Unit/Messenger/Gateway/
```

- `SlackSocketWorkerTest.php` - Mock WebSocket, verify event handling
- `TelegramPollingWorkerTest.php` - Mock HTTP client, verify polling loop
- `DiscordGatewayWorkerTest.php` - Mock WebSocket, verify Gateway protocol
- `MessengerGatewayManagerTest.php` - Verify worker lifecycle management
- `ReconnectionStrategyTest.php` - Verify backoff calculations with jitter

**Adapter Tests**

```
tests/Unit/Messenger/Adapters/
```

- `DiscordAdapterTest.php` - Message sending, threading, identity mapping
- `WhatsAppAdapterTest.php` - Message sending, templates, identity mapping

**Reliability Tests**

```
tests/Unit/Messenger/Reliability/
```

- `CircuitBreakerTest.php` - State transitions, threshold behavior
- `DeadLetterManagerTest.php` - DLQ operations, retry logic

### 12.2 Integration Tests

**Webhook Controller Tests**

```
tests/Feature/Messenger/Webhooks/
```

- `DiscordWebhookTest.php` - Ed25519 verification, PING/PONG, interaction routing
- `WhatsAppWebhookTest.php` - HMAC-SHA256 verification, message routing

**End-to-End Flow Tests**

```
tests/Feature/Messenger/Flows/
```

Per provider and mode combination:
- Inbound message → parse → execute → outbound response
- Deduplication (same message_id rejected)
- Attachment handling (images, files)
- Rate limiting (verify throttling)
- Circuit breaker (trips after failures, recovers)

**Gateway Integration Tests**

```
tests/Feature/Messenger/Gateway/
```

- `GatewayManagerIntegrationTest.php` - Worker spawn, health updates, shutdown
- Mock external WebSocket/HTTP endpoints
- Verify database state updates

### 12.3 Credential Validation Tests

```
tests/Feature/Messenger/Validation/
```

- `SlackCredentialValidatorTest.php` - Both modes, error cases
- `TelegramCredentialValidatorTest.php` - Both modes, error cases
- `DiscordCredentialValidatorTest.php` - Both modes, error cases
- `WhatsAppCredentialValidatorTest.php` - Webhook mode only, error cases

### 12.4 Install Flow Tests

```
tests/Feature/Console/
```

- `MessengerInstallTest.php` - Mode selection, credential flow, validation feedback
- Mock API calls for credential testing
- Verify correct prompts per mode
- Verify webhook probes execute correctly

---

## Dependency Order

1. **Phase 1** (Gateway Foundation) - No dependencies, foundational
2. **Phase 2** (Slack Worker) - Requires Phase 1 interfaces
3. **Phase 3** (Telegram Worker) - Requires Phase 1 interfaces
4. **Phase 4** (Discord Adapter) - Requires Phase 1 interfaces
5. **Phase 5** (WhatsApp Adapter) - Independent of gateway workers
6. **Phase 6** (Gateway Command) - Requires Phases 1-4 workers
7. **Phase 7** (Install Flow) - Requires Phase 2-5 validators
8. **Phase 8** (Circuit Breaker) - Independent, enhances existing
9. **Phase 9** (Dead-Letter Queue) - Independent, enhances existing
10. **Phase 10** (Observability) - Requires Phase 1 for health integration
11. **Phase 11** (UI Updates) - Requires Phases 1, 9 for data sources
12. **Phase 12** (Testing) - Parallel with implementation phases

Phases 2, 3, 4, 5 can proceed in parallel after Phase 1.
Phases 8, 9 can proceed in parallel with other phases.
Phase 12 tests should be written alongside their respective implementation phases.

## Sections

- Phase 1: Gateway Runtime Foundation
- Phase 2: Slack Socket Mode Worker
- Phase 3: Telegram Long-Polling Worker
- Phase 4: Discord Provider Adapter
- Phase 5: WhatsApp Provider Adapter
- Phase 6: Gateway Artisan Command
- Phase 7: Mode-Aware Install Flow
- Phase 8: Circuit Breaker & Rate Limiting
- Phase 9: Dead-Letter Queue
- Phase 10: Observability & Health
- Phase 11: Messenger Settings UI Updates
- Phase 12: Testing


## Risks

- ReactPHP/Amp long-running process stability in production requires thorough memory leak testing and monitoring
- Discord Gateway connection drops during rate limiting may require additional reconnection edge case handling
- WhatsApp Cloud API rate limits are strict; high-volume deployments may need multiple phone numbers
- Slack Socket Mode requires Enterprise Grid or specific app configurations that some users may not have
- Ed25519 signature verification for Discord requires sodium extension which may not be installed in all environments
- Mode switching drain behavior may cause user-perceived delays if many messages are in-flight
- Dead-letter queue could grow unbounded if underlying issue is not resolved; needs alerting and cleanup policy
- Credential rotation detection via polling (every 30s) creates a window where old credentials may be used
- Telegram long-polling blocks a connection per connector; many Telegram connectors increase connection count
- Gateway supervisor crash loses all local-mode connectivity until process restart; requires robust process supervision


## Assumptions

- ReactPHP is acceptable as the async runtime (alternative: Amp); both support PHP 8.1+ fibers
- The existing ChatIntentParser and ChatActionExecutor pipelines are stable and require no modifications
- Horizon is already configured and running; gateway process will be added as a separate supervised process
- Existing connector_accounts table structure allows adding new columns without migration conflicts
- Discord bot already exists in Discord Developer Portal; command registration uses existing bot credentials
- WhatsApp Business API access is already provisioned with Meta; Cloud API v18+ is accessible
- Redis is available for circuit breaker state storage (consistent with existing cache usage)
- The messenger settings UI uses Blade templates (not a SPA framework)
- Existing MessengerHttpClient is the single point for outbound API calls across all providers
- The test suite uses Pest/PHPUnit with feature and unit test separation already established

