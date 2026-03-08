# Requirements Discovery Summary

Session: 4

# Agent Connectors — Requirements Discovery Summary

## Overview
External service integration layer for the Agent platform (Laravel 12 / PHP 8.3) enabling agents (Jobs, Messenger, Delegation/Org Layer) to read from, write to, and react to events from external SaaS platforms via MCP tool contracts. Connectors are a composition layer extending the existing MCP tool contract — each connector surfaces as one or more MCP tools with standard scope, authentication, and telemetry semantics. Targets 890 companies across 13 UK mid-market verticals with 45 connectors across 3 tiers.

## Architecture Decisions

### Team Model Alignment
All connector tables use `team_id UUID NOT NULL REFERENCES teams(id)` to match the codebase's Jetstream Team model. No `tenants` abstraction. The credential vault's per-team key derivation, data residency enforcement, telemetry retention config, and connection scoping all key off `teams.id`.

### Messenger ConnectorAccount Relationship
The existing `ConnectorAccount` model for messaging providers (Slack, Telegram, Discord, WhatsApp) remains **permanently separate**. No migration path or shared base model. Messaging connectors continue to serve the Messenger subsystem exclusively.

### Manifest Storage Authority
**Hybrid model**: Connector manifest JSON files on the filesystem are the source of truth (versioned in the repo under `connectors/{name}/connector.json`). At boot, `ConnectorRegistryLoader` is invoked from `ConnectorServiceProvider::boot()` to parse all manifests and upsert them into the `agent_connectors` database table. The sync is idempotent — upserts from on-disk manifests, marks removed manifests as deprecated, safe to run on every boot. The `php artisan connector:sync` CLI command triggers the same sync manually.

### Multi-Instance Connections
Teams can connect **multiple instances** of the same connector (e.g., two Xero organisations). Each connection has a user-defined label/alias. The `UNIQUE(team_id, connector_id)` constraint from the brief is replaced with `UNIQUE(team_id, connector_id, alias)`. The `ConnectorResolver` supports alias-based routing.

### Alias Conflict Resolution
When an agent invokes a connector action without specifying a connection alias and multiple connections exist, the runtime **prompts the delegating user for clarification via the approval/messenger channel**. This reuses the `agent_connector_approvals` table with `type: clarification`. A clarification request is created with the list of available connection aliases, sent to both messenger (inline selection buttons) and dashboard. The action is paused until clarification is received or the 15-minute timeout expires.

### Connector Discovery Model
**Hybrid: auto-discovery with optional workflow-level pinning.** Agents can auto-discover all team-connected connectors at runtime via the `connectors.auto_resolve` feature flag. Workflows can optionally pin specific connectors (by name and/or connection alias). When pinned, the agent only sees declared connectors.

### Webhook Deployment Model
**Built-in tunnel support** for local-first deployment. Configuration via `config/connectors.php` with `webhook_tunnel_provider` (cloudflare|ngrok|none), `webhook_tunnel_token`, and `webhook_base_url`. In deployed mode, uses the application's public URL directly.

### IP Whitelisting Strategy
Connectors requiring IP whitelisting marked `requires_static_ip: true` in manifest. **Unsupported in local-first mode** — only enabled when Cloudflare Tunnel is configured. `ConnectorResolver` blocks connection attempts in local-first mode with a descriptive error. UI shows "Requires tunnel configuration" when no tunnel is active.

### Partnership-Gated Connectors
Tier 3 connectors requiring partnership agreements (Acturis, Alto, Person Centred Software flagged) are **skipped entirely in Phase 1**. Deferred to Phase 2 alongside the connector SDK. Manifests created with `status: partnership_pending` for registry/UI display but cannot be connected. Manifest includes `partnership_notes` field documenting access requirements.

### SDK Extension Points (Phase 2 Preparation)
Phase 1 includes explicit extension points:
- `ConnectorInterface` — contract for all implementations (authenticate, execute, healthCheck, listActions)
- `ConnectorManifestValidator` — validates connector.json against manifest JSON schema
- `ConnectorActionHandler` — base class with hooks for request transform, response normalize, error map
- `ConnectorPluginLoader` — discovers and registers connector bundles from configurable directories
- `ConnectorAuthProvider` interface — abstracts OAuth, API key, and custom auth flows
All built-in connectors implement these interfaces, dogfooding the SDK contract.

### GDPR/UK DPA Compliance Scope
**No additional compliance work in-scope.** Existing PII redaction (per-action `pii_fields` with configurable redaction mode), per-team credential encryption (`ConnectorVaultEncrypter`), audit logging (`agent_connector_credential_events`), and data residency settings are sufficient technical safeguards. Formal compliance certification is a separate organisational initiative.

## Credential Vault

### Backend
**Custom `ConnectorVaultEncrypter` service** — per-team `Illuminate\Encryption\Encrypter` instance, NOT the global `Crypt` facade. Accepts a `Team` model, derives a per-team key, returns an `Encrypter` instance configured with AES-256-CBC.

**Key derivation:** `hash('sha256', $team->connector_vault_key . config('app.key'))` truncated to 32 bytes.

Tokens stored in `agent_connector_credentials.encrypted_data` (TEXT — Encrypter outputs base64). `encryption_key_id` tracks key version for rotation. No external secrets manager dependency.

### Key Source — Teams Table Migration
Migration adds `connector_vault_key VARCHAR(64) NULL` to `teams` table. Generated lazily on first connector connection via `Str::random(64)`. Key rotation: new `connector_vault_key`, re-encrypt all credentials, increment `encryption_key_id`, log `rotated` event.

### Supported Auth Types
- OAuth 2.0 Authorization Code with PKCE (auto-refresh via `refresh_token`)
- OAuth 2.0 Client Credentials (auto-refresh on expiry)
- API Key (stored encrypted, no refresh)
- API Key + Secret (stored encrypted, no refresh)
- Basic Auth (username + password, stored encrypted)
- Custom Header (user-defined header/value, stored encrypted)

### Credential Refresh
Background job (`RefreshConnectorCredentialJob`) on `supervisor-connector-credentials` with 5-minute expiry buffer, 3 retry attempts with backoff [10, 30, 60]s. Failure marks credential `degraded`, emits alert. Lifecycle events logged to `agent_connector_credential_events`.

## Team Configuration Storage

Per-team connector config in dedicated `agent_connector_settings` table:

```sql
CREATE TABLE agent_connector_settings (
    id                       UUID PRIMARY KEY,
    team_id                  UUID NOT NULL REFERENCES teams(id) UNIQUE,
    telemetry_retention_days INTEGER NOT NULL DEFAULT 90,
    enforce_data_residency   BOOLEAN NOT NULL DEFAULT FALSE,
    allowed_regions          JSONB DEFAULT '[]',
    rate_limit_multiplier    DECIMAL(3,2) DEFAULT 1.00,
    approval_timeout_minutes INTEGER NOT NULL DEFAULT 15,
    created_at               TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at               TIMESTAMP NOT NULL DEFAULT NOW()
);
```

Created lazily on first connector connection alongside `connector_vault_key` generation. The `approval_timeout_minutes` field governs the default timeout for both approval and clarification requests (15 minutes default, configurable per-team).

## Horizon Supervisors (3 new)

**supervisor-connector-credentials** — `RefreshConnectorCredentialJob`:
```php
'connection' => 'redis', 'queue' => ['connector-credentials'], 'balance' => 'auto',
'maxProcesses' => max(1, min(4, (int) env('HORIZON_CONNECTOR_CREDS_MAX_PROCESSES', 2))),
'tries' => 3, 'backoff' => [10, 30, 60], 'timeout' => 60
```

**supervisor-connector-webhooks** — `ProcessConnectorWebhookJob`:
```php
'connection' => 'redis', 'queue' => ['connector-webhooks'], 'balance' => 'auto',
'maxProcesses' => max(1, min(8, (int) env('HORIZON_CONNECTOR_WEBHOOKS_MAX_PROCESSES', 4))),
'tries' => 3, 'backoff' => [5, 15, 45], 'timeout' => 30
```

**supervisor-connector-approvals** — `ExpireConnectorApprovalJob`:
```php
'connection' => 'redis', 'queue' => ['connector-approvals'], 'balance' => 'auto',
'maxProcesses' => max(1, min(2, (int) env('HORIZON_CONNECTOR_APPROVALS_MAX_PROCESSES', 1))),
'tries' => 3, 'backoff' => [5, 15, 30], 'timeout' => 30
```

All with production overrides: `balanceMaxShift => 1`, `balanceCooldown => 3`.

## Security & Trust

### Write Action Trust Threshold
**Configurable per-connector with 0.7 default.** `write_trust_threshold` in manifest (decimal 0.0–1.0). `PolicyGate` checks delegatee trust score from `TrustScoreCalculator`. Read-only actions have no trust gate. Risk-elevated connectors can set higher thresholds (0.8+).

### PII Redaction Scope
**Configurable per-connector**: `pii_redaction_mode` — `memory_only` (default) or `context_and_memory`. Per-action `pii_fields` array declares which response fields contain PII.

### Data Residency Enforcement
**Configurable per-team** via `agent_connector_settings`. Each connector manifest declares `data_regions` (ISO country codes). When `enforce_data_residency` is true, `ConnectorResolver` blocks connections to connectors with non-allowed data regions.

### Approval & Clarification Infrastructure
**Single table:** `agent_connector_approvals` with `type VARCHAR(20) NOT NULL DEFAULT 'approval'` column. Type values: `approval` | `clarification`. Both use same dual-channel delivery (messenger + dashboard, first-response wins) and same `ExpireConnectorApprovalJob`. Default timeout: **15 minutes** for both types, configurable per-team via `agent_connector_settings.approval_timeout_minutes`. Clarification rows have nullable `connection_id` (may occur before connection selection).

### Approvals Data Model
```sql
CREATE TABLE agent_connector_approvals (
    id                  UUID PRIMARY KEY,
    team_id             UUID NOT NULL REFERENCES teams(id),
    connection_id       UUID REFERENCES agent_connector_connections(id),
    connector_id        UUID NOT NULL REFERENCES agent_connectors(id),
    type                VARCHAR(20) NOT NULL DEFAULT 'approval',
    action_name         VARCHAR(128) NOT NULL,
    run_attempt_id      UUID,
    delegatee_id        UUID,
    workflow_key        VARCHAR(255),
    request_payload     JSONB NOT NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'pending',
    resolved_by         UUID REFERENCES users(id),
    resolved_via        VARCHAR(20),
    resolved_at         TIMESTAMP,
    resolution_note     TEXT,
    timeout_minutes     INTEGER NOT NULL DEFAULT 15,
    expires_at          TIMESTAMP NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_approvals_team_status ON agent_connector_approvals (team_id, status);
CREATE INDEX idx_approvals_expires ON agent_connector_approvals (status, expires_at) WHERE status = 'pending';
CREATE INDEX idx_approvals_connection ON agent_connector_approvals (connection_id, created_at);
CREATE INDEX idx_approvals_type ON agent_connector_approvals (type, status);
```

## Authorization

### ConnectorPolicy (3 abilities)
| Ability | Grants | Required Role |
|---------|--------|---------------|
| `viewConnectorTelemetry` | Read invocation history and metrics | Team admin or operator |
| `manageConnectors` | Connect, disconnect, test, configure, approve/reject | Team admin or operator |
| `adminConnectors` | Rotate credentials, configure webhooks, rate limit overrides | Team admin only |

Agent-invoked actions bypass user-level auth; governed by delegation trust score instead.

## Connector Runtime

### Execution Pipeline
`ConnectorResolver` (validate connected + read stored `health_score` + alias resolution + static IP check; if multiple connections and no alias → prompt via clarification channel) → `PolicyGate` (trust score + risk level + data residency) → `ApprovalGate` (check `requires_approval_for`, create approval record if needed, await resolution) → `CredentialManager` (decrypt via `ConnectorVaultEncrypter`) → `RateLimiter` (token bucket: per-connector limits × per-team `rate_limit_multiplier`) → `HttpClient` (request with auth headers + timeout + retry + circuit breaker) → `ResponseNormalizer` (map to action schema) → `PiiRedactor` (strip PII per mode) → `TelemetryEmitter` (record events + write back `health_score`) → Result to agent context.

### Health Score Write-Back
`TelemetryEmitter` writes updated `health_score` to `agent_connector_connections` after each invocation. Full recalculation only on explicit health check. Formula: credential_health (40%) + error_rate (30%) + latency (15%) + rate_limit_headroom (15%). Status: ≥0.8 healthy, ≥0.5 degraded, <0.5 unhealthy.

### Resilience
- **Retry**: 3 attempts, exponential backoff (1s, 2s, 4s) + jitter. Retry on 429/500/502/503/504. No retry on 401/403/404/422.
- **Circuit Breaker**: 5 failures in 60s → open for 120s. Always returns structured error — no cached data.
- **Rate Limiter**: Token bucket per-connector × per-team multiplier. Composes with delegation concurrency limits.
- **Timeout**: Default 30s per action, configurable per action.
- **Dead-letter Queue**: Failed webhook events after 3 delivery attempts, 30-day retention, manual replay.

### Team Connection Limit
**No hard limit.** Rate limiting, quota controls, and workflow budget caps govern abuse prevention.

## Feature Flags

All 6 flags use **existing custom FeatureFlagManager** backed by `agent_feature_settings`:

| Flag | Description |
|------|-------------|
| `connectors.enabled` | Master toggle |
| `connectors.ui_enabled` | UI pages |
| `connectors.webhooks_enabled` | Push-based webhooks |
| `connectors.auto_resolve` | Agent auto-discovery |
| `connectors.write_actions` | Write/mutate actions |
| `connectors.credential_refresh` | Background token refresh |

Rollout: `connectors.enabled` → `connectors.credential_refresh` → `connectors.write_actions` → `connectors.ui_enabled` → `connectors.webhooks_enabled` → `connectors.auto_resolve`

## Telemetry

Events: `connector.invoked`, `connector.completed`, `connector.failed`, `connector.webhook_received`

Fields: `connector_id`, `connection_id`, `action`, `duration_ms`, `http_status`, `retry_count`, `token_usage`, `outcome`, `workflow_key`, `run_attempt_id`, `delegatee_id`

Events feed into existing `WeightedReliability` calculations. Connector API costs attributed to invoking workflow's budget.

## Testing Strategy
Mock HTTP responses per-connector in unit/feature tests. Each connector ships with `tests/` containing mock response fixtures (JSON). Tests use Laravel `Http::fake()`. No live API calls in CI.

## Connector Set (45 total)

### Tier 1 — Cross-Industry Essentials (13, ship first)
Xero (OAuth 2.0), Sage 50/200 (API Key), Salesforce (OAuth 2.0), HubSpot (OAuth 2.0), Microsoft Teams (OAuth 2.0), Slack (OAuth 2.0), Google Workspace (OAuth 2.0), Microsoft 365 (OAuth 2.0), Stripe (API Key), GoCardless (OAuth 2.0), DocuSign (OAuth 2.0), Monday.com (API Key), Notion (OAuth 2.0)

### Tier 2 — High-Priority Multi-Vertical (14, ship second)
BrightHR (API Key), Breathe HR (OAuth 2.0), Zendesk (OAuth 2.0), Freshdesk (API Key), Mailchimp (OAuth 2.0), ActiveCampaign (API Key), Calendly (OAuth 2.0), Jira (OAuth 2.0), Asana (OAuth 2.0), QuickBooks Online (OAuth 2.0), FreeAgent (OAuth 2.0), Shopify (OAuth 2.0), Trello (API Key), Dropbox (OAuth 2.0)

### Tier 3 — Industry-Specific Vertical (18, ship third, by API accessibility)
Partnership-gated (Acturis, Alto, Person Centred Software) skipped Phase 1, marked `status: partnership_pending`.
- IT/MSP: ConnectWise Manage, Datto Autotask
- Healthcare/Care: CareLineLive (partnership pending), Person Centred Software (partnership pending)
- Facilities: ServiceM8, Simpro
- Logistics: Brightpearl, ShipStation
- Construction: Procore, Sage Construction
- Property: Alto (partnership pending), Reapit
- Recruitment: Bullhorn, Vincere
- Legal: Clio, LEAP
- Insurance: Acturis (partnership pending), Open GI

## Data Model Changes from Brief (16 modifications)
1. Replace all `tenant_id` with `team_id` referencing `teams(id)`
2. Add `connector_vault_key VARCHAR(64) NULL` to `teams` table
3. Replace `UNIQUE(team_id, connector_id)` on connections with `UNIQUE(team_id, connector_id, alias)`
4. Add `alias VARCHAR(128) NOT NULL DEFAULT 'default'` to connections
5. Replace `UNIQUE(team_id, connector_id)` on credentials with `UNIQUE(team_id, connector_id, connection_id)`
6. Add `connection_id UUID NOT NULL REFERENCES agent_connector_connections(id)` to credentials
7. Change `encrypted_data` from `BYTEA` to `TEXT`
8. Add `agent_connector_settings` table (telemetry_retention_days, enforce_data_residency, allowed_regions, rate_limit_multiplier, approval_timeout_minutes)
9. Add `data_regions JSONB DEFAULT '[]'` to `agent_connectors`
10. Add `write_trust_threshold DECIMAL(3,2) DEFAULT 0.70` to `agent_connectors`
11. Add `pii_redaction_mode VARCHAR(20) DEFAULT 'memory_only'` to `agent_connectors`
12. Add `requires_static_ip BOOLEAN DEFAULT FALSE` to `agent_connectors`
13. Add `partnership_notes TEXT NULL` to `agent_connectors`
14. Add `agent_connector_approvals` table with `type` column (`approval` | `clarification`)
15. Add `partnership_pending` to `agent_connectors` status enum
16. Default `timeout_minutes` on approvals table changed from 30 to 15

## API Surface (14 endpoints)
```
GET    /agent/api/v1/connectors
GET    /agent/api/v1/connectors/{id}
POST   /agent/api/v1/connectors/{id}/connect
DELETE /agent/api/v1/connectors/{id}/connections/{connectionId}
POST   /agent/api/v1/connectors/{id}/connections/{connectionId}/test
GET    /agent/api/v1/connectors/{id}/actions
POST   /agent/api/v1/connectors/{id}/connections/{connectionId}/actions/{action}
GET    /agent/api/v1/connectors/{id}/connections/{connectionId}/health
GET    /agent/api/v1/connectors/{id}/connections/{connectionId}/telemetry
POST   /agent/api/v1/connectors/{id}/connections/{connectionId}/webhooks
DELETE /agent/api/v1/connectors/{id}/connections/{connectionId}/webhooks/{event}
GET    /agent/api/v1/connectors/callback
GET    /agent/api/v1/connectors/approvals
POST   /agent/api/v1/connectors/approvals/{id}/resolve
```

## CLI Surface (9 commands)
```bash
php artisan connector:list
php artisan connector:connect {name} [--api-key=] [--alias=]
php artisan connector:disconnect {name} [--alias=]
php artisan connector:test {name} [--alias=]
php artisan connector:health
php artisan connector:actions {name}
php artisan connector:sync
php artisan connector:prune-telemetry
php artisan connector:rotate-keys {team}
```

## Messenger Surface
- `connect {service} [alias]` — Initiate connection
- `list connections` / `my connections` — Show connected services with status
- `test {service} [alias]` — Run health check
- `disconnect {service} [alias]` — Remove connection with confirmation
- Inline approve/reject buttons for write action approval requests
- Inline connection selection buttons for alias clarification prompts

## Goals

- Deliver connector architecture built on MCP tool contracts surfacing as standard MCP tools within existing scope (tenant/environment/role) and transport model
- Provide secure per-team credential vault using custom ConnectorVaultEncrypter (AES-256-CBC, per-team key derivation from team.connector_vault_key + app.key)
- Ship 13 Tier 1 cross-industry connectors: Xero, Sage 50/200, Salesforce, HubSpot, Microsoft Teams, Slack, Google Workspace, Microsoft 365, Stripe, GoCardless, DocuSign, Monday.com, Notion
- Ship 14 Tier 2 multi-vertical connectors: BrightHR, Breathe HR, Zendesk, Freshdesk, Mailchimp, ActiveCampaign, Calendly, Jira, Asana, QuickBooks, FreeAgent, Shopify, Trello, Dropbox
- Ship 18 Tier 3 industry-specific vertical connectors across 9 verticals, prioritized by API accessibility with partnership-gated connectors deferred to Phase 2
- Build connector registry with boot-time manifest sync via ConnectorServiceProvider::boot() calling ConnectorRegistryLoader::sync() and full lifecycle management
- Support both pull-based (agent queries external service) and push-based (webhook ingestion with signature verification, dead-letter queue, built-in tunnel support) connector patterns
- Integrate connector telemetry into existing WeightedReliability scoring, cost governance, and operator dashboard surfaces
- Implement unified approval and clarification flow using single agent_connector_approvals table with type column (approval | clarification), dual-channel delivery (messenger + dashboard), 15-minute default timeout
- Build ConnectorPolicy authorization with 3 abilities (viewConnectorTelemetry, manageConnectors, adminConnectors) plus trust-score-gated write access
- Implement connector health scoring (credential 40%, error_rate 30%, latency 15%, rate_limit_headroom 15%) with lightweight write-back after each invocation via TelemetryEmitter
- Deliver 14-endpoint REST API surface and 9 artisan CLI commands for connector management
- Implement PII redaction with per-connector configurable modes (memory_only default, opt-in context_and_memory) using per-action pii_fields declarations


## Constraints

- All connector tables use team_id referencing Jetstream teams(id), not tenant_id/tenants(id)
- Credential vault uses custom ConnectorVaultEncrypter per team — never the global Crypt facade
- Encryption algorithm is AES-256-CBC to match Laravel Illuminate Encrypter; key derived from hash('sha256', team.connector_vault_key + config('app.key')) truncated to 32 bytes
- Feature flags use existing FeatureFlagManager backed by agent_feature_settings table, not Laravel Pennant
- MCP scope dimensions remain exactly tenant, environment, role — no new dimensions
- Evaluation order remains scope check then permission claim check per agent-mcp-v5 contract
- Connector tools follow existing stability tiers (stable, beta, experimental) and CI matrix gates
- Transport semantics remain poll plus WebSocket with stable app error codes
- Connector-specific errors map to existing error taxonomy plus new connector-specific codes
- Connector invocations are policy-gated through existing CommandPolicy, PathPolicy, EnvPolicy
- Trust scoring determines connector access — delegatees below threshold cannot invoke write-enabled or sensitive connectors
- Memory failures never block connector execution per memory contract
- Sensitive connector data excluded from memory formation via configurable PII redaction rules
- Connector cost attribution uses existing monthly workflow budget — costs are additive
- Rate limit exhaustion triggers automatic backoff, not hard failure
- Webhooks only functional in deployed mode or with user-configured tunnel (ngrok/Cloudflare) — not supported in bare local-first mode
- No hard limit on connections per team — rate limiting, quota controls, and workflow budget caps govern abuse
- Partnership-gated Tier 3 connectors marked status partnership_pending and skipped until Phase 2
- Connectors requiring static IP marked requires_static_ip: true and unsupported in local-first mode without Cloudflare Tunnel
- No public connector marketplace or third-party submissions in Phase 1
- No visual workflow builder — connectors invoked by agents via skill/delegation context
- No bi-directional real-time sync — Phase 1 supports read + write + event only
- No SOAP/legacy protocol connectors beyond thin REST wrappers
- No custom connector authoring SDK in Phase 1 (extension points designed, SDK deferred to Phase 2)
- No additional GDPR/UK DPA compliance work in-scope — existing platform posture covers connector data flows
- Approval and clarification requests share 15-minute default timeout, configurable per-team via agent_connector_settings.approval_timeout_minutes
- Existing ConnectorAccount model for messaging providers remains permanently separate — no shared base model


## Acceptance Criteria

- ConnectorVaultEncrypter creates per-team Encrypter instance with key derived from hash('sha256', team.connector_vault_key + config('app.key')) truncated to 32 bytes using AES-256-CBC
- All connector tables (agent_connectors, agent_connector_connections, agent_connector_credentials, agent_connector_invocations, agent_connector_webhook_events, agent_connector_credential_events, agent_connector_approvals, agent_connector_settings) use team_id FK to teams(id)
- ConnectorServiceProvider::boot() calls ConnectorRegistryLoader::sync() to idempotently load connector manifests from disk into agent_connectors table on every application boot
- OAuth 2.0 authorization code flow with PKCE completes successfully for at least Xero, Salesforce, HubSpot, and Google Workspace
- API key authentication flow stores encrypted key via ConnectorVaultEncrypter and returns connected status for Sage 50/200, Stripe, Monday.com
- ConnectorPolicy enforces 3 abilities: viewConnectorTelemetry (admin/operator), manageConnectors (admin/operator), adminConnectors (admin only)
- Write connector actions gated by delegation trust score at configurable threshold per connector (write_trust_threshold, default 0.7)
- Token bucket rate limiter enforces per-connector limits from manifest multiplied by per-team rate_limit_multiplier from agent_connector_settings
- Health score calculated as weighted average: credential_health (40%) + error_rate (30%) + latency (15%) + rate_limit_headroom (15%)
- Health score write-back occurs after each invocation via TelemetryEmitter; full recalculation only on explicit health check
- Connector execution pipeline follows sequence: ConnectorResolver → PolicyGate → ApprovalGate → CredentialManager → RateLimiter → HttpClient → ResponseNormalizer → PiiRedactor → TelemetryEmitter
- Retry mechanism: 3 attempts with exponential backoff (1s, 2s, 4s) + jitter on 429/500/502/503/504; no retry on 401/403/404/422
- Circuit breaker: 5 failures in 60s opens circuit for 120s; always returns structured error when open — no cached responses
- PII redaction defaults to memory_only mode; context_and_memory mode available as opt-in per connector manifest
- Telemetry events emitted: connector.invoked, connector.completed, connector.failed, connector.webhook_received with required fields (connector_id, connection_id, action, duration_ms, http_status, retry_count, token_usage, outcome)
- Webhook ingestion endpoint verifies provider-specific signatures, deduplicates by (external_event_id, connector_id), routes to matching workflow
- Dead-letter queue stores unmatched/failed webhook events with 30-day retention and manual replay capability
- agent_connector_approvals table supports both approval and clarification types via type column (approval | clarification) with nullable connection_id for clarifications
- ExpireConnectorApprovalJob handles expiry for both approval and clarification rows with 15-minute default timeout
- Dual-channel delivery (messenger + dashboard) with first-response-wins semantics for both approvals and clarifications
- When multiple connections exist for same connector and no alias specified, ConnectorResolver creates clarification request via approval channel with inline connection selection
- 3 Horizon supervisors configured: supervisor-connector-credentials, supervisor-connector-webhooks, supervisor-connector-approvals with production overrides
- All 6 feature flags registered via FeatureFlagManager: connectors.enabled, connectors.ui_enabled, connectors.webhooks_enabled, connectors.auto_resolve, connectors.write_actions, connectors.credential_refresh
- Credential refresh background job runs with 5-minute buffer before expiry, 3 retry attempts with backoff [10, 30, 60]s, marks credential degraded on failure
- All 14 REST API endpoints operational under /agent/api/v1/connectors with proper ConnectorPolicy authorization checks
- All 9 artisan CLI commands functional: connector:list, connector:connect, connector:disconnect, connector:test, connector:health, connector:actions, connector:sync, connector:prune-telemetry, connector:rotate-keys
- agent_connector_settings table created with telemetry_retention_days (default 90), enforce_data_residency (default false), allowed_regions (default []), rate_limit_multiplier (default 1.00), approval_timeout_minutes (default 15)
- Partnership-gated connectors stored with status partnership_pending and excluded from connect flow but visible in registry/UI
- Connectors with requires_static_ip: true blocked from connection in local-first mode with descriptive error
- Telemetry pruned by connector:prune-telemetry command based on per-team telemetry_retention_days setting

