# Credentials Manager Integration Brief

## Metadata
- Status: Draft
- Author: Codex
- Date: 2026-03-03

## Executive Summary
Agent needs a centralized, extensible credentials manager that securely stores and manages all integration secrets and tokens across the platform.

This module will act as the single source of truth for API keys, OAuth credentials, access tokens, refresh tokens, webhook secrets, and provider-specific metadata. It must support encryption at rest, safe decryption at runtime, strict access controls, audit trails, and provider-specific OAuth lifecycle management.

## Objectives
- Provide one secure credentials store for the full application.
- Ensure all sensitive values are encrypted at rest and never exposed in logs.
- Support OAuth connect, refresh, revoke, and expiry handling.
- Make provider integrations pluggable so new providers are added without core rewrites.
- Preserve operational safety through policy checks, key rotation, and audit visibility.

## Scope
### In Scope
- Central credentials domain and storage model.
- Encryption/decryption services with key version tracking.
- OAuth token lifecycle orchestration and refresh jobs.
- Provider driver contract and registry for easy extension.
- Audit logging, redaction, and authorization policy integration.

### Out of Scope (v1)
- Secrets manager externalization (Vault/AWS KMS/HSM) as mandatory runtime dependency.
- Multi-region active-active key replication.
- Provider-specific UI workflows beyond a baseline connect/manage experience.

## Problem Statement
Current and future integrations require a secure, standardized way to store and use credentials. Scattered secret handling introduces risk:
- inconsistent encryption usage,
- difficult rotation workflows,
- unclear ownership and auditability,
- fragile OAuth token refresh handling.

A dedicated credentials manager resolves this by standardizing all credential operations behind one interface and one policy boundary.

## Functional Requirements
1. Store encrypted credentials by scope and provider.
2. Retrieve and decrypt credentials only via authorized service boundaries.
3. Update/rotate credentials with versioned key metadata.
4. Delete/revoke credentials safely and idempotently.
5. Support credential expiry tracking and proactive refresh triggers.
6. Record immutable audit events for create/read/rotate/revoke/delete operations.
7. Provide provider-aware OAuth token handling with refresh/retry rules.
8. Support provider metadata and custom fields without schema churn.

## Non-Functional Requirements
- Security-first defaults with redaction and least privilege.
- Deterministic behavior for retries and idempotency.
- Backward-compatible extension path for additional providers.
- Strong automated test coverage for crypto, policy, and OAuth flows.

## Proposed Architecture
### Core Components
- `CredentialStore` (application contract)
  - `put(scope, provider, key, value, metadata)`
  - `get(scope, provider, key)`
  - `delete(scope, provider, key)`
  - `rotate(scope, provider, key, newValue, rotationContext)`
  - `list(scope, provider)`
- `EncryptedCredentialRepository`
  - Persists encrypted payloads and metadata.
- `CryptoService`
  - Encrypts/decrypts payloads and tracks encryption key version.
- `OAuthTokenService`
  - Handles token state, refresh scheduling, and revoke flows.
- `IntegrationDriver` (provider contract)
  - Provider-specific OAuth/token behavior.
- `IntegrationRegistry`
  - Resolves driver implementation by provider key.

### Integration Pattern
- Controllers and jobs never access raw secret fields directly.
- Callers resolve credentials through `CredentialStore`.
- Provider-specific logic lives in drivers; shared logic stays in core services.

## Data Model (Proposed)
### `integration_credentials`
- `id` (uuid)
- `scope_type` (string, e.g. `user|team|system`)
- `scope_id` (string)
- `provider` (string)
- `credential_key` (string)
- `ciphertext` (text)
- `key_version` (string)
- `algorithm` (string)
- `metadata_json` (jsonb)
- `expires_at` (timestamp nullable)
- `rotated_at` (timestamp nullable)
- `created_by` (nullable fk)
- `updated_by` (nullable fk)
- timestamps

Recommended unique index:
- `(scope_type, scope_id, provider, credential_key)`

### `integration_credential_audit_logs`
- `id` (uuid)
- `credential_id` (uuid fk)
- `event` (`created|read|updated|rotated|revoked|deleted|refresh_succeeded|refresh_failed`)
- `actor_type` (string)
- `actor_id` (string nullable)
- `request_id` (string nullable)
- `context_json` (jsonb)
- `created_at`

## Encryption and Key Management
### Encryption Strategy
- Use envelope encryption semantics:
  - app-level master key from secure env/config,
  - per-record encrypted payload with associated key version.
- Store only ciphertext + cryptographic metadata in DB.
- Support decrypting older records during key rotation via key version lookup.

### Key Rotation
- Rotation command migrates credentials from old key version to latest key version.
- Rotation operation is resumable and idempotent.
- Audit every re-encryption event with actor/context.

### Sensitive Data Handling Rules
- Never log plaintext credentials.
- Never expose credential values in API responses.
- Provide standardized redaction helper for UI and debug output.

## OAuth Lifecycle Management
### Stored OAuth Fields
- Access token
- Refresh token
- Token type
- Granted scopes
- Expires at
- Provider account metadata (tenant/account IDs)

### Lifecycle Flows
1. Connect: store token bundle + metadata.
2. Pre-expiry refresh: background job refreshes before expiration threshold.
3. Runtime fallback refresh: on auth failure, one safe refresh attempt before hard failure.
4. Revoke/disconnect: revoke upstream where supported, then tombstone/delete local tokens.
5. Failure handling: classify `temporary` vs `terminal` refresh failures and emit alerts/events.

### OAuth Driver Contract (per provider)
- `exchangeAuthorizationCode(...)`
- `refreshToken(...)`
- `revokeToken(...)`
- `normalizeTokenPayload(...)`

## Authorization and Access Policy
- Access must be protected with explicit policy checks (owner/admin/system actor).
- Write operations require elevated permission.
- Read/decrypt operations are restricted to services that need runtime secret access.
- Audit log captures actor, reason/context, and request correlation id.

## Extensibility Model
To add a new provider:
1. Create a new driver implementing `IntegrationDriver`.
2. Register driver in `IntegrationRegistry`.
3. Add provider validation rules and metadata schema.
4. Add provider-specific tests.

No core schema change should be required for typical provider additions.

## API and Service Boundaries (Proposed)
- Internal service first (preferred): callers use domain services instead of direct HTTP exposure.
- Optional admin API surface (later):
  - `POST /agent/api/v1/integrations/{provider}/credentials`
  - `PATCH /agent/api/v1/integrations/{provider}/credentials/{key}`
  - `DELETE /agent/api/v1/integrations/{provider}/credentials/{key}`
  - `POST /agent/api/v1/integrations/{provider}/oauth/refresh`

All endpoints must return redacted payloads only.

## Observability and Operations
- Metrics:
  - credential read/write/rotate counts,
  - oauth refresh success/failure rates,
  - upcoming expirations by provider/scope.
- Alerts:
  - repeated refresh failures,
  - decryption failures,
  - rotation failures.
- Operational commands:
  - `credentials:rotate --from=... --to=...`
  - `credentials:audit:report --provider=... --since=...`

## Testing Strategy
### Unit
- Crypto roundtrip and key-version compatibility.
- Credential repository invariants.
- Redaction behavior.
- OAuth driver normalization/parsing.

### Feature/Integration
- Policy authorization for read/write/delete/rotate paths.
- OAuth refresh and revoke flows (success and failure branches).
- Audit log emission on sensitive operations.
- Expiry-triggered refresh job behavior.

### Regression/Security
- Ensure no plaintext values appear in logs or serialized responses.
- Ensure rotated credentials remain decryptable and usable.

## Phased Delivery Plan
1. **Phase 1: Core Storage + Crypto**
   - Migrations, repository, `CredentialStore`, `CryptoService`, policy checks.
2. **Phase 2: Audit + Redaction + Rotation**
   - Audit events, redacted DTOs, rotation command/service.
3. **Phase 3: OAuth Lifecycle Core**
   - `OAuthTokenService`, expiry monitoring, refresh jobs.
4. **Phase 4: Provider Drivers**
   - Implement initial providers (for example OpenAI, Anthropic, GitHub/custom OAuth).
5. **Phase 5: API/UI Integration**
   - Settings and management surfaces built on top of the service boundary.

## Risks and Mitigations
- Risk: inconsistent credential usage bypassing manager.
  - Mitigation: enforce service usage via code review rule and static grep checks.
- Risk: rotation causes downtime.
  - Mitigation: staged rotation with dual-version decrypt support.
- Risk: OAuth refresh loops under provider outages.
  - Mitigation: capped retries + backoff + terminal state marking.
- Risk: privilege creep around decryption calls.
  - Mitigation: policy-gated service methods and audit assertions in tests.

## Acceptance Criteria
- All integration secrets are created and retrieved only through `CredentialStore`.
- Credentials are encrypted at rest with key version metadata.
- OAuth refresh lifecycle is automated and test-covered.
- Audit logs exist for all sensitive operations.
- Redaction is enforced across logs and API responses.
- Adding a new provider requires only driver + registration + tests.

## Definition of Done
- Schema, services, policies, and jobs are implemented.
- Existing integration paths are migrated to the new credentials manager.
- Test suite covering crypto, policy, OAuth lifecycle, and audit behavior passes.
- Operations docs include rotation, revocation, and incident response guidance.
