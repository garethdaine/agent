# Implementation Plan

Derived from discovery session 4.

# Agent Connectors — Implementation Plan

## Phase 1: Foundation — Configuration, Data Model & Credential Vault

### 1.1 Configuration File
- Create `config/connectors.php` with sections: `enabled` (master toggle, default false), `manifest_path` (default `base_path('connectors')`), `webhook_tunnel_provider` (cloudflare|ngrok|none), `webhook_tunnel_token`, `webhook_base_url`, `default_timeout` (30), `health_check_interval` (300), `credential_refresh_buffer_seconds` (300), `max_refresh_retries` (3)
- All feature flag default values sourced from this config via `config('connectors.*')` pattern consistent with other subsystems

### 1.2 Database Migrations
Create migration `2026_03_09_000001_create_agent_connector_tables.php` containing all 8 tables:

**Table: `agent_connectors`** — Connector registry (available connectors)
- Columns: `id` UUID PK, `name` VARCHAR(64) NOT NULL UNIQUE, `display_name` VARCHAR(128), `description` TEXT, `category` VARCHAR(64), `industries` JSONB DEFAULT '[]', `version` VARCHAR(20), `auth_type` VARCHAR(30), `auth_config` JSONB, `base_url` TEXT, `rate_limits` JSONB, `cost_model` VARCHAR(20) DEFAULT 'free', `risk_level` VARCHAR(20) DEFAULT 'standard', `actions` JSONB, `webhooks` JSONB DEFAULT '[]', `icon_path` TEXT, `status` VARCHAR(20) DEFAULT 'available' (available|deprecated|disabled|partnership_pending), `data_regions` JSONB DEFAULT '[]', `write_trust_threshold` DECIMAL(3,2) DEFAULT 0.70, `pii_redaction_mode` VARCHAR(20) DEFAULT 'memory_only', `requires_static_ip` BOOLEAN DEFAULT FALSE, `partnership_notes` TEXT NULL, `created_at`, `updated_at`
- Indexes: `idx_connectors_category` on (category), GIN index on `industries`

**Table: `agent_connector_connections`** — Per-team installations
- Columns: `id` UUID PK, `team_id` UUID NOT NULL FK teams(id), `connector_id` UUID NOT NULL FK agent_connectors(id), `alias` VARCHAR(128) NOT NULL DEFAULT 'default', `status` VARCHAR(20) DEFAULT 'pending' (pending|connected|degraded|disconnected|error), `health_score` DECIMAL(3,2) DEFAULT 1.00, `config` JSONB DEFAULT '{}', `webhook_subscriptions` JSONB DEFAULT '[]', `last_health_check_at` TIMESTAMP, `last_action_at` TIMESTAMP, `action_count_24h` INTEGER DEFAULT 0, `error_count_24h` INTEGER DEFAULT 0, `connected_by` UUID FK users(id), `connected_at` TIMESTAMP, `disconnected_by` UUID FK users(id), `disconnected_at` TIMESTAMP, `created_at`, `updated_at`
- UNIQUE constraint: `(team_id, connector_id, alias)`
- Indexes: `idx_connections_team_status` on (team_id, status), `idx_connections_connector` on (connector_id)

**Table: `agent_connector_credentials`** — Encrypted credential storage
- Columns: `id` UUID PK, `team_id` UUID NOT NULL FK teams(id), `connector_id` UUID NOT NULL FK agent_connectors(id), `connection_id` UUID NOT NULL FK agent_connector_connections(id), `auth_type` VARCHAR(30), `encrypted_data` TEXT NOT NULL, `encryption_key_id` VARCHAR(64), `scopes_granted` JSONB DEFAULT '[]', `token_expires_at` TIMESTAMP, `refresh_token_expires_at` TIMESTAMP, `status` VARCHAR(20) DEFAULT 'active' (active|degraded|expired|revoked), `last_refreshed_at` TIMESTAMP, `last_used_at` TIMESTAMP, `refresh_failure_count` INTEGER DEFAULT 0, `created_by` UUID FK users(id), `updated_by` UUID FK users(id), `revoked_by` UUID FK users(id), `revoked_at` TIMESTAMP, `rotation_count` INTEGER DEFAULT 0, `created_at`, `updated_at`
- UNIQUE constraint: `(team_id, connector_id, connection_id)`

**Table: `agent_connector_invocations`** — Telemetry
- Columns: `id` UUID PK, `connection_id` UUID NOT NULL FK, `connector_id` UUID NOT NULL FK, `action_name` VARCHAR(128), `run_attempt_id` UUID, `delegatee_id` UUID, `workflow_key` VARCHAR(255), `http_method` VARCHAR(10), `http_status` INTEGER, `duration_ms` INTEGER, `request_size_bytes` INTEGER, `response_size_bytes` INTEGER, `token_usage` INTEGER, `retry_count` INTEGER DEFAULT 0, `outcome` VARCHAR(20), `error_message` TEXT, `created_at`
- Indexes: on (connection_id, created_at), (run_attempt_id), (workflow_key, created_at), (outcome, created_at)

**Table: `agent_connector_webhook_events`** — Webhook event log
- Columns: `id` UUID PK, `connection_id` UUID NOT NULL FK, `connector_id` UUID NOT NULL FK, `event_type` VARCHAR(128), `external_event_id` VARCHAR(255), `payload_hash` VARCHAR(64), `processing_status` VARCHAR(20) DEFAULT 'received' (received|processing|routed|dead_letter), `routed_to_workflow` VARCHAR(255), `routed_to_job_id` UUID, `processing_duration_ms` INTEGER, `retry_count` INTEGER DEFAULT 0, `error_message` TEXT, `created_at`
- UNIQUE constraint: `(connection_id, external_event_id)`
- Indexes: on (connection_id, created_at), (processing_status)

**Table: `agent_connector_credential_events`** — Audit log
- Columns: `id` UUID PK, `credential_id` UUID NOT NULL FK, `event_type` VARCHAR(30) (created|refreshed|refresh_failed|expired|revoked|rotated), `actor_id` UUID FK users(id), `details` JSONB DEFAULT '{}', `created_at`
- Index: on (credential_id, created_at)

**Table: `agent_connector_approvals`** — Approval and clarification requests
- Columns: `id` UUID PK, `team_id` UUID NOT NULL FK teams(id), `connection_id` UUID FK agent_connector_connections(id) (nullable for clarifications), `connector_id` UUID NOT NULL FK, `type` VARCHAR(20) NOT NULL DEFAULT 'approval' (approval|clarification), `action_name` VARCHAR(128), `run_attempt_id` UUID, `delegatee_id` UUID, `workflow_key` VARCHAR(255), `request_payload` JSONB, `status` VARCHAR(20) DEFAULT 'pending' (pending|approved|rejected|expired|resolved), `resolved_by` UUID FK users(id), `resolved_via` VARCHAR(20), `resolved_at` TIMESTAMP, `resolution_note` TEXT, `timeout_minutes` INTEGER DEFAULT 15, `expires_at` TIMESTAMP, `created_at`, `updated_at`
- Indexes: on (team_id, status), partial on (status, expires_at) WHERE status='pending', on (connection_id, created_at), on (type, status)

**Table: `agent_connector_settings`** — Per-team configuration
- Columns: `id` UUID PK, `team_id` UUID NOT NULL FK teams(id) UNIQUE, `telemetry_retention_days` INTEGER DEFAULT 90, `enforce_data_residency` BOOLEAN DEFAULT FALSE, `allowed_regions` JSONB DEFAULT '[]', `rate_limit_multiplier` DECIMAL(3,2) DEFAULT 1.00, `approval_timeout_minutes` INTEGER DEFAULT 15, `created_at`, `updated_at`

### 1.3 Teams Table Migration
Create migration `2026_03_09_000002_add_connector_vault_key_to_teams_table.php`:
- Add `connector_vault_key VARCHAR(64) NULL` to `teams` table

### 1.4 Eloquent Models
Create 8 models in `app/Models/` namespace using `HasUuids` trait, `$guarded = []`, cast patterns matching existing codebase:

- `AgentConnector` — casts: industries (array), auth_config (array), rate_limits (array), actions (array), webhooks (array), data_regions (array). Relationships: connections(), credentials(). Scopes: scopeAvailable(), scopeForCategory(), scopeForIndustry()
- `AgentConnectorConnection` — casts: config (array), webhook_subscriptions (array), last_health_check_at (datetime), connected_at (datetime). Relationships: connector(), team(), credentials(), credential(), invocations(), approvals(). Scopes: scopeConnected(), scopeForTeam(), scopeHealthy(), scopeDegraded()
- `AgentConnectorCredential` — casts: scopes_granted (array), token_expires_at (datetime), refresh_token_expires_at (datetime), last_refreshed_at (datetime), last_used_at (datetime). Hidden: encrypted_data, encryption_key_id. Relationships: connection(), connector(), team(), events()
- `AgentConnectorInvocation` — No guarded override needed. Relationships: connection(), connector()
- `AgentConnectorWebhookEvent` — Relationships: connection(), connector()
- `AgentConnectorCredentialEvent` — Relationships: credential()
- `AgentConnectorApproval` — casts: request_payload (array), expires_at (datetime), resolved_at (datetime). Relationships: connection(), connector(), team(), resolvedBy(). Scopes: scopePending(), scopeExpired(), scopeForType()
- `AgentConnectorSetting` — Casts: allowed_regions (array). Relationships: team()

### 1.5 ConnectorVaultEncrypter Service
Create `app/Services/Connectors/ConnectorVaultEncrypter.php`:
- Method: `forTeam(Team $team): Encrypter` — returns `new Encrypter(substr(hash('sha256', $team->connector_vault_key . config('app.key')), 0, 32), 'aes-256-cbc')`
- Method: `encrypt(Team $team, array $data): string` — encrypts credential payload to base64 string
- Method: `decrypt(Team $team, string $encryptedData): array` — decrypts stored credential data
- Throws `ConnectorVaultKeyMissingException` if `$team->connector_vault_key` is null
- Service registered as singleton in `ConnectorServiceProvider`
- Lazy generation: if `connector_vault_key` is null, generate via `Str::random(64)` and persist before first use

### 1.6 ConnectorServiceProvider
Create `app/Providers/ConnectorServiceProvider.php`:
- Register in `bootstrap/providers.php`
- `register()`: bind `ConnectorVaultEncrypter` as singleton, bind `ConnectorRegistryLoader` as singleton, bind `ConnectorResolver` as singleton
- `boot()`: call `ConnectorRegistryLoader::sync()` to idempotently load manifests from disk when `connectors.enabled` flag is true
- Gate-check: only runs sync when `FeatureFlagManager::isEnabled('connectors.enabled')` returns true

### 1.7 ConnectorRegistryLoader
Create `app/Services/Connectors/ConnectorRegistryLoader.php`:
- Method: `sync(): void` — scans `config('connectors.manifest_path')` for `*/connector.json` files, validates each against manifest schema, upserts into `agent_connectors` table keyed by `name`, marks any DB-only connectors (removed from disk) as `status: deprecated`
- Method: `loadManifest(string $path): array` — parses and validates a single connector.json
- Uses `ConnectorManifestValidator` for schema validation
- Idempotent: safe to call on every boot via `updateOrCreate` keyed on `name`

### 1.8 ConnectorManifestValidator
Create `app/Services/Connectors/ConnectorManifestValidator.php`:
- Validates connector.json against required fields: name, display_name, description, version, auth (type required), base_url, actions (non-empty array)
- Validates each action has: name, method, path
- Returns `ValidationResult` DTO with errors array

### 1.9 Extension Point Interfaces (Phase 2 SDK Preparation)
Create contracts in `app/Contracts/Connectors/`:
- `ConnectorInterface` — methods: `authenticate(Team $team, array $config): AuthResult`, `execute(string $action, array $params): ActionResult`, `healthCheck(): HealthCheckResult`, `listActions(): array`
- `ConnectorAuthProvider` — methods: `initiateAuth(Team $team, array $config): AuthInitResult`, `handleCallback(array $callbackData): AuthResult`, `refreshCredential(AgentConnectorCredential $credential): RefreshResult`, `revokeCredential(AgentConnectorCredential $credential): void`
- `ConnectorActionHandler` — abstract class with hooks: `transformRequest(array $params): array`, `normalizeResponse(array $raw): array`, `mapError(Throwable $e): ConnectorError`

### 1.10 ConnectorPluginLoader
Create `app/Services/Connectors/ConnectorPluginLoader.php`:
- Discovers and registers connector bundles from configurable directories
- Phase 1: loads built-in connectors only from `connectors/` directory
- Extension point for Phase 2 SDK to register external connector directories

### 1.11 Feature Flag Registration
Add 6 constants and definitions to `app/Support/Agent/FeatureFlagManager.php`:
- `CONNECTORS_ENABLED = 'connectors.enabled'`
- `CONNECTORS_UI_ENABLED = 'connectors.ui_enabled'`
- `CONNECTORS_WEBHOOKS_ENABLED = 'connectors.webhooks_enabled'`
- `CONNECTORS_AUTO_RESOLVE = 'connectors.auto_resolve'`
- `CONNECTORS_WRITE_ACTIONS = 'connectors.write_actions'`
- `CONNECTORS_CREDENTIAL_REFRESH = 'connectors.credential_refresh'`
- Add `getConnectorsFlags(): array` static method returning all 6 keys
- Add DEFINITIONS entries with label and description for each flag

### 1.12 Horizon Supervisor Configuration
Add 3 new supervisors to `config/horizon.php` defaults, production environments, and local environments:

**supervisor-connector-credentials**:
- Queue: `connector-credentials`, balance: auto, maxProcesses: `max(1, min(4, (int) env('HORIZON_CONNECTOR_CREDS_MAX_PROCESSES', 2)))`, tries: 3, backoff: [10, 30, 60], timeout: 60
- Production: balanceMaxShift: 1, balanceCooldown: 3
- Add `'redis:connector-credentials' => 30` to waits

**supervisor-connector-webhooks**:
- Queue: `connector-webhooks`, balance: auto, maxProcesses: `max(1, min(8, (int) env('HORIZON_CONNECTOR_WEBHOOKS_MAX_PROCESSES', 4)))`, tries: 3, backoff: [5, 15, 45], timeout: 30
- Production: balanceMaxShift: 1, balanceCooldown: 3
- Add `'redis:connector-webhooks' => 10` to waits

**supervisor-connector-approvals**:
- Queue: `connector-approvals`, balance: auto, maxProcesses: `max(1, min(2, (int) env('HORIZON_CONNECTOR_APPROVALS_MAX_PROCESSES', 1)))`, tries: 3, backoff: [5, 15, 30], timeout: 30
- Production: balanceMaxShift: 1, balanceCooldown: 3
- Add `'redis:connector-approvals' => 30` to waits

### 1.13 OAuth 2.0 Flow Infrastructure
Create `app/Services/Connectors/Auth/OAuthFlowManager.php`:
- Method: `initiateAuthorizationCodeFlow(AgentConnector $connector, Team $team, string $alias): string` — generates PKCE code verifier/challenge, stores state in cache with 10-minute TTL keyed by random state param, returns authorization URL with `response_type=code`, `client_id`, `redirect_uri`, `scope`, `state`, `code_challenge`, `code_challenge_method=S256`
- Method: `handleCallback(string $state, string $code): AgentConnectorConnection` — validates state from cache, exchanges code for tokens via POST to token_url, encrypts tokens via `ConnectorVaultEncrypter`, creates credential record, updates connection status to `connected`
- Method: `initiateClientCredentialsFlow(AgentConnector $connector, Team $team, string $alias, array $clientConfig): AgentConnectorConnection` — direct token exchange, no browser redirect
- OAuth callback route: `GET /agent/api/v1/connectors/callback` — extracts state and code, delegates to `OAuthFlowManager::handleCallback()`

Create `app/Services/Connectors/Auth/ApiKeyAuthManager.php`:
- Method: `connect(AgentConnector $connector, Team $team, string $alias, string $apiKey, ?string $apiSecret = null): AgentConnectorConnection` — encrypts key via vault, creates connection + credential records
- Method: `connectBasicAuth(AgentConnector $connector, Team $team, string $alias, string $username, string $password): AgentConnectorConnection`
- Method: `connectCustomHeader(AgentConnector $connector, Team $team, string $alias, string $headerName, string $headerValue): AgentConnectorConnection`

### 1.14 Connection Lifecycle Service
Create `app/Services/Connectors/ConnectionLifecycleService.php`:
- Method: `connect(string $connectorName, Team $team, string $alias, array $authParams): AgentConnectorConnection` — orchestrates connection flow based on auth_type, generates `connector_vault_key` if needed, creates `AgentConnectorSetting` if first connection for team
- Method: `disconnect(AgentConnectorConnection $connection, User $actor): void` — revokes token at provider if supported, deletes encrypted credentials, updates connection status to `disconnected`, logs credential event
- Method: `test(AgentConnectorConnection $connection): HealthCheckResult` — makes lightweight API call to verify credentials are valid, returns structured result
- Validates: connector not `partnership_pending`, not `requires_static_ip` in local-first mode without tunnel

### 1.15 CLI Commands (Foundation subset)
Create artisan commands in `app/Console/Commands/Connectors/`:
- `ConnectorListCommand` (`connector:list`) — lists all available connectors with status, category, auth type. Output: table format
- `ConnectorConnectCommand` (`connector:connect {name} [--api-key=] [--alias=]`) — initiates connection; for OAuth opens browser with auth URL; for API key accepts via option
- `ConnectorDisconnectCommand` (`connector:disconnect {name} [--alias=]`) — disconnects with confirmation prompt
- `ConnectorTestCommand` (`connector:test {name} [--alias=]`) — runs health check, outputs result
- `ConnectorHealthCommand` (`connector:health`) — summary grid of all connected services with health scores
- `ConnectorActionsCommand` (`connector:actions {name}`) — lists available actions for a connector
- `ConnectorSyncCommand` (`connector:sync`) — manually triggers `ConnectorRegistryLoader::sync()`

### 1.16 Foundation Tests
Create test files in `tests/Feature/Connectors/` and `tests/Unit/Connectors/`:
- `ConnectorVaultEncrypterTest` — per-team encryption/decryption, key derivation correctness, missing key exception, cross-team isolation
- `ConnectorRegistryLoaderTest` — manifest parsing, upsert idempotency, deprecated marking for removed manifests
- `ConnectorManifestValidatorTest` — valid/invalid manifest validation
- `ConnectionLifecycleServiceTest` — connect/disconnect/test flows for OAuth and API key auth types
- `OAuthFlowManagerTest` — PKCE generation, state management, callback handling
- `ApiKeyAuthManagerTest` — API key encryption and credential creation
- `FeatureFlagManagerConnectorsTest` — 6 connector flags registered and functional
- All tests use `Http::fake()` for external calls and factory-generated models

---

## Phase 2: Connector Runtime — Execution Pipeline & Resilience

Depends on: Phase 1 complete

### 2.1 ConnectorResolver
Create `app/Services/Connectors/Runtime/ConnectorResolver.php`:
- Method: `resolve(string $connectorName, Team $team, ?string $alias = null): ResolvedConnection` — validates connector exists and is connected, checks health_score, handles alias resolution
- When multiple connections exist and no alias provided: creates clarification request via `ApprovalService`, throws `ClarificationRequiredException` with approval ID
- Validates `requires_static_ip` against tunnel configuration
- Returns `ResolvedConnection` DTO containing connection, connector, and credential references

### 2.2 PolicyGate
Create `app/Services/Connectors/Runtime/PolicyGate.php`:
- Method: `authorize(ResolvedConnection $resolved, string $action, ?string $delegateeId): void`
- Checks: is action read-only (always allowed) or write (requires `connectors.write_actions` flag)
- For write actions: retrieves delegatee trust score from `TrustScoreCalculator`, compares against `write_trust_threshold` from connector manifest
- For risk-elevated connectors: applies higher threshold
- Checks data residency: if team has `enforce_data_residency`, verifies connector `data_regions` against team `allowed_regions`
- Throws `ConnectorPolicyDeniedException` with reason on failure

### 2.3 ApprovalGate
Create `app/Services/Connectors/Runtime/ApprovalGate.php`:
- Method: `checkApproval(ResolvedConnection $resolved, string $action, array $payload, ?string $runAttemptId, ?string $delegateeId, ?string $workflowKey): ?AgentConnectorApproval`
- Checks if action is in connector's `requires_approval_for` list
- If approval required: creates `AgentConnectorApproval` record with type `approval`, dispatches notification via dual-channel (messenger + dashboard), returns approval record
- Method: `awaitResolution(AgentConnectorApproval $approval): ApprovalResult` — polls for resolution or timeout

### 2.4 ApprovalService
Create `app/Services/Connectors/Runtime/ApprovalService.php`:
- Method: `createApproval(...)` — creates approval record, calculates `expires_at` from team's `approval_timeout_minutes`, dispatches `ExpireConnectorApprovalJob` delayed by timeout
- Method: `createClarification(Team $team, AgentConnector $connector, array $connectionOptions, ...)` — creates clarification record with connection options in `request_payload`
- Method: `resolve(AgentConnectorApproval $approval, User $resolver, string $resolution, ?string $note, ?string $via): void` — updates approval status, sets resolved_by/at/via
- Dual-channel delivery: sends to messenger (inline buttons) and creates dashboard notification; first response wins

### 2.5 ExpireConnectorApprovalJob
Create `app/Jobs/Connectors/ExpireConnectorApprovalJob.php`:
- Queue: `connector-approvals`
- Dispatched with delay matching `timeout_minutes`
- Checks if approval is still `pending`, marks as `expired` if so
- Handles both `approval` and `clarification` types

### 2.6 CredentialManager
Create `app/Services/Connectors/Runtime/CredentialManager.php`:
- Method: `getDecryptedCredentials(AgentConnectorConnection $connection): array` — retrieves credential, decrypts via `ConnectorVaultEncrypter`, updates `last_used_at`
- Method: `buildAuthHeaders(array $credentials, string $authType): array` — constructs HTTP auth headers based on auth type (Bearer token, API key header, Basic auth, custom header)

### 2.7 ConnectorRateLimiter
Create `app/Services/Connectors/Runtime/ConnectorRateLimiter.php`:
- Token bucket algorithm using Redis
- Method: `attempt(AgentConnector $connector, Team $team): RateLimitResult` — checks per-connector limits from manifest × per-team `rate_limit_multiplier` from settings
- Redis keys: `connector_rate:{connector_id}:{team_id}:minute`, `connector_rate:{connector_id}:{team_id}:daily`
- Returns `RateLimitResult` with `allowed`, `remaining`, `retryAfterSeconds`
- Composes with existing delegation concurrency limits

### 2.8 ConnectorHttpClient
Create `app/Services/Connectors/Runtime/ConnectorHttpClient.php`:
- Wraps Laravel `Http` client with connector-specific configuration
- Method: `execute(AgentConnector $connector, string $action, array $params, array $authHeaders): HttpResponse`
- Retry: 3 attempts with exponential backoff (1s, 2s, 4s) + jitter on 429/500/502/503/504; no retry on 401/403/404/422
- Circuit breaker: tracks failures in Redis key `connector_circuit:{connector_id}:{team_id}`, 5 failures in 60s opens circuit for 120s
- Timeout: per-action timeout from manifest, default 30s
- When circuit is open: returns structured `ConnectorCircuitOpenException` — no cached responses

### 2.9 ResponseNormalizer
Create `app/Services/Connectors/Runtime/ResponseNormalizer.php`:
- Method: `normalize(array $rawResponse, array $actionSchema): array` — maps provider response to action's declared response_schema
- Handles pagination metadata extraction
- Handles error response mapping to connector error taxonomy

### 2.10 PiiRedactor
Create `app/Services/Connectors/Runtime/PiiRedactor.php`:
- Method: `redact(array $response, array $piiFields, string $mode): array` — strips PII fields based on mode
- Mode `memory_only`: redacts before memory formation only (default)
- Mode `context_and_memory`: redacts before returning to agent context AND before memory formation
- Uses dot-notation field paths from action's `pii_fields` declaration (e.g., `Contact.EmailAddress`)

### 2.11 TelemetryEmitter
Create `app/Services/Connectors/Runtime/TelemetryEmitter.php`:
- Method: `emitInvoked(...)` — creates `AgentConnectorInvocation` record with outcome pending
- Method: `emitCompleted(AgentConnectorInvocation $invocation, int $httpStatus, int $durationMs, ...)` — updates invocation, emits `connector.completed` to telemetry event ledger
- Method: `emitFailed(AgentConnectorInvocation $invocation, string $outcome, ?string $errorMessage, ...)` — updates invocation, emits `connector.failed`
- Method: `emitWebhookReceived(...)` — emits `connector.webhook_received`
- Health score write-back: after each invocation, recalculates lightweight health score and updates `agent_connector_connections.health_score`

### 2.12 ConnectorHealthScorer
Create `app/Services/Connectors/Runtime/ConnectorHealthScorer.php`:
- Method: `calculate(AgentConnectorConnection $connection): float` — full recalculation using formula: credential_health (40%) + error_rate (30%) + latency (15%) + rate_limit_headroom (15%)
- Method: `quickUpdate(AgentConnectorConnection $connection, string $outcome, int $durationMs): float` — lightweight post-invocation update using rolling averages
- credential_health: 1.0 for active, 0.5 for degraded, 0.0 for expired/revoked
- error_rate: 1.0 - (5xx_count / total_count) over rolling 1-hour window
- latency: 1.0 if p95 < declared SLA, scaled down proportionally
- rate_limit_headroom: remaining_quota / daily_limit
- Status mapping: ≥0.8 healthy, ≥0.5 degraded, <0.5 unhealthy

### 2.13 ConnectorExecutionPipeline
Create `app/Services/Connectors/ConnectorExecutionPipeline.php`:
- Method: `execute(string $connectorName, string $action, array $params, Team $team, ?string $alias, ?string $delegateeId, ?string $runAttemptId, ?string $workflowKey): ConnectorActionResult`
- Orchestrates the full pipeline: ConnectorResolver → PolicyGate → ApprovalGate → CredentialManager → RateLimiter → HttpClient → ResponseNormalizer → PiiRedactor → TelemetryEmitter
- Returns `ConnectorActionResult` DTO with response data, metadata, health score
- All exceptions are caught and mapped to connector error taxonomy

### 2.14 Connector Error Taxonomy
Create error classes in `app/Exceptions/Connectors/`:
- `ConnectorException` — base class
- `ConnectorAuthFailedException` — maps to existing `AUTH_UNAUTHENTICATED`
- `ConnectorPolicyDeniedException` — trust score or data residency violation
- `ConnectorRateLimitedException` — rate limit exceeded with retry-after
- `ConnectorCircuitOpenException` — circuit breaker open
- `ConnectorTimeoutException` — action timeout
- `ConnectorValidationException` — maps to `VALIDATION_FAILED`
- `ClarificationRequiredException` — multiple connections, alias needed
- `ApprovalRequiredException` — write action requires approval
- `ConnectorVaultKeyMissingException` — team has no vault key

### 2.15 Credential Refresh Job
Create `app/Jobs/Connectors/RefreshConnectorCredentialJob.php`:
- Queue: `connector-credentials`
- Tries: 3, backoff: [10, 30, 60]
- Dispatched by scheduled command for credentials expiring within 5-minute buffer
- Refreshes OAuth tokens using `refresh_token`, encrypts new tokens, updates credential record
- On success: logs `refreshed` event, resets `refresh_failure_count`
- On exhaustion: marks credential `degraded`, logs `refresh_failed` event, dispatches operator alert notification

### 2.16 Credential Refresh Scheduler
Add to `routes/console.php` (or `app/Console/Kernel.php`):
- Schedule `RefreshConnectorCredentialJob` dispatch every minute for credentials where `token_expires_at` is within `credential_refresh_buffer_seconds` and status is `active`
- Gated by `connectors.credential_refresh` feature flag

### 2.17 Cost Attribution Integration
Create `app/Services/Connectors/Runtime/ConnectorCostAttributor.php`:
- Method: `attribute(AgentConnectorInvocation $invocation): void` — records connector API cost against invoking workflow's budget via existing `WorkflowBudgetEnforcer`
- Reads cost_model from connector manifest: `free` (no cost), `metered` (per-call cost), `quota-limited` (daily quota tracking)
- Integrates with existing `OrgCostLedger` for org-layer cost tracking

### 2.18 Runtime Tests
- `ConnectorResolverTest` — single connection, multi-connection alias resolution, clarification flow, static IP blocking
- `PolicyGateTest` — trust score checks, read vs write, data residency enforcement
- `ApprovalGateTest` — approval creation, dual-channel delivery, timeout handling
- `ConnectorRateLimiterTest` — token bucket algorithm, team multiplier, limit exhaustion
- `ConnectorHttpClientTest` — retry behavior, circuit breaker, timeout (all using Http::fake())
- `ConnectorExecutionPipelineTest` — full pipeline integration with mocked dependencies
- `PiiRedactorTest` — field redaction in both modes
- `ConnectorHealthScorerTest` — weighted formula correctness, status thresholds
- `RefreshConnectorCredentialJobTest` — refresh success, failure, degraded marking

---

## Phase 3: Tier 1 Connectors (13 Connectors)

Depends on: Phase 2 complete

### 3.1 Connector Manifest Directory Structure
Create `connectors/` directory at project root with subdirectories per connector:
```
connectors/
├── xero/connector.json
├── sage/connector.json
├── salesforce/connector.json
├── hubspot/connector.json
├── microsoft-teams/connector.json
├── slack/connector.json
├── google-workspace/connector.json
├── microsoft-365/connector.json
├── stripe/connector.json
├── gocardless/connector.json
├── docusign/connector.json
├── monday/connector.json
└── notion/connector.json
```

### 3.2 OAuth 2.0 Connectors (9)
For each OAuth connector, create `connector.json` manifest with:
- Auth config: authorization_url, token_url, scopes, refresh_strategy: auto, token_expiry_seconds
- PKCE support declaration
- Rate limits from provider documentation
- Actions with request/response schemas and pii_fields declarations
- `requires_approval_for` array for write/mutate actions

**Xero**: OAuth 2.0 with PKCE. Actions: list-invoices, create-invoice, update-invoice, get-contact, list-contacts, create-contact, list-bank-transactions, get-reports, list-payments. write_trust_threshold: 0.7. pii_fields on contacts.

**Salesforce**: OAuth 2.0. Actions: query (SOQL), create-lead, update-lead, get-contact, create-opportunity, update-opportunity, list-accounts, create-task. write_trust_threshold: 0.7.

**HubSpot**: OAuth 2.0. Actions: list-contacts, create-contact, update-contact, list-companies, list-deals, create-deal, list-tickets, create-ticket. write_trust_threshold: 0.7.

**Microsoft Teams**: OAuth 2.0 (Microsoft Identity Platform). Actions: send-message, list-channels, post-adaptive-card, read-channel-messages. write_trust_threshold: 0.7.

**Slack**: OAuth 2.0. Actions: send-message, list-channels, upload-file, add-reaction, reply-in-thread. write_trust_threshold: 0.7.

**Google Workspace**: OAuth 2.0. Actions: gmail-list, gmail-send, drive-list, drive-read, drive-upload, calendar-list-events, calendar-create-event, sheets-read, sheets-write. write_trust_threshold: 0.7. pii_fields on Gmail content.

**Microsoft 365**: OAuth 2.0 (Microsoft Graph). Actions: outlook-list, outlook-send, onedrive-list, onedrive-read, onedrive-upload, calendar-list, sharepoint-list, sharepoint-read. write_trust_threshold: 0.7.

**GoCardless**: OAuth 2.0. Actions: list-mandates, create-mandate, list-payments, create-payment, list-subscriptions, list-payouts. write_trust_threshold: 0.8 (financial).

**DocuSign**: OAuth 2.0. Actions: create-envelope, send-envelope, check-status, download-signed, list-envelopes. write_trust_threshold: 0.8.

**Notion**: OAuth 2.0. Actions: query-database, create-page, update-page, search. write_trust_threshold: 0.7.

### 3.3 API Key Connectors (3)
For each API key connector, create `connector.json` manifest:

**Sage 50/200**: API Key. Actions: list-invoices, create-invoice, list-customers, create-customer, list-products, list-stock, create-purchase-order. write_trust_threshold: 0.7.

**Stripe**: API Key. Actions: list-charges, create-charge, list-customers, create-customer, list-invoices, create-invoice, create-payment-intent, list-subscriptions. write_trust_threshold: 0.8 (financial).

**Monday.com**: API Key (GraphQL). Actions: list-boards, create-board, list-items, create-item, update-item, list-columns, list-groups. write_trust_threshold: 0.7.

### 3.4 Test Fixtures
For each connector, create mock response fixtures in `tests/Fixtures/Connectors/{name}/`:
- `{action-name}-success.json` — successful response
- `{action-name}-error-{code}.json` — common error responses (401, 403, 404, 422, 429, 500)
- Tests in `tests/Feature/Connectors/Tier1/` using `Http::fake()` with fixture sequences

### 3.5 Tier 1 Integration Tests
- `XeroConnectorTest` — OAuth flow, invoice CRUD, contact operations
- `SalesforceConnectorTest` — OAuth flow, SOQL query, lead management
- `StripeConnectorTest` — API key auth, charge creation, customer management
- One test per connector verifying: authentication, at least one read action, at least one write action (if applicable), error handling for common HTTP statuses
- All tests use `Http::fake()` — no live API calls

---

## Phase 4: Authorization & REST API Surface

Depends on: Phase 2 complete

### 4.1 ConnectorPolicy
Create `app/Policies/ConnectorPolicy.php`:
- 3 abilities matching existing policy pattern from `AgentJobPolicy`:
- `viewConnectorTelemetry(User $user)` — returns true if user is team admin or has operator role (check via `$user->hasTeamRole($team, 'admin')` or operator role)
- `manageConnectors(User $user)` — connect, disconnect, test, configure, approve/reject. Admin or operator.
- `adminConnectors(User $user)` — rotate credentials, configure webhooks, rate limit overrides. Admin only.
- Register in `AuthServiceProvider` or `ConnectorServiceProvider::boot()` via `Gate::policy()`

### 4.2 API Controllers
Create controllers in `app/Http/Controllers/Api/V1/Connectors/`:

**ConnectorRegistryController**:
- `index()` — GET `/connectors` — list available connectors with category/industry filters. Policy: any authenticated user.
- `show($id)` — GET `/connectors/{id}` — connector detail with actions list. Policy: any authenticated user.
- `actions($id)` — GET `/connectors/{id}/actions` — list available actions for connector.

**ConnectorConnectionController**:
- `connect($id)` — POST `/connectors/{id}/connect` — initiate OAuth flow (returns redirect URL) or accept API key. Policy: `manageConnectors`. Request validation: alias (optional string), api_key (conditional), api_secret (conditional).
- `disconnect($id, $connectionId)` — DELETE `/connectors/{id}/connections/{connectionId}` — disconnect and revoke. Policy: `manageConnectors`. Confirmation required.
- `test($id, $connectionId)` — POST `/connectors/{id}/connections/{connectionId}/test` — health check. Policy: `manageConnectors`.
- `health($id, $connectionId)` — GET `/connectors/{id}/connections/{connectionId}/health` — detailed health info.
- `telemetry($id, $connectionId)` — GET `/connectors/{id}/connections/{connectionId}/telemetry` — invocation history. Policy: `viewConnectorTelemetry`.

**ConnectorActionController**:
- `execute($id, $connectionId, $action)` — POST `/connectors/{id}/connections/{connectionId}/actions/{action}` — execute action via pipeline. Policy: `manageConnectors`.

**ConnectorWebhookController**:
- `subscribe($id, $connectionId)` — POST `/connectors/{id}/connections/{connectionId}/webhooks` — register webhook. Policy: `adminConnectors`. Gated by `connectors.webhooks_enabled`.
- `unsubscribe($id, $connectionId, $event)` — DELETE `/connectors/{id}/connections/{connectionId}/webhooks/{event}` — remove subscription. Policy: `adminConnectors`.

**ConnectorCallbackController**:
- `callback()` — GET `/connectors/callback` — OAuth callback handler. No auth required (state-validated).

**ConnectorApprovalController**:
- `index()` — GET `/connectors/approvals` — list pending approvals for team. Policy: `manageConnectors`.
- `resolve($id)` — POST `/connectors/approvals/{id}/resolve` — approve/reject/select. Policy: `manageConnectors`. Request: resolution (approved|rejected|resolved), note (optional), selected_connection_id (for clarifications).

### 4.3 Route Registration
Add connector routes to `routes/api.php` within the existing `auth:sanctum` + `license` middleware group:
```php
Route::prefix('connectors')->group(function (): void {
    // Registry (read-only, any authenticated user)
    Route::get('/', [ConnectorRegistryController::class, 'index']);
    Route::get('/callback', [ConnectorCallbackController::class, 'callback'])->withoutMiddleware(['auth:sanctum', 'license']);
    Route::get('/approvals', [ConnectorApprovalController::class, 'index']);
    Route::post('/approvals/{id}/resolve', [ConnectorApprovalController::class, 'resolve'])->middleware('throttle:agent-mutations');
    Route::get('/{id}', [ConnectorRegistryController::class, 'show']);
    Route::get('/{id}/actions', [ConnectorRegistryController::class, 'actions']);
    Route::post('/{id}/connect', [ConnectorConnectionController::class, 'connect'])->middleware('throttle:agent-mutations');
    
    // Connection-scoped routes
    Route::delete('/{id}/connections/{connectionId}', [ConnectorConnectionController::class, 'disconnect'])->middleware('throttle:agent-mutations');
    Route::post('/{id}/connections/{connectionId}/test', [ConnectorConnectionController::class, 'test'])->middleware('throttle:agent-mutations');
    Route::get('/{id}/connections/{connectionId}/health', [ConnectorConnectionController::class, 'health']);
    Route::get('/{id}/connections/{connectionId}/telemetry', [ConnectorConnectionController::class, 'telemetry']);
    Route::post('/{id}/connections/{connectionId}/actions/{action}', [ConnectorActionController::class, 'execute'])->middleware('throttle:agent-mutations');
    Route::post('/{id}/connections/{connectionId}/webhooks', [ConnectorWebhookController::class, 'subscribe'])->middleware('throttle:agent-mutations');
    Route::delete('/{id}/connections/{connectionId}/webhooks/{event}', [ConnectorWebhookController::class, 'unsubscribe'])->middleware('throttle:agent-mutations');
});
```

### 4.4 Form Request Validation
Create request classes in `app/Http/Requests/Connectors/`:
- `ConnectConnectorRequest` — validates alias (nullable string max:128), api_key (required_if auth_type is api_key), api_secret (nullable)
- `ResolveApprovalRequest` — validates resolution (required, in:approved,rejected,resolved), note (nullable string), selected_connection_id (required_if type is clarification, uuid)
- `ExecuteConnectorActionRequest` — validates params (nullable array)

### 4.5 API Resource Transformers
Create resources in `app/Http/Resources/Connectors/`:
- `ConnectorResource` — transforms AgentConnector for API response (excludes auth_config secrets)
- `ConnectionResource` — transforms AgentConnectorConnection with health status
- `InvocationResource` — transforms AgentConnectorInvocation for telemetry response
- `ApprovalResource` — transforms AgentConnectorApproval

### 4.6 API Tests
- `ConnectorRegistryControllerTest` — list, show, filter by category/industry
- `ConnectorConnectionControllerTest` — connect (OAuth + API key), disconnect, test, health
- `ConnectorActionControllerTest` — execute action, policy enforcement, rate limiting
- `ConnectorApprovalControllerTest` — list, approve, reject, clarification resolution
- `ConnectorPolicyTest` — 3 abilities with admin, operator, and regular user roles

---

## Phase 5: UI & Operator Dashboard Integration

Depends on: Phase 4 complete

### 5.1 Frontend Navigation
- Add "Connectors" entry to the Tools settings section navigation in the existing sidebar/navigation component
- Navigation entry gated by `connectors.ui_enabled` feature flag
- Route: `/tools/connectors`
- Icon: consistent with existing Tools section iconography

### 5.2 Connector Library Page
- URL: `/tools/connectors` (default view)
- Components: category filter sidebar, industry filter, search input, connector cards grid
- Each card shows: icon, display_name, description, category badge, connection status indicator, "Connect" button (or "Connected" badge with count of active connections)
- Partnership-pending connectors shown with "Coming Soon" badge, connect button disabled
- Gated by `connectors.ui_enabled` feature flag
- **Discoverability acceptance check**: Connector Library page accessible via Tools section sidebar navigation; page loads connector list from `GET /agent/api/v1/connectors`; category and industry filters functional; search by name operational

### 5.3 Connected Services Page
- URL: `/tools/connectors/connected`
- Components: grid/list toggle, health status indicators (green/yellow/red), last action time, credential expiry countdown, request count (24h), error rate (24h), action buttons (disconnect, test, reconfigure)
- Each connected service row shows: connector icon, display_name, alias, health score badge, connection status
- Quick actions: test connection (inline), disconnect (confirmation modal)
- **Discoverability acceptance check**: Connected Services view accessible as tab/sub-nav within `/tools/connectors`; shows real-time health status pulled from `GET /connectors/{id}/connections/{connectionId}/health`; test button triggers `POST /test` and shows result inline

### 5.4 Connector Detail Page
- URL: `/tools/connectors/{id}`
- Sections: overview (metadata, auth type, rate limits), connections (list of active connections with alias), available actions (table with name, method, stability tier, approval requirement), telemetry history (invocation chart), webhook subscriptions (if enabled)
- Connection management: connect new instance (with alias input), view per-connection health, rotate credentials (admin only)
- **Discoverability acceptance check**: Detail page accessible by clicking connector card from library; shows connector metadata, actions list, and per-connection telemetry

### 5.5 OAuth Flow UI
- Connect button for OAuth connectors opens OAuth redirect in new window/tab
- Callback page (`/tools/connectors/callback`) shows success/failure state after OAuth completes
- Error states: provider denied, invalid state, token exchange failure — each with user-friendly message and retry option
- **Discoverability acceptance check**: OAuth connect flow initiates browser redirect; callback page renders success with connection details or error with retry link

### 5.6 Approval & Clarification UI
- Approvals list accessible from `/tools/connectors/approvals` or via notification bell
- Each approval row: connector name, action name, requester, timestamp, expires countdown, approve/reject buttons
- Clarification rows: show list of available connections as selectable options
- Dashboard notification badge increments for pending approvals
- **Discoverability acceptance check**: Pending approvals visible in connector approvals page and via dashboard notification; approve/reject buttons functional; clarification presents connection selection UI

### 5.7 Dashboard Widgets
- **ConnectorHealth widget**: health status grid for all connected services showing green/yellow/red indicators, credential expiry countdown. Added to existing operator dashboard surface.
- **ConnectorUsage widget**: top connectors by invocation count, average latency, success rate (24h rolling). Added to operator dashboard.
- Extend existing `ReliabilityScore` widget: include connector-attributed failure rate contribution
- Extend existing `BudgetUtilization` widget: include external API cost attribution per workflow
- Extend existing `EscalationEvents` widget: include connector-triggered escalations
- **Discoverability acceptance check**: ConnectorHealth and ConnectorUsage widgets visible on operator dashboard when `connectors.ui_enabled` is active; widgets show live data from connector telemetry

### 5.8 Operator Alerts
Configure alert rules in operator surface:
- Credential expiring (7 days, auto-refresh failed) — Warning severity
- Credential expired — Critical severity
- Rate limit approaching (>80% daily quota) — Warning severity
- Rate limit exhausted (100%) — Critical severity
- Connector degraded (health score <0.5) — Warning severity
- Webhook delivery failures (>50% failure rate over 1 hour) — Warning severity
- Alerts delivered via existing notification infrastructure (dashboard + messenger system notifications)
- **Discoverability acceptance check**: Alert rules configurable in operator settings; triggered alerts appear in notification feed with connector context

### 5.9 UI Feature Tests
- Test connector library page renders with mocked connector data
- Test connected services page shows health indicators
- Test OAuth flow redirect and callback rendering
- Test approval/clarification UI interactions
- Test dashboard widgets render with telemetry data

---

## Phase 6: Webhook Ingestion & Tier 2 Connectors

Depends on: Phase 3 complete, Phase 4 complete

### 6.1 Webhook Ingestion Endpoint
Create `app/Http/Controllers/Api/V1/Connectors/ConnectorWebhookIngestController.php`:
- Route: `POST /agent/api/v1/connectors/{connectorName}/webhooks/{event}` — public endpoint, no auth middleware
- Signature verification: reads provider-specific signature header, verifies HMAC using webhook secret from connection config
- Deduplication: checks `(external_event_id, connector_id)` uniqueness before processing
- Creates `AgentConnectorWebhookEvent` record with `processing_status: received`
- Dispatches `ProcessConnectorWebhookJob`

### 6.2 Webhook Signature Verifiers
Create `app/Services/Connectors/Webhooks/` with per-provider verifiers:
- `WebhookSignatureVerifier` — interface with `verify(Request $request, string $secret): bool`
- `HmacSha256Verifier` — generic HMAC-SHA256 (Stripe, Xero pattern)
- `SlackWebhookVerifier` — Slack-specific signature verification
- `CustomVerifier` — configurable header name and algorithm per connector manifest

### 6.3 ProcessConnectorWebhookJob
Create `app/Jobs/Connectors/ProcessConnectorWebhookJob.php`:
- Queue: `connector-webhooks`
- Normalizes webhook payload to internal event schema
- Routes to matching workflow/job trigger based on event type and connector
- Updates `processing_status` to `routed` on success
- On routing failure (no matching workflow): updates to `dead_letter`
- Emits `connector.webhook_received` telemetry event

### 6.4 Dead Letter Queue
- Failed/unmatched webhook events stored with `processing_status: dead_letter`
- 30-day retention enforced by `connector:prune-telemetry` command
- Manual replay: `ReplayWebhookEventCommand` or API endpoint for operators

### 6.5 Tunnel Integration for Webhooks
- Read tunnel configuration from `config/connectors.php` webhook settings
- When `webhook_tunnel_provider` is `cloudflare`: use existing `TunnelSetting` hostname as webhook base URL
- When `none`: require `webhook_base_url` to be explicitly configured (deployed mode)
- `ConnectorResolver` blocks webhook subscription in local-first mode without tunnel
- Webhook registration: when subscribing, sends registration request to provider's webhook API with the resolved public URL

### 6.6 Tier 2 Connector Manifests (14 Connectors)
Create connector.json manifests for each Tier 2 connector under `connectors/`:

**OAuth 2.0** (9): Breathe HR, Zendesk, Mailchimp, Calendly, Jira, Asana, QuickBooks Online, FreeAgent, Shopify, Dropbox
**API Key** (4): BrightHR, Freshdesk, ActiveCampaign, Trello

Each manifest follows the same structure as Tier 1 with appropriate auth config, actions, rate limits, pii_fields, and write_trust_threshold.

### 6.7 Tier 2 Test Fixtures
Create mock response fixtures in `tests/Fixtures/Connectors/` for each Tier 2 connector.
Create tests in `tests/Feature/Connectors/Tier2/` — one test class per connector verifying auth, read, and write operations with `Http::fake()`.

### 6.8 Webhook Tests
- `ConnectorWebhookIngestControllerTest` — signature verification, deduplication, event creation
- `ProcessConnectorWebhookJobTest` — event normalization, routing, dead-letter
- `WebhookSignatureVerifierTest` — HMAC verification for multiple provider patterns

---

## Phase 7: Tier 3 Vertical Connectors

Depends on: Phase 6 complete

### 7.1 API-Accessible Tier 3 Connectors
Create connector.json manifests for connectors with publicly available APIs:

**IT/MSP**: ConnectWise Manage (API Key + Secret), Datto Autotask (API Key)
**Healthcare/Care**: CareLineLive (API Key — partnership pending, status: partnership_pending)
**Facilities**: ServiceM8 (OAuth 2.0), Simpro (API Key)
**Logistics**: Brightpearl (OAuth 2.0), ShipStation (API Key)
**Construction**: Procore (OAuth 2.0), Sage Construction (API Key)
**Property**: Reapit (OAuth 2.0)
**Recruitment**: Bullhorn (OAuth 2.0), Vincere (API Key)
**Legal**: Clio (OAuth 2.0), LEAP (API Key)
**Insurance**: Open GI (API Key)

### 7.2 Partnership-Pending Connectors
Create minimal connector.json manifests with `status: partnership_pending` for:
- Acturis (Insurance) — `partnership_notes`: "Requires partnership agreement. Contact partnerships@acturis.com"
- Alto (Property/Zoopla) — `partnership_notes`: "API access requires Zoopla partner agreement"
- Person Centred Software (Healthcare/Care) — `partnership_notes`: "Requires partnership for API access"
- CareLineLive (Healthcare/Care) — `partnership_notes`: "API access being negotiated"

These appear in registry/UI with "Coming Soon" badge but cannot be connected.

### 7.3 Tier 3 Test Fixtures
Create mock response fixtures and tests in `tests/Feature/Connectors/Tier3/` for all API-accessible Tier 3 connectors.
Partnership-pending connectors get a single test verifying they appear in registry with correct status and are blocked from connection attempts.

---

## Phase 8: Messenger Integration & CLI Completion

Depends on: Phase 4 complete

### 8.1 Messenger Chat Commands
Register connector commands in existing `CommandRouter` / `SlashCommandRegistrar`:

- `connect {service} [alias]` — initiates connection with instructions (OAuth link or API key prompt). Uses existing messenger conversation flow. OAuth: sends auth URL as clickable link. API key: prompts user to DM the key (never in group channels).
- `list connections` / `my connections` — shows connected services with status, alias, health indicator. Formatted as messenger-native card/embed.
- `test {service} [alias]` — runs `ConnectionLifecycleService::test()`, reports result inline.
- `disconnect {service} [alias]` — creates `PendingConfirmation` (existing pattern), requires user confirmation before executing disconnect.

### 8.2 Approval Delivery via Messenger
Extend existing `SystemNotificationDispatcher` to deliver connector approval/clarification notifications:
- Approval requests: send message with connector name, action name, request details, and inline approve/reject buttons using existing messenger button infrastructure
- Clarification requests: send message with list of available connections and inline selection buttons
- First-response-wins: when resolved via messenger, immediately updates `AgentConnectorApproval` record; dashboard reflects resolution

### 8.3 Remaining CLI Commands
Create in `app/Console/Commands/Connectors/`:
- `ConnectorPruneTelemetryCommand` (`connector:prune-telemetry`) — deletes invocation and webhook event records older than team's `telemetry_retention_days` setting. Iterates all teams with connector settings.
- `ConnectorRotateKeysCommand` (`connector:rotate-keys {team}`) — generates new `connector_vault_key`, re-encrypts all credentials for the team, increments `encryption_key_id`, logs `rotated` credential events. Requires confirmation.

### 8.4 Messenger Tests
- `ConnectorMessengerCommandTest` — connect, list, test, disconnect command parsing and execution
- `ConnectorApprovalMessengerTest` — approval notification delivery and inline button resolution

---

## Phase 9: Telemetry Integration & Reliability Scoring

Depends on: Phase 2 complete

### 9.1 WeightedReliability Integration
Extend `app/Services/Reliability/WeightedReliabilityScorer.php`:
- Add connector failure rate as an input signal to existing reliability scoring
- Connector-attributed failures reduce workflow reliability score proportionally
- New method or parameter: `includeConnectorMetrics(string $workflowKey): array` — pulls connector error rates from `agent_connector_invocations` for the workflow

### 9.2 Telemetry Event Ledger Integration
Extend existing `IngestionService` to accept connector telemetry events:
- Map `connector.invoked`, `connector.completed`, `connector.failed`, `connector.webhook_received` to ledger event format
- Events include: connector_id, connection_id, action, duration_ms, http_status, retry_count, token_usage, outcome, workflow_key, run_attempt_id, delegatee_id

### 9.3 Budget Enforcement Integration
Extend `WorkflowBudgetEnforcer`:
- Add connector API costs as additive to existing workflow budget calculations
- Connector costs sourced from `agent_connector_invocations` grouped by `workflow_key`

### 9.4 Telemetry Integration Tests
- `ConnectorReliabilityIntegrationTest` — connector failures impact workflow reliability score
- `ConnectorCostAttributionTest` — connector costs reflected in workflow budget
- `ConnectorTelemetryLedgerTest` — connector events appear in telemetry ledger

---

## Phase 10: Org Layer Integration & Hardening

Depends on: Phase 8 complete, Phase 9 complete

### 10.1 Org Layer Connector Assignment
- Extend `OrgAgentProfile` to support connector access profiles: which connectors/connection aliases a named AI employee can use
- Add `connector_access` JSONB column to `org_agent_profiles` via migration (or use existing `config` JSONB column)
- `ConnectorResolver` checks org agent profile's connector access list when resolving connectors for org-layer delegatees

### 10.2 MCP Tool Surface Registration
- Register connector actions as MCP tools in the existing MCP server infrastructure
- Tool names follow `{mcp_tool_prefix}.{action_name}` pattern (e.g., `xero.list-invoices`)
- Tools respect existing scope dimensions: tenant (team_id), environment, role
- Stability tiers from connector manifest applied to MCP tool registration
- Auto-discovery: when `connectors.auto_resolve` is enabled, agents can list available connector tools

### 10.3 Load Testing
- Concurrent connector invocations: verify rate limiter and circuit breaker under load
- Credential refresh under contention: multiple simultaneous refresh attempts for same credential
- Webhook ingestion throughput: burst of webhook events
- Approval timeout under load: many concurrent approval requests

### 10.4 Security Audit Verification
- Credential isolation: verify cross-team credential access is impossible (per-team encryption keys)
- OAuth flow: verify PKCE, state parameter validation, no token leakage in logs
- Webhook signature verification: verify rejection of invalid signatures
- PII redaction: verify configured fields are stripped before memory formation
- Raw tokens: verify no raw tokens appear in logs, telemetry, or error messages
- SQL injection: verify parameterized queries in all connector-related database operations

### 10.5 End-to-End Integration Tests
- Full workflow: agent discovers connector → invokes read action → invokes write action (with approval) → telemetry recorded → health score updated → reliability score reflects result
- OAuth reconnection: credential expires → auto-refresh succeeds → next invocation works transparently
- Multi-connection: team connects two Xero orgs → agent invokes without alias → clarification flow → user selects → action executes
- Webhook flow: external service sends event → signature verified → event routed to workflow → workflow executes
- Partnership-pending: connector visible in library → connect attempt blocked with correct error

## Sections

- Phase 1: Foundation — Configuration, Data Model & Credential Vault
- Phase 2: Connector Runtime — Execution Pipeline & Resilience
- Phase 3: Tier 1 Connectors (13 Connectors)
- Phase 4: Authorization & REST API Surface
- Phase 5: UI & Operator Dashboard Integration
- Phase 6: Webhook Ingestion & Tier 2 Connectors
- Phase 7: Tier 3 Vertical Connectors
- Phase 8: Messenger Integration & CLI Completion
- Phase 9: Telemetry Integration & Reliability Scoring
- Phase 10: Org Layer Integration & Hardening


## Risks

- External API instability — third-party APIs change without notice, breaking connector action schemas. Mitigated by version-pinned action schemas in manifests, health monitoring with automatic degradation alerts, and per-connector error taxonomy. Each connector's manifest is independently versioned.
- Credential vault security — breach of per-team encryption keys exposes all team integrations. Mitigated by per-team key derivation (hash of team.connector_vault_key + app.key), AES-256-CBC encryption, no raw token logging, key rotation support, and encrypted_data stored as TEXT not accessible via reporting queries.
- Rate limit exhaustion blocking business workflows — aggressive agent invocations can burn through daily API quotas for external services. Mitigated by token bucket rate limiter with per-team multiplier, proactive alerts at 80% quota, automatic backoff at limit, and per-workflow budget caps.
- OAuth flow complexity across providers — different providers implement OAuth differently (PKCE support, token expiry semantics, refresh token rotation). Mitigated by per-connector auth_config in manifest, PKCE by default for authorization code flows, automated credential refresh job with degradation alerting.
- Webhook reliability in local-first deployment — webhooks require publicly routable endpoints which are unavailable in bare local-first mode. Mitigated by built-in tunnel support (Cloudflare/ngrok), clear blocking of webhook subscriptions when no tunnel is configured, and descriptive error messages.
- Partnership-gated API access — Tier 3 vertical connectors (Acturis, Alto, Person Centred Software, CareLineLive) may require partnership agreements that delay or prevent API access. Mitigated by marking as partnership_pending, deferring to Phase 2, and documenting access requirements in manifest partnership_notes.
- PII leakage through connector data — external systems contain sensitive personal data that could flow into agent memory. Mitigated by per-action pii_fields declarations, configurable redaction modes (memory_only default), automatic redaction in PiiRedactor pipeline stage, and exclusion from memory formation.
- Circuit breaker cascade — if a popular external service experiences sustained outage, many workflows may simultaneously fail. Mitigated by per-connector circuit breaker (5 failures in 60s opens for 120s), health score degradation to unhealthy status, operator alerts, and automatic pause of auto-invocations for unhealthy connectors.
- Multi-connection alias confusion — teams with multiple connections to the same connector may experience unexpected routing if agents do not specify aliases. Mitigated by clarification flow via approval channel with inline selection, configurable per-workflow connector pinning, and descriptive error messages.
- Horizon supervisor resource contention — 3 new supervisors add to existing 12 supervisors competing for Redis and process resources. Mitigated by conservative default maxProcesses (2, 4, 1), environment-variable overrides, and auto-balancing strategy.


## Assumptions

- The existing Jetstream Team model and team-scoped authorization patterns (as seen in AgentJobPolicy) are the canonical multi-tenancy boundary — all connector tables use team_id FK to teams(id).
- The existing FeatureFlagManager backed by agent_feature_settings table is the sole feature flag mechanism — Laravel Pennant is not used for connector flags.
- The existing TrustScoreCalculator in app/Support/Delegation/ provides trust scores that can be consumed by PolicyGate for write action gating without modification to its public API.
- The existing WeightedReliabilityScorer, WorkflowBudgetEnforcer, and IngestionService can be extended to accept connector telemetry signals without architectural changes.
- The existing tunnel infrastructure (config/tunnel.php, TunnelSetting model, CloudflaredService) provides the public URL resolution needed for webhook endpoints.
- The existing SystemNotificationDispatcher and messenger button infrastructure (PendingConfirmation pattern) support dual-channel delivery of approval/clarification requests without new messenger transport work.
- The existing CommandRouter/SlashCommandRegistrar pattern supports registering new connector-related chat commands alongside existing messenger commands.
- Laravel's built-in Illuminate\Encryption\Encrypter with AES-256-CBC is sufficient for per-team credential encryption — no external secrets manager (Vault, AWS KMS) is required.
- All 45 connector target APIs are accessible via REST (or REST-wrapped GraphQL for Monday.com and Shopify) — no SOAP or legacy protocol support is needed.
- The ConnectorAccount model for messaging providers (Slack, Telegram, Discord, WhatsApp) remains permanently separate with no shared base model or migration path to the new connector system.
- The existing routes/api.php structure under agent/api/v1 with auth:sanctum + license middleware is the correct location for connector API routes.
- Mock HTTP responses via Http::fake() with JSON fixtures are sufficient for CI testing — no live API sandbox accounts are needed in the test suite.
- The existing Horizon supervisor pattern (defaults + production/local environment overrides) accommodates 3 additional supervisors without configuration architecture changes.

