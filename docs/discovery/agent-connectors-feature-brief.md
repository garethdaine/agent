# Requirements Discovery Brief — Agent Connectors

## 1. Overview

**Feature Name:** Agent Connectors (External Service Integration Layer)

**Purpose:** Add a first-class connector system to the Agent platform so that agents (Jobs, Messenger, and Delegation/Org Layer) can read from, write to, and react to events from external SaaS platforms, APIs, and data sources used by target customers. Connectors bridge the gap between Agent's orchestration runtime and the tools customers already use — accounting packages, CRMs, HR systems, industry-specific platforms — enabling agents to operate on real business data rather than isolated prompts.

This brief consolidates:

1. Service adoption research across 890 target companies in 13 UK mid-market verticals.
2. Competitive integration analysis from 8 agent orchestration platforms.
3. Existing MCP transport and scope contracts from `agent-mcp-v5.md`.
4. Connector security, credential, and compliance requirements.

The Connectors system is a **composition layer** that extends the existing MCP tool contract — each connector surfaces as one or more MCP tools with standard scope, authentication, and telemetry semantics.

---

## 2. Competitive Landscape & Gap Analysis

### 2.1 Integration Landscape

Integration breadth is a primary differentiator for agent platforms targeting business users:

| Platform | Native Integrations | Approach | Marketplace |
|----------|-------------------|----------|-------------|
| **Lindy.ai** | 3,000+ | Pre-built templates | Extensive, template-driven |
| **Make.com** | 1,800+ | Visual workflow builder | Community + official |
| **n8n** | 1,000+ | Community nodes + self-hosted | Open-source community |
| **Zapier** (reference) | 7,000+ | Trigger/action pairs | Largest marketplace |
| **CrewAI** | 30+ tools, MCP support | Python decorators | CrewAI Enterprise Hub |
| **LangChain** | 100+ tool integrations | Python/TS packages | LangChain Hub |
| **Dust.tt** | 20+ | Custom TypeScript actions | Internal only |
| **Relevance AI** | 50+ | Visual tool builder | Template library |

### 2.2 Key Market Patterns

- **MCP is the convergence standard.** 8.4M+ downloads, adopted by OpenAI, Google DeepMind, Anthropic. Building connectors as MCP servers means they work with any MCP-compatible client — not just Agent.
- **Breadth wins distribution, depth wins retention.** Platforms with 1,000+ integrations acquire faster, but platforms with deep vertical integrations (e.g., Acturis for insurance, COINS for construction) retain better.
- **Credential management is the hidden moat.** Most agent platforms struggle with OAuth flow management, token refresh, and multi-tenant credential isolation. Platforms that solve this cleanly build trust.
- **Webhook-driven event connectors are underserved.** Most platforms treat integrations as pull-only (read data). Push-based connectors that react to external events (new invoice in Xero, new ticket in Zendesk) are rare but high-value.

### 2.3 Highest-Value Gaps for Agent

1. **No agent platform combines connector telemetry with production reliability scoring.** Agent can track per-connector error rates, latency, and token cost, feeding into existing `WeightedReliability` calculations.
2. **No platform offers connector-level policy gates.** Agent can enforce which connectors a given delegatee or org-layer employee can access, with trust-score-gated escalation.
3. **No platform provides connector health as an operator surface.** Agent can surface connector status, credential expiry alerts, and rate limit utilization alongside workflow health.
4. **UK mid-market vertical connectors are underserved.** No competing platform offers native integration with Acturis, COINS, Alto, CareLineLive, or Person Centred Software.

---

## 3. Goals

1. Deliver a connector architecture built on MCP tool contracts, so connectors surface as standard MCP tools within the existing scope and transport model.
2. Provide a secure credential vault with OAuth 2.0 flow management, token refresh, and per-tenant isolation.
3. Ship an initial set of ~45 connectors across 3 tiers: cross-industry essentials, high-priority multi-vertical, and industry-specific vertical connectors.
4. Build a connector registry with install, configure, health monitoring, and removal lifecycle management.
5. Support both pull-based (agent queries external service) and push-based (external service notifies agent via webhook) connector patterns.
6. Integrate connector telemetry into existing reliability scoring, cost governance, and operator surfaces.

---

## 4. Non-Goals (Phase 1)

1. Building a public connector marketplace with third-party submissions.
2. Visual workflow builder for connector composition (connectors are invoked by agents via skill/delegation context).
3. Bi-directional real-time sync (e.g., two-way Salesforce sync) — Phase 1 supports read + write + event, not continuous sync.
4. Supporting SOAP/legacy protocol connectors beyond thin REST wrappers.
5. Multi-tenant SaaS-hosted connector proxy — connectors run local-first within the Agent runtime.
6. Custom connector authoring SDK (Phase 1 ships pre-built connectors only; SDK is a Phase 2 concern).

---

## 5. Canonical Reuse Contracts

### 5.1 MCP Contract

Connectors must obey `agent-mcp-v5` lock-ins:

1. Each connector surfaces as one or more MCP tools with versioned request/response schemas.
2. Scope dimensions remain exactly `tenant`, `environment`, `role`.
3. Evaluation order remains scope check then permission claim check.
4. Connector tools follow the same stability tiers (`stable`, `beta`, `experimental`) and CI matrix gates.
5. Transport semantics remain poll plus WebSocket with stable app error codes.
6. Connector-specific errors map to existing error taxonomy (`AUTH_UNAUTHENTICATED`, `VALIDATION_FAILED`, plus new connector-specific codes).

### 5.2 Delegation Runtime Contract

Connector access must flow through existing delegation components:

1. Connector invocations are policy-gated: `CommandPolicy`, `PathPolicy`, and `EnvPolicy` govern which connectors an agent can call.
2. Connector outputs pass through the verification pipeline when configured.
3. Trust scoring determines connector access: delegatees below configurable thresholds cannot invoke write-enabled or sensitive connectors.
4. Connector rate limits compose with existing delegation concurrency limits.

### 5.3 Memory Contract

Connector data must obey the four-layer memory architecture:

1. Connector responses are eligible for working memory capture and long-term formation.
2. Connectors can declare memory context requirements (e.g., "needs client context for Xero tenant selection").
3. Memory failures never block connector execution.
4. Sensitive connector data (credentials, PII from external systems) is excluded from memory formation via configurable redaction rules.

### 5.4 Telemetry Contract

Connector invocations emit telemetry events into the existing append-only ledger:

1. Event types: `connector.invoked`, `connector.completed`, `connector.failed`, `connector.webhook_received`.
2. Events include `connector_id`, `action`, `duration_ms`, `http_status`, `retry_count`, `token_usage`, `outcome`.
3. Connector events feed into weighted reliability calculations for the parent workflow.
4. Connector cost (API call costs, token costs) is attributed to the invoking workflow's budget.

### 5.5 Cost Governance Contract

Connector API calls may incur external costs (e.g., Xero API metered calls, Salesforce API quota):

1. Connectors declare their cost model: `free`, `metered`, `quota-limited`.
2. Quota-limited connectors expose remaining quota via health checks.
3. Budget enforcement uses existing monthly workflow budget — connector costs are additive.
4. Rate limit exhaustion triggers automatic backoff, not hard failure.

---

## 6. Connector Architecture

### 6.1 Connector Structure

Each connector is an MCP tool bundle:

```
connectors/
├── xero/
│   ├── connector.json          # Manifest: metadata, auth config, actions
│   ├── actions/
│   │   ├── list-invoices.json  # Action schema (request/response)
│   │   ├── create-invoice.json
│   │   ├── get-contact.json
│   │   └── ...
│   ├── webhooks/               # Optional: push-based event handlers
│   │   ├── invoice-created.json
│   │   └── payment-received.json
│   └── README.md               # Human-readable docs
```

### 6.2 Connector Manifest Schema (`connector.json`)

```json
{
  "name": "xero",
  "display_name": "Xero Accounting",
  "description": "Read and write accounting data in Xero: invoices, contacts, bank transactions, reports.",
  "version": "1.0.0",
  "author": "agentops",
  "category": "accounting",
  "industries": ["accounting-advisory", "all"],
  "icon": "xero.svg",
  "auth": {
    "type": "oauth2",
    "authorization_url": "https://login.xero.com/identity/connect/authorize",
    "token_url": "https://identity.xero.com/connect/token",
    "scopes": ["openid", "profile", "accounting.transactions", "accounting.contacts"],
    "refresh_strategy": "auto",
    "token_expiry_seconds": 1800
  },
  "base_url": "https://api.xero.com/api.xro/2.0",
  "rate_limits": {
    "requests_per_minute": 60,
    "daily_limit": 5000,
    "concurrent_limit": 5
  },
  "cost_model": "quota-limited",
  "risk_level": "standard",
  "actions": [
    { "name": "list-invoices", "method": "GET", "path": "/Invoices", "stability": "stable" },
    { "name": "create-invoice", "method": "POST", "path": "/Invoices", "stability": "stable" },
    { "name": "get-contact", "method": "GET", "path": "/Contacts/{ContactID}", "stability": "stable" }
  ],
  "webhooks": [
    { "event": "invoice.created", "stability": "beta" },
    { "event": "payment.received", "stability": "beta" }
  ],
  "mcp_tool_prefix": "xero",              // MCP tool names are "{mcp_tool_prefix}.{action_name}" e.g. "xero.list-invoices"
  "requires_approval_for": ["create-invoice", "update-contact", "void-invoice"]
}
```

### 6.3 Action Schema

Each action maps to an MCP tool. Example: `xero.list-invoices`

```json
{
  "name": "list-invoices",
  "display_name": "List Invoices",
  "description": "Retrieve invoices from Xero with optional filtering by status, date range, and contact.",
  "method": "GET",
  "path": "/Invoices",
  "parameters": {
    "type": "object",
    "properties": {
      "Status": { "type": "string", "enum": ["DRAFT", "SUBMITTED", "AUTHORISED", "PAID", "VOIDED"], "description": "Filter by invoice status" },
      "DateFrom": { "type": "string", "format": "date", "description": "Start date (YYYY-MM-DD)" },
      "DateTo": { "type": "string", "format": "date", "description": "End date (YYYY-MM-DD)" },
      "ContactID": { "type": "string", "format": "uuid", "description": "Filter by contact ID" },
      "page": { "type": "integer", "default": 1 }
    }
  },
  "response_schema": {
    "type": "object",
    "properties": {
      "Invoices": { "type": "array", "items": { "$ref": "#/definitions/Invoice" } },
      "pagination": { "$ref": "#/definitions/Pagination" }
    }
  },
  "requires_approval": false,
  "read_only": true,
  "idempotent": true,
  "pii_fields": ["Contact.EmailAddress", "Contact.FirstName", "Contact.LastName"]
}
```

### 6.4 Push-Based Webhooks

Connectors can register webhook endpoints for event-driven automation:

```
External Service → POST /agent/api/v1/connectors/{connector}/webhooks/{event}
    → Signature verification (HMAC or provider-specific)
    → Event normalization to internal schema
    → Emit connector.webhook_received telemetry event
    → Route to matching workflow/job trigger
    → Dead-letter queue for unmatched or failed events
```

Webhook events follow the same deduplication semantics as MCP events: at-least-once delivery, idempotent dedupe by `(event_id, connector_id)`.

---

## 7. Credential Vault

### 7.1 Architecture

The credential vault provides secure, per-tenant credential storage with automatic lifecycle management.

```
Credential Creation:
    User initiates OAuth flow → Agent redirects to provider
    → Provider authenticates user → Callback with auth code
    → Agent exchanges code for tokens → Encrypt and store
    → Connector status: connected

Credential Refresh:
    Token expiry approaching (configurable buffer, default 5 minutes)
    → Background refresh job attempts token renewal
    → Success: update stored tokens, reset expiry
    → Failure: retry 3x with backoff → mark credential degraded
    → Degraded: connector actions return soft error, alert operator

Credential Revocation:
    User removes connector or revokes via UI
    → Revoke token at provider (if supported)
    → Delete encrypted credentials from vault
    → Connector status: disconnected
```

### 7.2 Security Requirements

| Requirement | Implementation |
|-------------|---------------|
| Encryption at rest | AES-256-GCM, key derived from tenant-specific secret + app key |
| Encryption in transit | TLS 1.3 for all provider API calls |
| Credential isolation | Per-tenant encryption keys; no cross-tenant credential access |
| Token storage | Only encrypted tokens stored; raw tokens never logged or cached |
| Audit trail | All credential lifecycle events (create, refresh, revoke, fail) logged with timestamp and actor |
| Key rotation | Application key rotation re-encrypts all stored credentials |
| Access control | Only connector runtime service can decrypt; no direct DB access to raw tokens |
| Provider token scoping | Request minimum required OAuth scopes per connector |

### 7.3 Supported Authentication Types

| Auth Type | Flow | Token Management |
|-----------|------|-----------------|
| **OAuth 2.0 (Authorization Code)** | Redirect flow with PKCE | Auto-refresh with `refresh_token` |
| **OAuth 2.0 (Client Credentials)** | Server-to-server | Auto-refresh on expiry |
| **API Key** | User provides key via UI | Stored encrypted, no refresh needed |
| **API Key + Secret** | User provides key pair | Stored encrypted, no refresh needed |
| **Basic Auth** | Username + password | Stored encrypted, no refresh needed |
| **Custom Header** | User-defined header/value | Stored encrypted |

### 7.4 Data Model — Credentials

```sql
CREATE TABLE agent_connector_credentials (
    id              UUID PRIMARY KEY,
    tenant_id       UUID NOT NULL REFERENCES tenants(id),
    connector_id    UUID NOT NULL REFERENCES agent_connectors(id),
    auth_type       VARCHAR(30) NOT NULL,
    -- oauth2_authorization_code | oauth2_client_credentials | api_key | api_key_secret | basic | custom_header
    encrypted_data  BYTEA NOT NULL,                    -- AES-256-GCM encrypted token/key blob
    encryption_key_id VARCHAR(64) NOT NULL,            -- references key version for rotation
    scopes_granted  JSONB DEFAULT '[]',
    token_expires_at TIMESTAMP,                        -- NULL for non-expiring auth types
    refresh_token_expires_at TIMESTAMP,                -- NULL if no refresh token
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    -- active | degraded | expired | revoked
    last_refreshed_at TIMESTAMP,
    last_used_at    TIMESTAMP,
    refresh_failure_count INTEGER DEFAULT 0,
    created_by      UUID REFERENCES users(id),
    updated_by      UUID REFERENCES users(id),
    revoked_by      UUID REFERENCES users(id),
    revoked_at      TIMESTAMP,
    rotation_count  INTEGER DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, connector_id)
);
```

---

## 8. Feature Components

### 8.1 Connector Registry & Management

#### 8.1.1 UI Surface

Location: `/tools/connectors` (new page in Tools settings section)

**Views:**

- **Connector Library** — Browse available connectors by category and industry. Shows connection status, description, actions available, and "Connect" button.
- **Connected Services** — Grid/list of active connections with health status, last sync time, credential expiry, request count (24h), error rate, and actions (disconnect, reconfigure, test).
- **Connector Detail** — Full connector metadata, available actions, webhook subscriptions, telemetry history, rate limit utilization, and credential management.

**Actions:**

| Action | Requires Confirmation | Feature Flag |
|--------|----------------------|-------------|
| Connect (initiate OAuth) | No (OAuth flow is inherently interactive) | `connectors.enabled` |
| Disconnect | Yes | `connectors.enabled` |
| Test connection | No | `connectors.enabled` |
| Configure webhooks | Yes (creates external endpoint) | `connectors.webhooks_enabled` |
| Remove connector | Yes | `connectors.enabled` |

#### 8.1.2 API Surface

```
GET    /agent/api/v1/connectors                        # List available connectors
GET    /agent/api/v1/connectors/{id}                   # Connector detail
POST   /agent/api/v1/connectors/{id}/connect           # Initiate connection (returns OAuth URL or accepts API key)
DELETE /agent/api/v1/connectors/{id}/disconnect         # Disconnect and revoke credentials
POST   /agent/api/v1/connectors/{id}/test              # Test connection health
GET    /agent/api/v1/connectors/{id}/actions            # List available actions
POST   /agent/api/v1/connectors/{id}/actions/{action}  # Execute action (proxied through connector runtime)
GET    /agent/api/v1/connectors/{id}/health             # Health check (credential status, rate limits, latency)
GET    /agent/api/v1/connectors/{id}/telemetry          # Invocation history and metrics
POST   /agent/api/v1/connectors/{id}/webhooks           # Register webhook subscription
DELETE /agent/api/v1/connectors/{id}/webhooks/{event}   # Remove webhook subscription
GET    /agent/api/v1/connectors/callback                # OAuth callback handler
```

#### 8.1.3 CLI Surface

```bash
php artisan connector:list                            # List all available connectors
php artisan connector:connect {name} [--api-key=]     # Connect (OAuth opens browser, or pass API key)
php artisan connector:disconnect {name}               # Disconnect and revoke
php artisan connector:test {name}                     # Test connection
php artisan connector:health                          # Health summary for all connected services
php artisan connector:actions {name}                  # List available actions for connector
```

#### 8.1.4 Messenger Surface

Chat commands (via existing messenger control plane):

- `connect {service}` — Initiate connection with instructions (OAuth link or API key prompt)
- `list connections` / `my connections` — Show connected services with status
- `test {service}` — Run connection health check
- `disconnect {service}` — Remove connection with confirmation

### 8.2 Connector Runtime

#### 8.2.1 Execution Pipeline

```
Agent invokes connector action (e.g., xero.list-invoices)
    → ConnectorResolver validates connector is connected + healthy
    → PolicyGate checks delegatee trust score + connector risk level
    → CredentialManager decrypts tokens for this tenant/connector
    → RateLimiter checks per-connector and per-tenant limits
    → HttpClient executes request with auth headers + timeout
    → ResponseNormalizer maps provider response to action schema
    → PiiRedactor strips configured PII fields before memory formation
    → TelemetryEmitter records connector.invoked / connector.completed / connector.failed
    → Result returned to agent context
```

#### 8.2.2 Resilience

| Mechanism | Configuration | Behaviour |
|-----------|--------------|-----------|
| **Retry** | 3 attempts with exponential backoff (1s, 2s, 4s) + jitter | Retries on 429, 500, 502, 503, 504; no retry on 401, 403, 404, 422 |
| **Circuit breaker** | 5 failures in 60s → open circuit for 120s | During open circuit, actions return cached response (if available) or soft error |
| **Rate limiter** | Per-connector limits from manifest + per-tenant multiplier | Token bucket algorithm; queues requests during limit approach; rejects with backoff hint at limit |
| **Timeout** | Default 30s per action; configurable per action | Hard timeout triggers failure event, no retry |
| **Credential refresh** | 5-minute buffer before expiry; 3 retry attempts | Background refresh; degraded status on failure |
| **Dead-letter queue** | Failed webhook events after 3 delivery attempts | Events stored for manual replay; 30-day retention |

#### 8.2.3 Connector Health Model

Each connected service exposes a health status:

```
Health Score = weighted average of:
    credential_health  (40%) — active/degraded/expired/revoked
    error_rate         (30%) — 5xx and timeout rate over rolling 1-hour window
    latency            (15%) — p95 latency vs connector's declared SLA
    rate_limit_headroom (15%) — remaining quota vs daily limit

Status mapping:
    score >= 0.8  → healthy (green)
    score >= 0.5  → degraded (yellow) → alert operator
    score <  0.5  → unhealthy (red) → pause auto-invocations, require manual intervention
```

---

## 9. Initial Connector Set

Based on outreach data covering 890 target companies across 13 UK mid-market verticals, connectors are organized into three tiers by integration priority.

### 9.1 Tier 1 — Cross-Industry Essentials (13 connectors)

These services are used across all or most target verticals and should ship first.

| Connector | Category | Auth Type | Actions (Phase 1) | Industries |
|-----------|----------|-----------|-------------------|------------|
| **Xero** | Accounting | OAuth 2.0 | List/create/update invoices, contacts, bank transactions, reports, payments | All (primary UK cloud accounting; 11.4% market share among bookkeepers) |
| **Sage 50/200** | Accounting/ERP | API Key | List/create invoices, customers, products, stock, purchase orders | All (10.3% UK market share; 100,000+ users) |
| **Salesforce** | CRM | OAuth 2.0 | Query/create/update leads, contacts, opportunities, accounts, tasks | All (enterprise CRM leader) |
| **HubSpot** | CRM | OAuth 2.0 | Contacts, companies, deals, tickets, email events | All (mid-market CRM leader; #2 G2 sales product 2025) |
| **Microsoft Teams** | Communication | OAuth 2.0 | Send messages, list channels, post adaptive cards, read channel messages | All (37% UK market share) |
| **Slack** | Communication | OAuth 2.0 | Send messages, list channels, upload files, react, thread replies | All (18.6% UK market share) |
| **Google Workspace** | Productivity | OAuth 2.0 | Gmail (read/send), Drive (list/read/upload), Calendar (list/create events), Sheets (read/write) | All |
| **Microsoft 365** | Productivity | OAuth 2.0 | Outlook (read/send), OneDrive (list/read/upload), Calendar, SharePoint (list/read) | All |
| **Stripe** | Payments | API Key | List/create charges, customers, invoices, payment intents, subscriptions | All (50,000+ UK businesses) |
| **GoCardless** | Payments | OAuth 2.0 | Create/list mandates, payments, subscriptions, payouts | All (UK direct debit leader) |
| **DocuSign** | Document Signing | OAuth 2.0 | Create/send envelopes, check status, download signed documents | All |
| **Monday.com** | Project Management | API Key | List/create/update boards, items, columns, groups | All (GraphQL API) |
| **Notion** | Knowledge Base | OAuth 2.0 | Query databases, create/update pages, search | All |

### 9.2 Tier 2 — High-Priority Multi-Vertical (14 connectors)

Used by 5+ target verticals; ship in second phase.

| Connector | Category | Auth Type | Actions (Phase 1) | Primary Industries |
|-----------|----------|-----------|-------------------|--------------------|
| **BrightHR** | HR/Payroll | API Key | Employee records, absence tracking, rota management, document storage | Hospitality, Retail, Care, Facilities |
| **Breathe HR** | HR | OAuth 2.0 | Employee data, absences, performance, documents | All mid-market (UK HR specialist) |
| **Zendesk** | Customer Support | OAuth 2.0 | List/create/update tickets, users, organizations, satisfaction ratings | IT, Consulting, Hospitality |
| **Freshdesk** | Customer Support | API Key | Tickets, contacts, companies, time entries | IT, Consulting, Manufacturing |
| **Mailchimp** | Email Marketing | OAuth 2.0 | Lists, campaigns, subscribers, analytics, automations | All |
| **ActiveCampaign** | Marketing Automation | API Key | Contacts, deals, automations, campaigns, tags | All |
| **Calendly** | Scheduling | OAuth 2.0 | Events, invitees, availability, webhooks | Consulting, Recruitment, Legal, Accounting |
| **Jira** | Issue Tracking | OAuth 2.0 | Issues, projects, boards, sprints, transitions | IT, Consulting, Manufacturing |
| **Asana** | Project Management | OAuth 2.0 | Tasks, projects, portfolios, goals, team members | Consulting, IT, Manufacturing |
| **QuickBooks Online** | Accounting | OAuth 2.0 | Invoices, customers, expenses, reports, payments | Accounting, Consulting (secondary to Xero/Sage in UK) |
| **FreeAgent** | Accounting | OAuth 2.0 | Invoices, contacts, expenses, timeslips, bank transactions | Accounting (UK-specific; HMRC MTD integrated) |
| **Shopify** | E-commerce | OAuth 2.0 | Orders, products, customers, inventory, fulfilments | Retail, Manufacturing, Food (GraphQL API) |
| **Trello** | Project Management | API Key | Boards, cards, lists, members, actions | All |
| **Dropbox** | File Storage | OAuth 2.0 | List/read/upload files, share links, search | All |

### 9.3 Tier 3 — Industry-Specific Vertical Connectors (18 connectors)

These target specific verticals where deep integration creates defensible value. Each vertical receives 2 connectors — the dominant market-share platform and its strongest competitor — regardless of vertical size. This consistent allocation reflects that vertical connector depth is a moat play: even the smallest verticals (Insurance at 17 companies) yield high per-deal value because these connectors are unavailable on any competing agent platform. Prioritization of which verticals to build first within Phase 6 should follow outreach pipeline conversion rates and active deal interest.

#### IT & Technology / Managed Services (100 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **ConnectWise Manage** | API Key + Secret | Tickets, companies, contacts, time entries, agreements | 18,000+ MSPs globally; PSA market leader |
| **Datto Autotask** | API Key | Tickets, companies, contacts, projects, billing | Major MSP PSA platform |

#### Healthcare & Care (86 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **CareLineLive** | API Key | Carer schedules, client records, visit logs, alerts | 700+ UK home care agencies; Trustpilot leader |
| **Person Centred Software** | API Key | Care plans, activity records, assessments, alerts | 3,500+ UK care homes; 95% rated Good/Outstanding |

#### Facilities & Environmental (67 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **ServiceM8** | OAuth 2.0 | Jobs, clients, quotes, invoices, staff schedules | Popular UK field service platform |
| **Simpro** | API Key | Jobs, quotes, invoices, schedules, stock | Facilities & trades management |

#### Logistics & Freight (53 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **Brightpearl** (Sage) | OAuth 2.0 | Orders, inventory, warehousing, fulfilment, purchasing | Sage-owned; 3PL integrations |
| **ShipStation** | API Key | Orders, shipments, carriers, labels, tracking | Multi-carrier shipping automation |

#### Construction (51 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **Procore** | OAuth 2.0 | Projects, RFIs, submittals, daily logs, financial tools | Leading construction project management |
| **Sage Construction** | API Key | Job costing, subcontractor management, plant, payroll | 100,000+ UK Sage construction users |

#### Property & Estate Agency (36 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **Alto** (Zoopla) | API Key | Properties, contacts, viewings, offers, tenancies | 6,000+ UK agencies; 73% YoY growth |
| **Reapit** | OAuth 2.0 | Properties, applicants, negotiations, lettings | 18,000 global users |

#### Recruitment & Staffing (30 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **Bullhorn** | OAuth 2.0 | Candidates, jobs, placements, submissions, activities | Market leader; 4.4/5 G2 rating |
| **Vincere** | API Key | Candidates, contacts, companies, jobs, placements | Strong UK/ANZ presence |

#### Legal Services (22 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **Clio** | OAuth 2.0 | Matters, contacts, time entries, bills, documents | 93% satisfaction; REST API |
| **LEAP** | API Key | Matters, contacts, time entries, documents, accounting | UK practice management leader |

#### Insurance Broking (17 companies)

| Connector | Auth Type | Actions | Notes |
|-----------|-----------|---------|-------|
| **Acturis** | API Key | Policies, clients, quotes, claims, renewals | 32,000 UK users; 8/10 top UK insurer extranets |
| **Open GI** | API Key | Policies, quotes, claims, client records | Major UK insurance platform |

---

## 10. Data Model

### 10.1 Tables

```sql
-- Connector registry (available connectors)
CREATE TABLE agent_connectors (
    id              UUID PRIMARY KEY,
    name            VARCHAR(64) NOT NULL UNIQUE,       -- e.g., 'xero'
    display_name    VARCHAR(128) NOT NULL,              -- e.g., 'Xero Accounting'
    description     TEXT NOT NULL,
    category        VARCHAR(64) NOT NULL,
    industries      JSONB DEFAULT '[]',
    version         VARCHAR(20) NOT NULL,
    auth_type       VARCHAR(30) NOT NULL,
    auth_config     JSONB NOT NULL,                    -- OAuth URLs, scopes, etc.
    base_url        TEXT NOT NULL,
    rate_limits     JSONB NOT NULL,
    cost_model      VARCHAR(20) NOT NULL DEFAULT 'free',
    risk_level      VARCHAR(20) NOT NULL DEFAULT 'standard',
    actions         JSONB NOT NULL,                    -- array of action definitions
    webhooks        JSONB DEFAULT '[]',
    icon_path       TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'available',
    -- available | deprecated | disabled
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Tenant connector connections (per-tenant installations)
CREATE TABLE agent_connector_connections (
    id              UUID PRIMARY KEY,
    tenant_id       UUID NOT NULL REFERENCES tenants(id),
    connector_id    UUID NOT NULL REFERENCES agent_connectors(id),
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    -- pending | connected | degraded | disconnected | error
    health_score    DECIMAL(3,2) DEFAULT 1.00,
    config          JSONB DEFAULT '{}',                -- tenant-specific config (e.g., Xero org ID)
    webhook_subscriptions JSONB DEFAULT '[]',
    last_health_check_at TIMESTAMP,
    last_action_at  TIMESTAMP,
    action_count_24h INTEGER DEFAULT 0,
    error_count_24h INTEGER DEFAULT 0,
    connected_by    UUID REFERENCES users(id),
    connected_at    TIMESTAMP,
    disconnected_by UUID REFERENCES users(id),
    disconnected_at TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, connector_id)
);

-- Connector invocation telemetry
CREATE TABLE agent_connector_invocations (
    id              UUID PRIMARY KEY,
    connection_id   UUID NOT NULL REFERENCES agent_connector_connections(id),
    connector_id    UUID NOT NULL REFERENCES agent_connectors(id),
    action_name     VARCHAR(128) NOT NULL,
    run_attempt_id  UUID,                              -- links to delegation attempt
    delegatee_id    UUID,
    workflow_key    VARCHAR(255),
    http_method     VARCHAR(10) NOT NULL,
    http_status     INTEGER,
    duration_ms     INTEGER,
    request_size_bytes INTEGER,
    response_size_bytes INTEGER,
    token_usage     INTEGER,
    retry_count     INTEGER DEFAULT 0,
    outcome         VARCHAR(20) NOT NULL,
    -- success | failed | timeout | rate_limited | auth_failed
    error_message   TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Webhook event log
CREATE TABLE agent_connector_webhook_events (
    id              UUID PRIMARY KEY,
    connection_id   UUID NOT NULL REFERENCES agent_connector_connections(id),
    connector_id    UUID NOT NULL REFERENCES agent_connectors(id),
    event_type      VARCHAR(128) NOT NULL,
    external_event_id VARCHAR(255),                    -- provider's event ID for dedupe
    payload_hash    VARCHAR(64) NOT NULL,              -- SHA-256 of raw payload
    processing_status VARCHAR(20) NOT NULL DEFAULT 'received',
    -- received | processing | routed | dead_letter
    routed_to_workflow VARCHAR(255),
    routed_to_job_id UUID,
    processing_duration_ms INTEGER,
    retry_count     INTEGER DEFAULT 0,
    error_message   TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (connection_id, external_event_id)
);

-- Credential refresh audit log
CREATE TABLE agent_connector_credential_events (
    id              UUID PRIMARY KEY,
    credential_id   UUID NOT NULL REFERENCES agent_connector_credentials(id),
    event_type      VARCHAR(30) NOT NULL,
    -- created | refreshed | refresh_failed | expired | revoked | rotated
    actor_id        UUID REFERENCES users(id),         -- NULL for system/automated
    details         JSONB DEFAULT '{}',
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### 10.2 Indexes

```sql
CREATE INDEX idx_connections_tenant_status ON agent_connector_connections (tenant_id, status);
CREATE INDEX idx_connections_connector ON agent_connector_connections (connector_id);
CREATE INDEX idx_connectors_category ON agent_connectors (category);
CREATE INDEX idx_connectors_industry ON agent_connectors USING GIN (industries);
CREATE INDEX idx_invocations_connection ON agent_connector_invocations (connection_id, created_at);
CREATE INDEX idx_invocations_run ON agent_connector_invocations (run_attempt_id);
CREATE INDEX idx_invocations_workflow ON agent_connector_invocations (workflow_key, created_at);
CREATE INDEX idx_invocations_outcome ON agent_connector_invocations (outcome, created_at);
CREATE INDEX idx_webhook_events_connection ON agent_connector_webhook_events (connection_id, created_at);
CREATE INDEX idx_webhook_events_status ON agent_connector_webhook_events (processing_status);
CREATE INDEX idx_credential_events_cred ON agent_connector_credential_events (credential_id, created_at);
```

---

## 11. Authorization Model

| Action | Required Role | Notes |
|--------|--------------|-------|
| Browse connector library | Any authenticated user | Read-only |
| View connected services | Any authenticated user | Read-only |
| View connector telemetry | Tenant admin or operator | Invocation history and metrics |
| Connect service (OAuth/API key) | Tenant admin or operator | Credential storage requires elevated access |
| Disconnect service | Tenant admin | Destructive; revokes credentials |
| Test connection | Tenant admin or operator | Read-only health check |
| Configure webhooks | Tenant admin | Creates external-facing endpoints |
| Rotate credentials | Tenant admin | Re-encrypts and refreshes stored tokens |
| Configure rate limit overrides | Tenant admin | Per-tenant multiplier for connector rate limits |
| Approve write actions | Tenant admin or operator | Required when `requires_approval_for` actions are invoked |
| Execute read-only connector action (via agent) | Governed by delegation trust score (any tier) | Agents invoke connectors; not direct user action |
| Execute write/mutate connector action (via agent) | Governed by delegation trust score (≥ configurable threshold) | Higher trust required for mutation actions |
| Execute connector on sensitive/risk-elevated connector | Governed by delegation trust score (≥ 0.7 default) | Risk-level-gated access for connectors marked `elevated` or `critical` |

**Tenant Boundary Enforcement:** All connector operations are strictly scoped to the authenticated tenant. Cross-tenant credential access is prevented by per-tenant encryption keys. The `ConnectorResolver` validates `tenant_id` on every action invocation, and the `agent_projection` schema's least-privilege grants prevent reporting-role queries from accessing credential data.

---

## 12. Operator Surface Integration

### 12.1 Dashboard Widgets

| Widget | Data |
|--------|------|
| **New: `ConnectorHealth`** | Health status grid for all connected services: green/yellow/red with credential expiry countdown |
| **New: `ConnectorUsage`** | Top connectors by invocation count, average latency, success rate (24h rolling) |
| `ReliabilityScore` | Extended with connector-attributed failure rate contribution |
| `BudgetUtilization` | Extended with external API cost attribution per workflow |
| `EscalationEvents` | Extended with connector-triggered escalations (auth failures, rate limit exhaustion) |

### 12.2 Alerts

| Alert | Trigger | Severity |
|-------|---------|----------|
| Credential expiring | Token expires within 7 days and auto-refresh has failed | Warning |
| Credential expired | Token expired; connector actions will fail | Critical |
| Rate limit approaching | > 80% of daily API quota consumed | Warning |
| Rate limit exhausted | 100% of daily API quota consumed; actions queued or rejected | Critical |
| Connector degraded | Health score drops below 0.5 | Warning |
| Webhook delivery failures | > 50% webhook delivery failure rate over 1 hour | Warning |

---

## 13. Feature Flags

| Flag | Subsystem | Description |
|------|-----------|-------------|
| `connectors.enabled` | Connectors | Master toggle for connector system |
| `connectors.ui_enabled` | Connectors | UI navigation and management pages |
| `connectors.webhooks_enabled` | Connectors | Push-based webhook support |
| `connectors.auto_resolve` | Connectors | Automatic connector matching during delegation (agent can discover which connectors are available) |
| `connectors.write_actions` | Connectors | Enable write/mutate actions (vs read-only mode for safe rollout) |
| `connectors.credential_refresh` | Connectors | Automatic background token refresh |

### Feature Flag → Delivery Phase Mapping

| Flag | Introduced In | Safe to Enable After |
|------|--------------|---------------------|
| `connectors.enabled` | Phase 1 | Phase 1 complete (foundation + credentials) |
| `connectors.credential_refresh` | Phase 2 | Phase 2 complete (runtime + refresh job) |
| `connectors.write_actions` | Phase 2 | Phase 3 complete (Tier 1 connectors tested with writes) |
| `connectors.ui_enabled` | Phase 4 | Phase 4 complete (UI pages shipped) |
| `connectors.webhooks_enabled` | Phase 5 | Phase 5 complete (webhook ingestion tested) |
| `connectors.auto_resolve` | Phase 7 | Phase 7 complete (integration tests pass) |

**Rollout sequence:** Enable `connectors.enabled` in read-only mode first (with `connectors.write_actions` off), then progressively enable write actions, UI, webhooks, and auto-resolve as each phase hardens.

---

## 14. Delivery Phases

### Phase 1: Foundation (3–4 weeks)

- [ ] Database migrations (6 tables + indexes)
- [ ] `AgentConnector`, `ConnectorConnection`, `ConnectorCredential` models + repositories
- [ ] Credential vault: encryption, storage, decryption service
- [ ] OAuth 2.0 authorization code flow with PKCE
- [ ] API key authentication flow
- [ ] Connector manifest parser and registry loader
- [ ] Connection lifecycle: connect, test, disconnect
- [ ] CLI commands: `connector:list`, `connector:connect`, `connector:disconnect`, `connector:test`, `connector:health`
- [ ] Feature flags registration

### Phase 2: Connector Runtime (3–4 weeks)

- [ ] HTTP client with retry, circuit breaker, rate limiting
- [ ] Credential refresh background job
- [ ] Connector action execution pipeline (resolve → policy gate → auth → execute → normalize → telemetry)
- [ ] PII redaction for memory integration
- [ ] Connector health scoring algorithm
- [ ] Telemetry events: `connector.invoked`, `connector.completed`, `connector.failed`
- [ ] Cost attribution to workflow budgets

### Phase 3: Tier 1 Connectors (4–5 weeks)

- [ ] Xero (OAuth 2.0, invoices, contacts, bank transactions, reports)
- [ ] Sage 50/200 (API key, invoices, customers, products)
- [ ] Salesforce (OAuth 2.0, leads, contacts, opportunities)
- [ ] HubSpot (OAuth 2.0, contacts, companies, deals)
- [ ] Microsoft Teams (OAuth 2.0, messages, channels)
- [ ] Slack (OAuth 2.0, messages, channels, files)
- [ ] Google Workspace (OAuth 2.0, Gmail, Drive, Calendar, Sheets)
- [ ] Microsoft 365 (OAuth 2.0, Outlook, OneDrive, Calendar)
- [ ] Stripe (API key, charges, invoices, customers)
- [ ] GoCardless (OAuth 2.0, mandates, payments)
- [ ] DocuSign (OAuth 2.0, envelopes, signing)
- [ ] Monday.com (API key, boards, items)
- [ ] Notion (OAuth 2.0, databases, pages)

### Phase 4: UI & Monitoring (2–3 weeks)

- [ ] Connector library browser page (`/tools/connectors`)
- [ ] Connected services management page
- [ ] Connector detail view with telemetry
- [ ] OAuth flow UI (redirect + callback handling)
- [ ] Dashboard widgets: ConnectorHealth, ConnectorUsage
- [ ] Alert configuration for credential/rate limit events

### Phase 5: Tier 2 Connectors + Webhooks (3–4 weeks)

- [ ] Webhook ingestion endpoint with signature verification
- [ ] Webhook event normalization and routing
- [ ] Dead-letter queue for failed webhook delivery
- [ ] Tier 2 connectors: BrightHR, Breathe HR, Zendesk, Freshdesk, Mailchimp, ActiveCampaign, Calendly, Jira, Asana, QuickBooks, FreeAgent, Shopify, Trello, Dropbox

### Phase 6: Tier 3 Vertical Connectors (4–5 weeks)

- [ ] ConnectWise Manage, Datto Autotask (IT/MSP)
- [ ] CareLineLive, Person Centred Software (Healthcare/Care)
- [ ] ServiceM8, Simpro (Facilities)
- [ ] Brightpearl, ShipStation (Logistics)
- [ ] Procore, Sage Construction (Construction)
- [ ] Alto, Reapit (Property/Estate Agency)
- [ ] Bullhorn, Vincere (Recruitment)
- [ ] Clio, LEAP (Legal)
- [ ] Acturis, Open GI (Insurance)

### Phase 7: Messenger + Org Layer Integration & Hardening (2–3 weeks)

- [ ] Messenger chat commands (connect, list, test, disconnect)
- [ ] Org layer connector assignment (named AI employees → connector access profiles)
- [ ] Integration tests for all Tier 1 connectors
- [ ] Load testing (concurrent connector invocations, rate limit behaviour)
- [ ] Security audit (credential isolation, OAuth flow, webhook signature verification)
- [ ] Documentation (product docs, API docs, connector authoring guide for Phase 2 SDK)

**Estimated total: 21–28 weeks**

---

## 15. Risk Boundaries

1. **External API instability.** Third-party APIs change without notice, breaking connector actions. Mitigation: version-pinned action schemas, health monitoring with automatic degradation alerts, and a connector-specific error taxonomy.
2. **Credential security is a single point of failure.** A breach of the credential vault exposes all tenant integrations. Mitigation: per-tenant encryption keys, AES-256-GCM, key rotation support, and no raw token logging.
3. **Rate limit exhaustion blocks business workflows.** Aggressive agent invocations can burn through daily API quotas. Mitigation: quota tracking with proactive alerts at 80%, automatic backoff at limit, and per-workflow budget caps.
4. **OAuth flow complexity.** Different providers implement OAuth differently (PKCE support, token expiry, refresh semantics). Mitigation: per-connector auth configuration, automated test suite for OAuth flows, and degradation alerting.
5. **Webhook reliability.** External services may not guarantee delivery; webhook endpoints must handle duplicates and out-of-order events. Mitigation: idempotent dedupe by `(external_event_id, connector_id)`, dead-letter queue, and manual replay.
6. **UK vertical connector API access.** Some industry-specific platforms (Acturis, Alto, Person Centred Software) may require partnership agreements for API access. Mitigation: early outreach to these vendors; fall back to webhook/CSV import if API access is restricted.
7. **PII leakage through connector data.** External systems contain sensitive personal data that flows into agent context and memory. Mitigation: PII field declarations per action schema, automatic redaction before memory formation, and GDPR/UK DPA compliance checks.

---

## 16. Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Connector availability | > 99.5% uptime per connector | Health score over rolling 30 days |
| Connection success rate | > 95% | Successful OAuth/API key connections / total attempts |
| Action success rate | > 98% (excluding provider-side 5xx and downtime) | Successful invocations / total invocations where `outcome != 'auth_failed'` and external `http_status < 500` are excluded from the denominator |
| Credential refresh success rate | > 99% | Successful auto-refreshes / total refresh attempts |
| Median action latency | < 2 seconds | p50 across all connector actions |
| Webhook delivery rate | > 95% | Successfully routed events / total received |
| Tier 1 coverage | 13 connectors at launch | Connectors available and tested |
| Full connector coverage | 45 connectors within 6 months | All tiers shipped and connected |
| Industry vertical hit rate | ≥ 1 connector per target vertical | Verticals with dedicated connector |

---

## 17. Open Questions

1. **Should connector actions be auto-discovered by agents, or explicitly assigned per workflow?** Current design supports both: `connectors.auto_resolve` flag enables agent discovery, but workflows can also declare required connectors.
2. **What is the maximum number of concurrent connections per tenant?** Proposed: 20 in Phase 1, expandable.
3. **How do we handle providers that require IP whitelisting?** Local-first deployment means each tenant's IP is different. May need to document requirements per connector.
4. **Should we build a connector SDK in Phase 2 or rely on community contributions?** SDK lowers barrier for custom connectors but adds maintenance burden.
5. **How do we test connectors against live APIs without incurring costs?** Proposed: mock servers for CI, sandbox/test accounts for integration tests, real API calls only in staging.
6. **Should webhook endpoints be globally routable or require user-configured tunnels for local-first deployment?** Phase 1 proposes webhooks only work in deployed (non-local) mode, or with user-configured ngrok/Cloudflare tunnel.
7. **How do we handle connector data residency requirements?** Some industries (finance, healthcare) require data to stay in UK. Connector runtime is local-first which satisfies this, but webhook payloads transit through external services.
