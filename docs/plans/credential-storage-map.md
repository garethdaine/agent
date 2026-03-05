# Credential Storage Map

## Overview

Agent Ops stores credentials (API keys, tokens) in an encrypted credential vault backed by a database table (`credential_vault`). This document maps every credential surface, where secrets are stored, and how they are protected.

## Storage Locations

### 1. Credential Vault (Primary)

| Provider | Keys | Purpose | Encrypted |
|----------|------|---------|-----------|
| `openai` | `api_key` | OpenAI API calls for LLM, embeddings | Yes (AES-256-CBC via Laravel encryption) |
| `anthropic` | `api_key` | Anthropic Claude API calls | Yes |
| `github` | `token` | GitHub API for repo analysis, webhooks | Yes |

- **Table:** `credential_vault`
- **Encryption:** Laravel's `encrypt()` / `decrypt()` using `APP_KEY`
- **Access:** `CredentialsManager` service, scoped per-user
- **API:** `GET/POST/DELETE /agent/api/v1/credentials`

### 2. Environment Variables (.env)

| Variable | Purpose | Sensitivity |
|----------|---------|-------------|
| `APP_KEY` | Master encryption key for all vault secrets | Critical |
| `DB_PASSWORD` | Database authentication | Critical |
| `REDIS_PASSWORD` | Redis authentication | High |
| `DISCORD_TOKEN` | Discord bot token | High |
| `SLACK_BOT_TOKEN` | Slack bot token | High |
| `SLACK_SIGNING_SECRET` | Slack webhook verification | High |
| `TELEGRAM_BOT_TOKEN` | Telegram bot token | High |
| `WHATSAPP_API_TOKEN` | WhatsApp API token | High |
| `REVERB_APP_SECRET` | WebSocket broadcast authentication | Medium |

### 3. Connector Account Configs

Messenger connector accounts store connection-specific tokens in the `config` JSON column on `connector_accounts`. These are encrypted at rest via the database column's `encrypted:array` cast.

### 4. API Tokens (Jetstream/Sanctum)

Personal API tokens for gateway access are stored in the `personal_access_tokens` table with SHA-256 hashed token values. Raw tokens are shown once at creation and never stored.

## Security Controls

| Control | Implementation |
|---------|----------------|
| At-rest encryption | Laravel Crypt (AES-256-CBC) for vault; DB column casts for configs |
| Key rotation | Change `APP_KEY` + re-encrypt vault entries |
| Access control | Per-user scoping on all credential operations |
| Audit trail | All credential mutations logged to `agent_audit_logs` |
| Rate limiting | Store/delete endpoints throttled via `throttle:agent-mutations` |
| Redaction | `LOG_REDACT_SENSITIVE=true` strips secrets from log output |
| Env protection | `EnvPolicy` blocks overriding `PATH`, `HOME`, and common secret patterns |

## UI Surface

- **Settings > Secrets**: View stored provider credentials (names only, no values). Store new credentials, delete existing ones.
- **Settings > API Tokens**: Jetstream-managed token create/revoke with scoped permissions.

## Adding a New Provider

1. Add the provider entry to `config/credentials.php` under `providers`
2. Specify the `keys` array (e.g., `['api_key', 'api_secret']`) and a `label`
3. The vault, API, and UI automatically pick it up -- no code changes needed
