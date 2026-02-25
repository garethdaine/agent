# Requirements Discovery Summary

Session: 1

# Messenger Control Plane — Technical Specification

## Overview

The Messenger Control Plane provides a unified AI chat interface for controlling local Agent installations through supported messenger platforms (Slack, Discord, Telegram, WhatsApp). Users can create/update cron jobs from natural language, observe and control active runs, and spawn new agent tasks—all through chat commands. The system runs entirely on user infrastructure with no cloud dependency for core orchestration.

## Architecture

### Connector Adapters

Four messenger adapters implementing a common interface:

| Provider | Inbound Mode | Default Mode | Local Mode Available | Threading | Fallback Behavior |
|----------|-------------|--------------|---------------------|-----------|-------------------|
| Slack | WebSocket (Socket Mode) or Webhook | Local (Socket Mode) | Yes | Native threads | Edit original message |
| Telegram | Long Polling or Webhook | Local (Long Polling) | Yes | Reply-to threads | Quote reply to original |
| Discord | Gateway WebSocket or Webhook | Local (Gateway) | Yes | Native threads | Edit original message |
| WhatsApp | Webhook only (Cloud API) | Webhook (required) | No | Quote replies | Single summary message |

**Default Mode:** Local connector mode (long polling/websockets) where supported to avoid public exposure. WhatsApp requires webhook mode. Public webhook mode available for all providers via `agent:install` configuration.

**Phase A Scope:** Slack and Telegram adapters only. Discord and WhatsApp adapters are Phase B deliverables.

### Multi-Bot Support

The system supports multiple concurrent bot connections per provider type. A single Agent instance can connect to multiple Slack workspaces, multiple Telegram bots, etc.

### Data Model Relationship

The existing `providers` table is **not extended**. A new `connector_accounts` table serves as the dedicated source of truth for messenger connections, with its own lifecycle separate from the generic providers infrastructure.

### Account-Link Flow (Identity Mapping)

Identity linking uses an **account-link flow** (not OAuth in the traditional sense for all providers):

1. User sends first message to bot from messenger
2. Bot replies with Agent auth link (signed, one-time-use token in URL)
3. User clicks link → `GET /messenger-link/{token}` validates token and renders login form
4. User authenticates with Agent (standard Agent login via web session)
5. User submits confirmation → `POST /messenger-link/{token}/complete` creates identity link
6. Token is invalidated immediately after POST consumption
7. Bot confirms link completion in messenger

**Account-Link Token Security Requirements:**
- **One-time use:** Token invalidated immediately upon POST consumption (success or failure)
- **Signed:** HMAC-SHA256 signature with server secret, verified before processing
- **TTL:** 15-minute expiration from generation
- **Replay prevention:** Token stored in Redis (primary) with TTL; atomic check-and-delete on POST
- **Explicit invalidation:** Token record deleted from storage after link completion
- **Payload:** `{connector_account_id, provider_user_id, issued_at, signature}`
- **GET is read-only:** GET validates token and renders UI but does NOT complete the link mutation

**Token Storage Strategy:**
- **Primary:** Redis with 15-minute TTL (`account_link:{token_hash}`)
- **DB Fallback:** If Redis unavailable at token creation, store in `account_link_tokens` table with `expires_at` and scheduled cleanup job
- **DB Consumption:** Atomic single-statement update with `WHERE consumed_at IS NULL` guard to prevent race-condition double use
- **Verification:** Always check Redis first; if miss and DB fallback enabled, check DB table
- **Cleanup:** Scheduled job purges expired DB tokens hourly

**Provider-specific identity proof:**
- **Slack:** Workspace user ID from verified event payload
- **Telegram:** `from.id` from Update object (bot API guarantees authenticity)
- **Discord:** User ID from gateway event or interaction payload
- **WhatsApp:** Phone number from verified webhook payload

**Unlinked Users:** Send onboarding prompt with auth link and instructions; no actions processed until linked

**Link Expiration:** Configurable per provider—admins set expiration policy independently for each messenger

### Account-Link Route Classification

The account-link endpoints are **web routes** (not API routes):
- `GET /messenger-link/{token}` — Validates token, renders login/confirmation form (read-only, no state mutation)
- `POST /messenger-link/{token}/complete` — Completes link mutation after authentication
- Registered in `routes/web.php`
- POST uses `auth` middleware (redirects to login if unauthenticated)
- On POST success: invalidates token, creates identity link, renders confirmation view
- On failure: renders error view with appropriate message

### Chat Session Management

- **Scope:** Per-thread—each messenger thread is a separate session with isolated context
- **History Retention:** Configurable limit—admin sets message count or time window per deployment

## Attachments (Phase A Deliverable — Slack + Telegram Only)

Full bidirectional attachment support is a Phase A requirement for Slack and Telegram. Discord and WhatsApp attachment support deferred to Phase B with their respective adapters.

### Inbound Attachments
- Download and store files from messenger to local disk or S3
- **Storage Limits:** Configurable max file size per provider (default: 10MB)
- **Allowed Types:** Configurable allowlist (default: images, PDFs, text files)
- **Malware Scanning:** Integrate with ClamAV or configurable scanner before storage
- **Retention:** Configurable retention period (default: 30 days), auto-purge after expiry

### Outbound Attachments
- Send images/files as part of bot responses (run output, screenshots, logs)
- Respect provider-specific size limits and format requirements

### Attachment Storage and Security

**Storage approach by backend:**

- **Local disk:** Store in non-public directory with filesystem access controls; rely on OS/disk-level encryption if encryption-at-rest is required; access only via signed URL endpoint
- **S3/compatible:** Enable server-side encryption (SSE-S3 or SSE-KMS); use presigned URLs for access
- **No application-level encryption:** Avoids performance overhead for large files

**Access Control:**
- File retrieval endpoints require authenticated user + ownership/permission check
- No raw URLs in logs: Chat message logs store only attachment IDs, never direct storage paths
- Signed URLs: Temporary presigned URLs (15-minute expiry) generated on-demand for authorized access

### Storage Schema Addition
```php
'attachment_config' => [
    'max_file_size_mb' => 10,
    'allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
    'malware_scan_enabled' => true,
    'retention_days' => 30,
    'storage_disk' => 'local', // or 's3'
    's3_encryption' => 'AES256', // SSE-S3, or 'aws:kms' for SSE-KMS
    'signed_url_ttl_minutes' => 15,
]
```

## Data Model

### New Tables

**`connector_accounts`** (primary source of truth for messenger connections)
- `id` (UUID, PK)
- `provider` (enum: slack, telegram, discord, whatsapp)
- `name` (string—workspace/bot display name)
- `credentials` (encrypted JSON—tokens, secrets)
- `webhook_secret` (string, nullable)
- `connection_mode` (enum: local, webhook)
- `status` (enum: connected, disconnected, error)
- `config` (JSON—provider-specific settings, see schema below)
- `created_at`, `updated_at`

**`chat_sessions`**
- `id` (UUID, PK)
- `user_id` (FK to users)
- `connector_account_id` (FK to connector_accounts)
- `provider` (enum: slack, telegram, discord, whatsapp)
- `channel_id` (provider-specific)
- `thread_id` (provider-specific, nullable)
- `status` (enum: active, archived)
- `created_at`, `updated_at`

**`chat_messages`**
- `id` (UUID, PK)
- `chat_session_id` (FK)
- `connector_account_id` (FK to connector_accounts)
- `direction` (enum: inbound, outbound)
- `content` (text)
- `attachment_ids` (JSON array of UUIDs—references chat_attachments, never raw URLs)
- `idempotency_key` (string, NOT NULL, for deduplication)
- `provider_event_id` (string, nullable—raw provider ID when available)
- `provider_message_id` (string, nullable—for editing/threading)
- `provider_timestamp` (datetime)
- `created_at`
- **UNIQUE constraint:** `(connector_account_id, idempotency_key)`

**Idempotency Key Generation:**
When `provider_event_id` is available, use it directly. When absent, generate deterministic key with collision-resistant components:

```
if provider_message_id available:
    idempotency_key = sha256(
        provider + ":" +
        connector_account_id + ":" +
        provider_message_id
    )
else:
    idempotency_key = sha256(
        provider + ":" +
        connector_account_id + ":" +
        channel_id + ":" +
        sender_id + ":" +
        provider_timestamp_unix_milliseconds + ":" +
        sha256(content + json_encode(attachment_ids))
    )
```

This formula ensures:
- Provider message ID used when available (most reliable)
- Full content + attachment hash prevents collision for identical messages
- Millisecond timestamp precision reduces collision window
- Deterministic regeneration for duplicate detection

**`chat_actions`**
- `id` (UUID, PK)
- `chat_message_id` (FK)
- `action_type` (enum: jobs.create, jobs.update, jobs.delete, jobs.list, runs.list_active, runs.stop, runs.retry, runs.run_now, runs.steer)
- `parameters` (JSON—structured action payload)
- `status` (enum: pending, executing, completed, failed)
- `result` (JSON, nullable)
- `error` (text, nullable)
- `requires_confirmation` (boolean)
- `confirmed_at` (datetime, nullable)
- `created_at`, `executed_at`

**`messenger_identity_links`**
- `id` (UUID, PK)
- `user_id` (FK to users)
- `connector_account_id` (FK to connector_accounts)
- `provider_user_id` (string)
- `provider_username` (string, nullable)
- `expires_at` (datetime, nullable)
- `created_at`, `updated_at`
- **UNIQUE constraint:** `(connector_account_id, provider_user_id)`

**`chat_attachments`**
- `id` (UUID, PK)
- `chat_message_id` (FK)
- `filename` (string)
- `mime_type` (string)
- `size_bytes` (integer)
- `storage_path` (string—relative path, not public URL)
- `provider_file_id` (string, nullable)
- `scan_status` (enum: pending, clean, infected, skipped)
- `expires_at` (datetime)
- `created_at`

**`account_link_tokens`** (DB fallback when Redis unavailable)
- `token_hash` (string, PK—SHA256 of signed token)
- `connector_account_id` (UUID)
- `provider_user_id` (string)
- `issued_at` (datetime)
- `expires_at` (datetime)
- `consumed_at` (datetime, nullable)
- Index on `expires_at` for cleanup job

**DB Token Consumption (atomic):**
```sql
UPDATE account_link_tokens 
SET consumed_at = NOW() 
WHERE token_hash = ? 
  AND consumed_at IS NULL 
  AND expires_at > NOW()
```
Check `affected_rows == 1` to confirm successful consumption; if 0, token was already consumed or expired.

### Provider Configuration Schema

```php
'connector_config' => [
    // Confirmation behavior
    'confirmation_required' => true,  // per-provider confirmation for destructive actions
    
    // Identity linking
    'link_expiration_days' => null,   // null = permanent, integer = days until re-auth
    
    // Signature verification (separate from replay protection)
    'signature_verification' => [
        'scheme' => 'hmac_sha256',     // 'hmac_sha256' (Slack/WhatsApp), 'ed25519' (Discord), 'token' (Telegram)
        'signing_secret' => '...',     // for HMAC schemes
        'public_key' => '...',         // for Ed25519 (Discord)
    ],
    
    // Replay protection (separate from signature verification)
    'replay_protection' => [
        'strategy' => 'timestamp',     // 'timestamp' (Slack/Discord), 'event_id_dedupe' (Telegram/WhatsApp)
        'window_seconds' => 300,       // for timestamp strategy (default 300s for all providers)
        'dedupe_ttl_seconds' => 3600,  // for event_id_dedupe strategy
    ],
    
    // Retry and rate limit behavior
    'retry_duration_hours' => 1,      // max retry before dead-letter
    'rate_limit' => [
        'requests_per_second' => 1,   // outbound rate limit
        'burst_limit' => 5,           // max burst before throttling
        'backoff_base_seconds' => 1,  // initial backoff on 429
        'backoff_max_seconds' => 300, // max backoff ceiling
        'jitter_percent' => 20,       // randomization factor
    ],
    
    // Session context
    'session_history_limit' => 20,    // messages retained for AI context
    'session_history_window' => null, // alternative: time-based (e.g., "24h")
    
    // Output verbosity
    'default_verbosity' => 'summary', // run output: full, summary, errors_only
    
    // Threading behavior (see capability matrix)
    'threading_mode' => 'native',     // 'native', 'quote', 'edit', 'top_level'
    'threading_fallback' => 'edit',   // fallback when primary unavailable
    
    // Channel restrictions
    'channel_restrictions' => [],     // channel IDs restricted to read-only
    
    // Attachments
    'max_file_size_mb' => 10,
    'allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
]
```

### Provider Threading Capability Matrix

| Provider | Primary Mode | DM Behavior | Group Behavior | Fallback Chain |
|----------|-------------|-------------|----------------|----------------|
| Slack | Native thread | Thread under message | Thread under message | edit → single |
| Telegram | Reply-to | Reply-to original | Reply-to original | quote → single |
| Discord | Native thread | Cannot thread in DM | Thread in channels | edit → single |
| WhatsApp | Quote reply | Quote original | Quote original | single |

### Provider Security Configuration Matrix

| Provider | Signature Scheme | Replay Strategy | Default Window/TTL |
|----------|------------------|-----------------|-------------------|
| Slack | HMAC-SHA256 (`X-Slack-Request-Timestamp` + body) | Timestamp | 300 seconds |
| Telegram | Token-based (bot token in URL) | Event ID dedupe (`update_id`) | 3600 seconds TTL |
| Discord | Ed25519 (`X-Signature-Ed25519` + `X-Signature-Timestamp`) | Timestamp | 300 seconds |
| WhatsApp | HMAC-SHA256 (app secret) | Event ID dedupe (`messages[].id`) | 3600 seconds TTL |

**Discord Webhook Verification Detail:**
- Extract `X-Signature-Ed25519` header (hex-encoded signature)
- Extract `X-Signature-Timestamp` header
- Concatenate: `timestamp + request_body`
- Verify Ed25519 signature using Discord application public key
- Reject if timestamp older than configured window (default 300 seconds)

## Chat Action Orchestration

### Processing Model

- **Simple Queries (sync):** `jobs.list`, `runs.list_active`—parse and respond in single round-trip
- **Mutations (async):** `jobs.create`, `jobs.update`, `jobs.delete`, `runs.stop`, `runs.retry`, `runs.run_now`, `runs.steer`—immediate ack, queue processing, thread replies for progress

### Progress Updates

- **Primary:** Thread replies under original message (or provider-appropriate equivalent)
- **Fallback:** Per-provider behavior per capability matrix above

### Structured Action Output

All AI-parsed intents produce validated JSON schemas, never raw commands:

```json
{
  "action": "runs.retry",
  "parameters": {
    "run_id": "uuid-here",
    "with_modifications": false
  },
  "confidence": 0.95,
  "requires_confirmation": false
}
```

### Confirmation Behavior

Configurable per provider. When enabled, **destructive actions** prompt user for explicit confirmation before execution.

**Destructive actions requiring confirmation (when enabled):**
- `runs.stop` — terminates running process
- `jobs.update` — modifies existing job configuration
- `jobs.delete` — removes job permanently

**Non-destructive mutations (no confirmation required):**
- `runs.retry` — re-executes a failed/stopped run (additive, original preserved)
- `runs.run_now` — triggers immediate execution of existing job
- `runs.steer` — provides guidance to running process
- `jobs.create` — creates new job (additive)

## Authorization Model

### Identity + Channel Hybrid

1. User permissions are primary—derived from linked Agent user account
2. Admins can optionally restrict specific channels to read-only via `channel_restrictions` config
3. Per-action authorization checks via existing `CommandPolicy`, `PathPolicy`, `EnvPolicy` equivalents

### Audit Trail

Every chat-triggered mutation emits audit records containing:
- Actor (messenger user ID + linked Agent user ID)
- Channel/thread context
- Action type and parameters
- Execution result
- Correlation ID for tracing

## Security

### Webhook Verification

- Signature verification required for all providers when in webhook mode
- Signature scheme and replay protection are separate concerns (see Provider Security Configuration Matrix)
- Idempotency enforced via unique constraint on `(connector_account_id, idempotency_key)` with fallback key generation

### Input Safety

- No raw command templates from chat
- All payloads validated against policy constraints before execution
- Structured JSON schema enforcement

### Attachment Security

- Malware scanning before storage (ClamAV integration or configurable scanner)
- File type allowlist enforcement
- Size limits per provider configuration
- S3: server-side encryption (SSE-S3 or SSE-KMS)
- Local: filesystem access controls; OS/disk-level encryption if required
- Access-controlled retrieval with ownership verification
- Presigned URLs with short TTL for authorized access
- No raw file URLs stored in chat message logs
- Automatic expiration and purge

## Reliability

### Queue-Backed Processing

All webhook callbacks and outbound messages processed via Horizon queues.

### Provider Rate Limit Handling

Each provider adapter implements rate limit handling:
- **429 Response Handling:** Detect rate limit responses, extract `Retry-After` header when available
- **Exponential Backoff:** Base delay with configurable multiplier (default: 1s base, 2x multiplier)
- **Jitter:** Add randomization (default: ±20%) to prevent thundering herd
- **Max Backoff:** Ceiling on retry delay (default: 300 seconds)
- **Queue Throttling:** Per-connector rate limiter on outbound queue (configurable requests/second)
- **Circuit Breaker:** After N consecutive failures, pause outbound for cooldown period

```php
'rate_limit' => [
    'requests_per_second' => 1,
    'burst_limit' => 5,
    'backoff_base_seconds' => 1,
    'backoff_multiplier' => 2,
    'backoff_max_seconds' => 300,
    'jitter_percent' => 20,
    'circuit_breaker_threshold' => 10,
    'circuit_breaker_cooldown_seconds' => 60,
]
```

### Degradation Handling

- **Strategy:** Queue and retry with exponential backoff + jitter
- **Retry Duration:** Configurable per provider (admin sets based on SLAs)
- **Dead-Letter:** Messages exceeding retry duration moved to dead-letter queue with admin notification

### Connector Independence

System remains operational if one provider is degraded—other connectors continue functioning.

## CLI Commands

### `agent:install`

Hybrid mode: use flags/config when provided, fall back to interactive prompts for missing values.

**Responsibilities:**
1. Preflight checks (PHP/Node/Redis/DB/network/DNS/TLS)
2. Configure selected connector providers
3. Configure ingress profile (local connector vs public webhook)
4. Create/update runtime scripts and config
5. Run health checks and print status

**Key Flags:**
- `--connector=slack,telegram` (providers to configure)
- `--mode=local|webhook` (default: local; WhatsApp forces webhook)
- `--non-interactive` (fail on missing required values)
- `--config=/path/to/config.yaml`

### `agent:restart`

Graceful restart of local runtime stack.

**Managed Services:**
- `php artisan horizon`
- `php artisan reverb:start`
- `php artisan schedule:work`
- `php artisan serve` (configurable—excluded by default in production)
- `npm run dev` (when local-dev mode enabled)

**Configuration:**
```php
'agent_restart' => [
    'include_web_server' => env('AGENT_RESTART_WEB_SERVER', false),
    'include_npm_dev' => env('AGENT_RESTART_NPM_DEV', false),
]
```

## Routes and Endpoints

### Webhook Endpoints (API Routes)

- `POST /agent/api/v1/connectors/slack/webhook`
- `POST /agent/api/v1/connectors/telegram/webhook`
- `POST /agent/api/v1/connectors/discord/webhook`
- `POST /agent/api/v1/connectors/whatsapp/webhook`

### Account-Link Endpoints (Web Routes)

These are **web routes** using Laravel's `web` middleware stack (session, CSRF, etc.):

- `GET /messenger-link/{token}` — Validates token, renders login/confirmation form (read-only)
- `POST /messenger-link/{token}/complete` — Completes link mutation (state-changing)
- Registered in `routes/web.php`
- POST uses `auth` middleware (redirects to login if unauthenticated)
- GET only validates and renders UI; POST performs the actual link creation
- On POST success: invalidates token, creates identity link, renders confirmation view
- On failure: renders error view with appropriate message

### Chat API (API Routes)

- `GET /agent/api/v1/chat/sessions` — list user's sessions
- `GET /agent/api/v1/chat/sessions/{id}/messages` — session message history
- `GET /agent/api/v1/chat/actions/{id}` — action execution status
- `GET /agent/api/v1/chat/runs/{id}/stream` — SSE stream for run updates

### Attachment API (API Routes)

- `GET /agent/api/v1/chat/attachments/{id}` — Get presigned URL for attachment (requires auth + ownership check)

### Connector Management (API Routes)

- `GET /agent/api/v1/connectors` — list configured connectors
- `POST /agent/api/v1/connectors` — add connector account
- `DELETE /agent/api/v1/connectors/{id}` — remove connector
- `POST /agent/api/v1/connectors/{id}/test` — test connectivity

## Observability

### Structured Logging

Correlation IDs across: webhook receipt → chat action parsing → run/job mutation → response delivery

### Metrics

- Inbound message rate (per provider)
- Action success/failure rate (per action type)
- Median/P95 action latency
- Webhook verification failures
- Queue depth and dead-letter counts
- Attachment scan results (clean/infected/skipped)
- Rate limit events (429 responses, backoff triggers)
- Circuit breaker state changes

## Run Output Streaming

### Verbosity Levels

- **full:** Stream all output in real-time
- **summary:** Start/completion messages with result summary
- **errors_only:** Errors and key milestones only

Default verbosity configurable per deployment; users can override per run via chat command.

## Goals

- Provide unified AI chat control surface across Slack, Discord, Telegram, and WhatsApp messenger platforms
- Enable natural language creation and management of agent cron jobs with policy validation
- Allow observation and control of active runs (list, stop, retry, run-now, steer) through chat commands
- Implement secure account-link flow with signed one-time-use tokens (Redis primary, DB fallback with atomic consumption) and POST-only state mutation
- Support multiple concurrent bot connections per messenger provider (multi-workspace/multi-bot)
- Default to local connector mode (long polling for Telegram, Socket Mode for Slack) where provider supports it
- Process simple queries synchronously and mutations asynchronously with thread-based progress updates
- Support full bidirectional attachments for Slack and Telegram in Phase A with S3 server-side encryption or local filesystem access controls
- Provide `agent:install` command for bootstrapping connector configuration with hybrid interactive/automated mode
- Provide `agent:restart` command for graceful runtime service management with configurable scope
- Maintain full audit trail for all chat-triggered mutations with actor and context attribution
- Ensure system resilience when individual connector providers are degraded
- Enforce idempotency via unique constraint with collision-resistant fallback key generation including provider_message_id or full content+attachment hash
- Implement per-provider rate limit handling with exponential backoff, jitter, and circuit breaker patterns
- Verify Discord webhooks using Ed25519 signature verification with separate timestamp-based replay protection (configurable window, default 300s)
- Separate signature verification configuration from replay protection configuration for clarity and correctness


## Constraints

- No cloud dependency for core orchestration—control plane runs on user infrastructure
- No arbitrary shell execution from chat—all actions converted to validated structured payloads
- No replacement of existing scheduler/runner internals—reuse existing job/run authorization and policy validation
- Native iOS/Android client out of scope for current phase
- Multi-tenant hosted SaaS control plane out of scope
- MVP steering limited to stop+restart with context or queue follow-up run (no live stdin/PTY)
- Must respect messenger provider platform rules (ack timing, signature checks, webhook/polling limits)
- Existing queue/horizon/reverb runtime remains execution backbone
- All generated payloads must pass CommandPolicy, PathPolicy, EnvPolicy equivalents
- WhatsApp Cloud API is webhook-only for inbound events—no local connector mode available
- connector_accounts is the sole source of truth for messenger connections—do not extend existing providers table
- Attachment storage uses S3 server-side encryption for cloud; local disk relies on filesystem access controls and OS/disk-level encryption (not application-level encryption)
- Phase A attachment support applies to Slack and Telegram only; Discord and WhatsApp attachments deferred to Phase B
- Account-link GET is read-only (renders UI); POST performs state mutation to avoid prefetch/crawler issues
- Chat message logs must never contain raw file URLs—store only attachment IDs with presigned URL generation on demand
- Idempotency keys must include provider_message_id when available, otherwise full content+attachment hash to prevent collision on identical rapid messages
- Telegram default mode is long polling (local), not webhook
- Account-link tokens use Redis as primary store with DB table as fallback; DB consumption requires atomic UPDATE with consumed_at IS NULL guard
- Discord timestamp verification window defaults to 300 seconds (not 5 seconds) for network tolerance
- Ed25519 is a signature verification scheme (Discord), not a replay protection strategy—config must separate signature_verification from replay_protection


## Acceptance Criteria

- User can configure at least one messenger provider (Slack or Telegram) via agent:install and issue commands
- User can create a cron job from natural-language chat with schedule parsing and policy validation
- User can list active runs and execute stop/retry/run-now commands through chat interface
- Every chat-triggered mutation emits audit record with messenger identity, linked user, channel context, and correlation ID
- Account-link flow uses GET for read-only token validation and UI rendering; POST /messenger-link/{token}/complete performs state mutation
- POST account-link endpoint uses auth middleware (redirects to login if unauthenticated)
- Account-link tokens stored in Redis (primary) with atomic check-and-delete on POST; DB fallback uses atomic UPDATE with consumed_at IS NULL guard to prevent race-condition double use
- Account-link token replay is prevented via atomic consumption on POST and immediate invalidation
- Unlinked messenger users receive onboarding prompt with auth link and cannot execute actions until linked
- Mutations acknowledge immediately and deliver progress updates via provider-appropriate threading (native, quote, edit per matrix)
- Destructive actions (runs.stop, jobs.update, jobs.delete) respect per-provider confirmation configuration
- Non-destructive mutations (runs.retry, runs.run_now, runs.steer, jobs.create) execute without confirmation regardless of provider setting
- Channel read-only restrictions (when configured) prevent mutations from restricted channels
- agent:install performs preflight checks and configures connectors in both interactive and non-interactive modes
- agent:restart gracefully restarts Horizon, Reverb, and scheduler; web server inclusion respects configuration flag
- System continues operating normally when one connector provider is degraded—other providers unaffected
- Outbound messages queue and retry with exponential backoff plus jitter; dead-letter after configurable duration
- Rate limit responses (429) trigger provider-specific backoff with configurable base delay, multiplier, and ceiling
- Circuit breaker activates after consecutive failures and pauses outbound for cooldown period
- Slack webhook verification uses HMAC-SHA256 signature scheme with X-Slack-Request-Timestamp; replay protection uses timestamp strategy with configurable window (default 300s)
- Discord webhook verification uses Ed25519 signature scheme (X-Signature-Ed25519 header); replay protection uses timestamp strategy via X-Signature-Timestamp with configurable window (default 300s)
- Telegram replay protection uses update_id deduplication with configurable TTL
- Duplicate events rejected via unique constraint on (connector_account_id, idempotency_key)
- Fallback idempotency key uses provider_message_id when available; otherwise includes full content+attachment hash to prevent false duplicates
- Chat sessions maintain per-thread context with configurable history retention
- Bidirectional attachments for Slack and Telegram processed with size limit enforcement, MIME type validation, and malware scanning
- S3 attachments stored with server-side encryption (SSE-S3 or SSE-KMS); local attachments protected via filesystem access controls
- Attachment access returns presigned URLs with 15-minute TTL; raw storage paths never exposed in chat logs
- Infected attachments quarantined and user notified; clean attachments stored with automatic expiration
- Multiple bot connections per provider type can be configured and operate concurrently
- WhatsApp connector operates in webhook mode only; agent:install enforces this constraint
- Telegram connector defaults to long polling mode (local) not webhook
- Connector config schema separates signature_verification (scheme, signing_secret/public_key) from replay_protection (strategy, window_seconds, dedupe_ttl_seconds)

