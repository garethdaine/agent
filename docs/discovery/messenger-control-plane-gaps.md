# Requirements Discovery Summary

Session: 12

## Messenger Integration Feature Completion

### Overview
Complete the messenger integration by replacing all placeholder/simulation code with real implementations, unifying webhook architecture, and ensuring full test coverage. The goal is zero placeholder code, zero TODO items, and all tests passing.

### Webhook Architecture Unification

**Canonical Endpoint Strategy**: Consolidate all webhooks to API routes at `/agent/api/v1/connectors/{provider}/webhook`

**Route Changes**:
- Add `Route::match(['get', 'post'], '/whatsapp/webhook', ...)` to support both WhatsApp verification (GET with hub.mode=subscribe) and event ingestion (POST)
- Remove legacy web route controllers immediately:
  - Delete `DiscordWebhookController.php` (web routes)
  - Delete `WhatsAppWebhookController.php` (web routes)
  - Remove routes from `web.php` lines 26-35
- Update `ConnectorCredentialManager.php:197` to return correct API route URLs for WhatsApp onboarding

**WhatsApp Account Resolution**: Retain current behavior—try all matching accounts and route to first successful signature match. No changes to `VerifyWebhookSignature.php:98` fallback logic.

### Chat Action Handlers Implementation

Replace placeholder code in all 9 handlers with real AgentJob/AgentJobRun domain operations:

| Handler | Integration |
|---------|-------------|
| `JobsListHandler.php` | Query `AgentJob::where('user_id', $user->id)` |
| `JobsCreateHandler.php` | Call `AgentJobController::store` logic |
| `JobsUpdateHandler.php` | Call `AgentJobController::update` logic |
| `JobsDeleteHandler.php` | Call `AgentJobController::destroy` logic |
| `RunsListActiveHandler.php` | Query `AgentJobRun::where('status', 'running')` with ownership |
| `RunsRunNowHandler.php` | Call `AgentJobController::runNow` logic |
| `RunsStopHandler.php` | Call `AgentRunController::stop` logic |
| `RunsRetryHandler.php` | Call `AgentRunController::retry` logic |
| `RunsSteerHandler.php` | Update all mutable job parameters except credentials |

**Steer Command Scope**: All mutable parameters except credentials (tokens, secrets, API keys). User assumes responsibility for mid-run parameter changes.

### Authorization Model

**Ownership Model**: Creator-only. `AgentJob.user_id` must match authenticated user.

**Policy Implementation** (replace placeholders in `ChatActionPolicyValidator.php`):
- Line 106: Check `$job->user_id === $user->id`
- Line 144: Check `$run->job->user_id === $user->id`
- Line 189: Same ownership check for steer operations

**Denial Response**: Generic "Permission denied" message. Do not reveal whether the resource exists to prevent enumeration attacks.

### Outbound Message Failure Handling

**Retry Configuration**:
- 3 retry attempts with exponential backoff: 30 seconds, 2 minutes, 8 minutes
- Configure in `SendOutboundMessage.php` job properties

**Dead-Letter Integration**:
- On terminal failure (after 3 retries), call `DeadLetterManager::store()`
- Trigger owner notification via configured fallback channel

**Notification Preferences**: User-configurable via `UserNotificationSetting` model

### New Data Models

**UserNotificationSetting Model**:
```
Schema:
- id (bigint, PK)
- user_id (bigint, FK to users)
- channel (enum: 'email', 'in_app', 'both')
- enabled (boolean, default true)
- created_at, updated_at
```

**UserChatPreference Model**:
```
Schema:
- id (bigint, PK)
- user_id (bigint, FK to users)
- require_confirmation_for_delete (boolean, default true)
- require_confirmation_for_stop (boolean, default true)
- require_confirmation_for_steer (boolean, default false)
- created_at, updated_at
```

### Connector Test Endpoint Enhancement

Extend `MessengerConnectorController::test` (line 347) for all providers:

**Discord Test Flow**:
1. Validate credentials format (DiscordCredentialValidator)
2. API probe: Call Discord API to verify bot token
3. Register slash commands on successful validation
4. Return diagnostic results with setup guidance

**WhatsApp Test Flow**:
1. Validate credentials format (WhatsAppCredentialValidator)
2. API probe: Call WhatsApp Cloud API to verify access token
3. Webhook ingress verification where possible
4. Return diagnostic results including verify_token reminder

**Discord Ingress Probe Fix**: Update `IngressProbe.php:389` to treat 401 as non-blocking guidance (expected for unsigned probe calls), not hard failure.

### Discord Slash Command Registration

Wire registration into API connector lifecycle:
- Trigger on successful `MessengerConnectorController::test` response
- Call Discord Applications Commands API to register slash commands
- Port logic from `AgentInstallCommand.php:481` to reusable service

### Files to Modify

**Remove**:
- `app/Http/Controllers/Messenger/DiscordWebhookController.php`
- `app/Http/Controllers/Messenger/WhatsAppWebhookController.php`
- Related routes in `routes/web.php` (lines 26-35)

**Modify**:
- `routes/api.php`: Add GET support for WhatsApp webhook
- `app/Http/Controllers/Api/V1/Messenger/WebhookController.php`: Add WhatsApp verify handler
- `app/Support/Messenger/ConnectorCredentialManager.php:197`: Fix WhatsApp webhook URL
- `app/Messenger/ChatAction/Handlers/*.php`: All 9 handlers
- `app/Messenger/Validation/ChatActionPolicyValidator.php`: Lines 106, 144, 189
- `app/Http/Controllers/Api/V1/MessengerConnectorController.php:347`: Discord/WhatsApp test
- `app/Jobs/Messenger/SendOutboundMessage.php:144`: Dead-letter integration
- `app/Messenger/Validation/IngressProbe.php:389`: Discord 401 handling
- `app/Console/Commands/AgentInstallCommand.php:26`: Update help text for all 4 providers

**Create**:
- `app/Models/UserNotificationSetting.php`
- `app/Models/UserChatPreference.php`
- `database/migrations/*_create_user_notification_settings_table.php`
- `database/migrations/*_create_user_chat_preferences_table.php`
- `app/Services/Messenger/SlashCommandRegistrar.php`
- `app/Notifications/OutboundMessageFailedNotification.php`

### Test Coverage

**Strategy**: Full mocks for all provider APIs. Test internal logic only.

**Required E2E Test Scenarios per Provider** (Slack, Telegram, Discord, WhatsApp):
1. Webhook verification flow (GET for WhatsApp)
2. Signed event ingestion (valid signature)
3. Invalid signature rejection (401 response)
4. Chat intent parsing and routing
5. Action execution with authorization check
6. Action execution with ownership denial
7. Outbound message success flow
8. Outbound message failure → dead-letter → notification flow
9. Confirmation workflow for destructive actions (when enabled)

**Existing Tests to Verify**:
- `tests/Feature/Messenger/Webhooks/DiscordWebhookTest.php`
- `tests/Feature/Messenger/Webhooks/WhatsAppWebhookTest.php`
- `tests/Feature/Messenger/ChatOrchestrationTest.php`

## Goals

- Unify webhook architecture to single canonical API route per provider at /agent/api/v1/connectors/{provider}/webhook
- Implement real chat action handlers for all 9 job/run operations (list, create, update, delete, run-now, stop, retry, steer, list-active)
- Replace placeholder authorization checks with creator-only ownership validation in ChatActionPolicyValidator
- Integrate SendOutboundMessage with DeadLetterManager and trigger owner notifications on terminal failure
- Extend MessengerConnectorController::test to provide full diagnostics for Discord and WhatsApp (credentials, API probe, ingress verification)
- Wire Discord slash command registration into connector test success flow
- Add WhatsApp GET verification handler to API routes for hub.mode=subscribe challenge
- Remove legacy web route webhook controllers (DiscordWebhookController, WhatsAppWebhookController)
- Create UserNotificationSetting model for fallback notification channel preferences
- Create UserChatPreference model for destructive action confirmation settings
- Implement configurable per-user confirmation requirements for delete, stop, and steer commands
- Fix Discord ingress probe to treat 401 as non-blocking guidance rather than hard failure
- Achieve 100% test pass rate with full mock-based E2E coverage for all provider flows
- Eliminate all placeholder code, TODO comments, and simulation logic from messenger subsystem


## Constraints

- Authorization model must be creator-only ownership (user_id match), not team-based
- Authorization denial must return generic 'Permission denied' without revealing resource existence
- Outbound retry configuration: exactly 3 retries with exponential backoff at 30s, 2min, 8min intervals
- WhatsApp account resolution must retain current fallback signature scan behavior for multi-account setups
- Legacy webhook controllers must be removed immediately with no deprecation period
- Steer command can modify all mutable parameters except credentials (tokens, secrets, API keys)
- Test strategy must use full mocks only—no provider sandbox environments or recorded fixtures
- UserNotificationSetting and UserChatPreference must be separate models following existing *Setting pattern
- Discord slash commands must register during connector test success, not on creation or activation


## Acceptance Criteria

- A WhatsApp account can send commands via chat that create, update, delete, and run real agent jobs
- A Discord account can send slash commands that create, update, delete, and run real agent jobs
- Unauthorized or non-owner job/run actions return 'Permission denied' without resource existence disclosure
- MessengerConnectorController::test returns real validation results for Slack, Telegram, Discord, and WhatsApp
- Discord connector test success triggers slash command registration with Discord API
- WhatsApp webhook verification (GET with hub.mode=subscribe) works on API route /agent/api/v1/connectors/whatsapp/webhook
- WhatsApp webhook events (POST) work on same API route with signature verification
- Legacy web route controllers for Discord and WhatsApp are deleted and routes removed
- Outbound message failure after 3 retries moves message to dead-letter queue via DeadLetterManager
- Outbound message terminal failure triggers notification to job owner via their configured preference
- User can configure notification channel preference (email, in-app, both) via UserNotificationSetting
- User can configure confirmation requirements for delete/stop/steer via UserChatPreference
- Destructive actions respect user's confirmation preference setting
- Discord ingress probe returns guidance (not failure) when 401 received during unsigned probe
- All existing messenger tests pass without modification to test assertions
- E2E tests exist for each provider covering: webhook verify, signed ingest, auth denial, action execution, outbound flow
- Zero placeholder comments, TODO items, or simulation code remains in messenger handlers
- AgentInstallCommand help text lists all four providers (Slack, Telegram, Discord, WhatsApp)

