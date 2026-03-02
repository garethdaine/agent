# Implementation Plan

Derived from discovery session 12.

# Messenger Integration Feature Completion - Implementation Plan

## Executive Summary

Complete the messenger integration subsystem by replacing all placeholder/simulation code with real implementations, unifying webhook architecture to canonical API routes, implementing full authorization, integrating dead-letter handling, and achieving comprehensive test coverage across all four providers.

---

## Phase 1: Webhook Architecture Unification

### 1.1 Remove Legacy Web Route Controllers

**Objective**: Eliminate duplicate webhook surface by removing legacy controllers and routes.

**Files to Delete**:
- `app/Http/Controllers/Messenger/DiscordWebhookController.php`
- `app/Http/Controllers/Messenger/WhatsAppWebhookController.php`

**Route Modifications** (`routes/web.php`):
- Remove lines 26-35 containing legacy webhook routes for Discord and WhatsApp
- Verify no other code references these controllers before deletion

**Verification**:
- Run `grep -r "DiscordWebhookController" app/` returns zero results
- Run `grep -r "WhatsAppWebhookController" app/` returns zero results
- Application boots without errors

### 1.2 Add WhatsApp GET Verification to API Routes

**Objective**: Support WhatsApp webhook verification (hub.mode=subscribe) on canonical API route.

**Modify** `routes/api.php`:
```php
// Change line ~194 from POST-only to match both methods
Route::match(['get', 'post'], '/connectors/whatsapp/webhook', [WebhookController::class, 'whatsapp'])
    ->name('api.connectors.whatsapp.webhook');
```

**Modify** `app/Http/Controllers/Api/V1/Messenger/WebhookController.php`:
- Add method detection in `whatsapp()` handler
- For GET requests with `hub.mode=subscribe`, return `hub.challenge` value
- For POST requests, continue existing signed event ingestion flow

```php
public function whatsapp(Request $request)
{
    if ($request->isMethod('get') && $request->query('hub_mode') === 'subscribe') {
        return $this->handleWhatsAppVerification($request);
    }
    
    return $this->handleWhatsAppEvent($request);
}

protected function handleWhatsAppVerification(Request $request): Response
{
    $verifyToken = $request->query('hub_verify_token');
    // Validate against stored verify_token for account
    // Return hub.challenge on success, 403 on failure
}
```

### 1.3 Fix WhatsApp Onboarding URL

**Modify** `app/Support/Messenger/ConnectorCredentialManager.php` line 197:
- Change returned URL from web route to API route
- Return: `/agent/api/v1/connectors/whatsapp/webhook`
- Ensure URL supports both GET (verification) and POST (events)

**Verification**:
- Connector setup returns correct API route URL
- WhatsApp verification challenge succeeds on API route
- WhatsApp events ingest correctly on same route

---

## Phase 2: Data Models and Migrations

### 2.1 Create UserNotificationSetting Model

**Create** `app/Models/UserNotificationSetting.php`:
```php
class UserNotificationSetting extends Model
{
    protected $fillable = ['user_id', 'channel', 'enabled'];
    
    protected $casts = [
        'enabled' => 'boolean',
        'channel' => 'string', // enum: email, in_app, both
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Create** migration `database/migrations/*_create_user_notification_settings_table.php`:
```php
Schema::create('user_notification_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('channel', ['email', 'in_app', 'both'])->default('email');
    $table->boolean('enabled')->default(true);
    $table->timestamps();
    
    $table->unique('user_id');
});
```

### 2.2 Create UserChatPreference Model

**Create** `app/Models/UserChatPreference.php`:
```php
class UserChatPreference extends Model
{
    protected $fillable = [
        'user_id',
        'require_confirmation_for_delete',
        'require_confirmation_for_stop',
        'require_confirmation_for_steer',
    ];
    
    protected $casts = [
        'require_confirmation_for_delete' => 'boolean',
        'require_confirmation_for_stop' => 'boolean',
        'require_confirmation_for_steer' => 'boolean',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Create** migration `database/migrations/*_create_user_chat_preferences_table.php`:
```php
Schema::create('user_chat_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->boolean('require_confirmation_for_delete')->default(true);
    $table->boolean('require_confirmation_for_stop')->default(true);
    $table->boolean('require_confirmation_for_steer')->default(false);
    $table->timestamps();
    
    $table->unique('user_id');
});
```

### 2.3 User Model Relationships

**Modify** `app/Models/User.php`:
- Add `notificationSetting()` hasOne relationship
- Add `chatPreference()` hasOne relationship
- Add accessor methods with default fallbacks for missing records

### 2.4 Settings UI Exposure

**Modify** existing user settings page/component:
- Add "Notification Preferences" section with channel dropdown (email, in-app, both) and enabled toggle
- Add "Chat Command Preferences" section with three confirmation toggles
- Ensure settings are discoverable from main user menu/navigation

**Acceptance Checks**:
- User can navigate to settings from main application menu
- Notification preference section is visible with all options
- Chat preference section is visible with all three toggles
- Changes persist and reflect in database

---

## Phase 3: Authorization Implementation

### 3.1 Replace Placeholder Policy Checks

**Modify** `app/Messenger/Validation/ChatActionPolicyValidator.php`:

**Line 106** (Job authorization):
```php
protected function authorizeJobAccess(User $user, AgentJob $job): bool
{
    return $job->user_id === $user->id;
}
```

**Line 144** (Run authorization):
```php
protected function authorizeRunAccess(User $user, AgentJobRun $run): bool
{
    return $run->job->user_id === $user->id;
}
```

**Line 189** (Steer authorization):
```php
protected function authorizeSteerAccess(User $user, AgentJobRun $run): bool
{
    return $run->job->user_id === $user->id;
}
```

### 3.2 Implement Generic Denial Response

**Ensure** all authorization failures return identical response:
```php
protected function denyAccess(): ChatActionResponse
{
    return ChatActionResponse::error('Permission denied');
}
```

**Security Requirement**: Response must not differentiate between:
- Resource does not exist
- Resource exists but user lacks permission
- Resource is in invalid state

### 3.3 Confirmation Flow Integration

**Modify** each handler that performs destructive actions to check user preferences:

```php
protected function requiresConfirmation(User $user, string $action): bool
{
    $preference = $user->chatPreference;
    
    return match($action) {
        'delete' => $preference?->require_confirmation_for_delete ?? true,
        'stop' => $preference?->require_confirmation_for_stop ?? true,
        'steer' => $preference?->require_confirmation_for_steer ?? false,
        default => false,
    };
}
```

**Handlers requiring confirmation check**:
- `JobsDeleteHandler.php`
- `RunsStopHandler.php`
- `RunsSteerHandler.php`

---

## Phase 4: Chat Action Handlers Implementation

### 4.1 JobsListHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/JobsListHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    
    $jobs = AgentJob::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->limit(25)
        ->get(['id', 'name', 'status', 'created_at']);
    
    return ChatActionResponse::success($this->formatJobList($jobs));
}
```

### 4.2 JobsCreateHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/JobsCreateHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $params = $context->getParameters();
    
    $validated = $this->validateCreateParams($params);
    
    $job = AgentJob::create([
        'user_id' => $user->id,
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'configuration' => $validated['configuration'] ?? [],
        'status' => 'draft',
    ]);
    
    return ChatActionResponse::success("Job '{$job->name}' created with ID: {$job->id}");
}
```

### 4.3 JobsUpdateHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/JobsUpdateHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $jobId = $context->getParameter('job_id');
    
    $job = AgentJob::find($jobId);
    
    if (!$job || !$this->policyValidator->authorizeJobAccess($user, $job)) {
        return ChatActionResponse::error('Permission denied');
    }
    
    $job->update($this->extractUpdateableFields($context->getParameters()));
    
    return ChatActionResponse::success("Job '{$job->name}' updated");
}
```

### 4.4 JobsDeleteHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/JobsDeleteHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $jobId = $context->getParameter('job_id');
    
    $job = AgentJob::find($jobId);
    
    if (!$job || !$this->policyValidator->authorizeJobAccess($user, $job)) {
        return ChatActionResponse::error('Permission denied');
    }
    
    if ($this->requiresConfirmation($user, 'delete') && !$context->isConfirmed()) {
        return ChatActionResponse::requireConfirmation(
            "Are you sure you want to delete job '{$job->name}'?"
        );
    }
    
    $job->delete();
    
    return ChatActionResponse::success("Job deleted");
}
```

### 4.5 RunsListActiveHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/RunsListActiveHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    
    $runs = AgentJobRun::whereHas('job', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereIn('status', ['running', 'pending'])
        ->with('job:id,name')
        ->orderBy('started_at', 'desc')
        ->limit(25)
        ->get();
    
    return ChatActionResponse::success($this->formatRunList($runs));
}
```

### 4.6 RunsRunNowHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/RunsRunNowHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $jobId = $context->getParameter('job_id');
    
    $job = AgentJob::find($jobId);
    
    if (!$job || !$this->policyValidator->authorizeJobAccess($user, $job)) {
        return ChatActionResponse::error('Permission denied');
    }
    
    $run = $this->jobRunner->dispatch($job);
    
    return ChatActionResponse::success("Job '{$job->name}' started. Run ID: {$run->id}");
}
```

### 4.7 RunsStopHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/RunsStopHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $runId = $context->getParameter('run_id');
    
    $run = AgentJobRun::find($runId);
    
    if (!$run || !$this->policyValidator->authorizeRunAccess($user, $run)) {
        return ChatActionResponse::error('Permission denied');
    }
    
    if ($this->requiresConfirmation($user, 'stop') && !$context->isConfirmed()) {
        return ChatActionResponse::requireConfirmation(
            "Are you sure you want to stop run {$run->id}?"
        );
    }
    
    $this->runController->stop($run);
    
    return ChatActionResponse::success("Run {$run->id} stop requested");
}
```

### 4.8 RunsRetryHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/RunsRetryHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $runId = $context->getParameter('run_id');
    
    $run = AgentJobRun::find($runId);
    
    if (!$run || !$this->policyValidator->authorizeRunAccess($user, $run)) {
        return ChatActionResponse::error('Permission denied');
    }
    
    $newRun = $this->runController->retry($run);
    
    return ChatActionResponse::success("Retry initiated. New run ID: {$newRun->id}");
}
```

### 4.9 RunsSteerHandler Implementation

**Modify** `app/Messenger/ChatAction/Handlers/RunsSteerHandler.php`:
```php
public function handle(ChatActionContext $context): ChatActionResponse
{
    $user = $context->getAuthenticatedUser();
    $runId = $context->getParameter('run_id');
    
    $run = AgentJobRun::find($runId);
    
    if (!$run || !$this->policyValidator->authorizeSteerAccess($user, $run)) {
        return ChatActionResponse::error('Permission denied');
    }
    
    if ($this->requiresConfirmation($user, 'steer') && !$context->isConfirmed()) {
        return ChatActionResponse::requireConfirmation(
            "Are you sure you want to modify parameters for run {$run->id}?"
        );
    }
    
    $params = $this->filterCredentialFields($context->getParameters());
    $run->update(['parameters' => array_merge($run->parameters, $params)]);
    
    return ChatActionResponse::success("Run {$run->id} parameters updated");
}

protected function filterCredentialFields(array $params): array
{
    $credentialKeys = ['token', 'secret', 'api_key', 'password', 'credential'];
    
    return array_filter($params, function ($key) use ($credentialKeys) {
        foreach ($credentialKeys as $credKey) {
            if (str_contains(strtolower($key), $credKey)) {
                return false;
            }
        }
        return true;
    }, ARRAY_FILTER_USE_KEY);
}
```

---

## Phase 5: Outbound Message Failure Handling

### 5.1 Configure Retry Behavior

**Modify** `app/Jobs/Messenger/SendOutboundMessage.php`:
```php
public $tries = 4; // 1 initial + 3 retries

public $backoff = [30, 120, 480]; // 30s, 2min, 8min

public function retryUntil(): DateTime
{
    return now()->addMinutes(15);
}
```

### 5.2 Integrate Dead-Letter Manager

**Modify** `app/Jobs/Messenger/SendOutboundMessage.php` line 144 (failed method):
```php
public function failed(Throwable $exception): void
{
    $deadLetterManager = app(DeadLetterManager::class);
    
    $deadLetterManager->store([
        'type' => 'outbound_message',
        'payload' => $this->messagePayload,
        'provider' => $this->provider,
        'account_id' => $this->accountId,
        'error' => $exception->getMessage(),
        'failed_at' => now(),
    ]);
    
    $this->notifyOwner($exception);
}
```

### 5.3 Create Owner Notification

**Create** `app/Notifications/OutboundMessageFailedNotification.php`:
```php
class OutboundMessageFailedNotification extends Notification
{
    public function __construct(
        protected array $messagePayload,
        protected string $error,
        protected string $provider
    ) {}
    
    public function via(object $notifiable): array
    {
        $setting = $notifiable->notificationSetting;
        
        return match($setting?->channel ?? 'email') {
            'email' => ['mail'],
            'in_app' => ['database'],
            'both' => ['mail', 'database'],
        };
    }
    
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Outbound Message Failed')
            ->line("A message to {$this->provider} failed after all retries.")
            ->line("Error: {$this->error}");
    }
    
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'outbound_message_failed',
            'provider' => $this->provider,
            'error' => $this->error,
        ];
    }
}
```

### 5.4 Integrate Notification in Failed Handler

**Modify** `SendOutboundMessage.php`:
```php
protected function notifyOwner(Throwable $exception): void
{
    $job = AgentJob::find($this->jobId);
    
    if (!$job || !$job->user) {
        return;
    }
    
    $setting = $job->user->notificationSetting;
    
    if ($setting?->enabled !== false) {
        $job->user->notify(new OutboundMessageFailedNotification(
            $this->messagePayload,
            $exception->getMessage(),
            $this->provider
        ));
    }
}
```

---

## Phase 6: Connector Test Endpoint Enhancement

### 6.1 Create Slash Command Registrar Service

**Create** `app/Services/Messenger/SlashCommandRegistrar.php`:
```php
class SlashCommandRegistrar
{
    public function __construct(
        protected DiscordApiClient $discordClient
    ) {}
    
    public function register(string $botToken, string $applicationId): RegisterResult
    {
        $commands = $this->getCommandDefinitions();
        
        $response = $this->discordClient->registerApplicationCommands(
            $botToken,
            $applicationId,
            $commands
        );
        
        return new RegisterResult(
            success: $response->successful(),
            registeredCommands: count($commands),
            errors: $response->errors ?? []
        );
    }
    
    protected function getCommandDefinitions(): array
    {
        return [
            [
                'name' => 'jobs',
                'description' => 'List your agent jobs',
                'type' => 1,
            ],
            [
                'name' => 'run',
                'description' => 'Run an agent job',
                'type' => 1,
                'options' => [
                    ['name' => 'job_id', 'description' => 'Job ID', 'type' => 3, 'required' => true],
                ],
            ],
            // Additional commands...
        ];
    }
}
```

### 6.2 Extend Discord Test Flow

**Modify** `app/Http/Controllers/Api/V1/MessengerConnectorController.php` line 347:
```php
protected function testDiscord(array $credentials): TestResult
{
    // Step 1: Validate credentials format
    $validator = new DiscordCredentialValidator();
    $formatResult = $validator->validate($credentials);
    
    if (!$formatResult->valid) {
        return TestResult::failure('Invalid credentials format', $formatResult->errors);
    }
    
    // Step 2: API probe - verify bot token
    $probe = new DiscordApiProbe();
    $probeResult = $probe->verify($credentials['bot_token']);
    
    if (!$probeResult->success) {
        return TestResult::failure('Discord API verification failed', [
            'error' => $probeResult->error,
            'guidance' => $probeResult->guidance,
        ]);
    }
    
    // Step 3: Register slash commands on success
    $registrar = app(SlashCommandRegistrar::class);
    $registerResult = $registrar->register(
        $credentials['bot_token'],
        $credentials['application_id']
    );
    
    return TestResult::success([
        'bot_username' => $probeResult->botUsername,
        'slash_commands_registered' => $registerResult->registeredCommands,
        'webhook_url' => route('api.connectors.discord.webhook'),
    ]);
}
```

### 6.3 Extend WhatsApp Test Flow

**Modify** `MessengerConnectorController.php`:
```php
protected function testWhatsApp(array $credentials): TestResult
{
    // Step 1: Validate credentials format
    $validator = new WhatsAppCredentialValidator();
    $formatResult = $validator->validate($credentials);
    
    if (!$formatResult->valid) {
        return TestResult::failure('Invalid credentials format', $formatResult->errors);
    }
    
    // Step 2: API probe - verify access token
    $probe = new WhatsAppApiProbe();
    $probeResult = $probe->verify(
        $credentials['access_token'],
        $credentials['phone_number_id']
    );
    
    if (!$probeResult->success) {
        return TestResult::failure('WhatsApp API verification failed', [
            'error' => $probeResult->error,
        ]);
    }
    
    return TestResult::success([
        'phone_number' => $probeResult->phoneNumber,
        'business_name' => $probeResult->businessName,
        'webhook_url' => route('api.connectors.whatsapp.webhook'),
        'setup_reminder' => 'Configure your verify_token in Meta Developer Console',
    ]);
}
```

### 6.4 Fix Discord Ingress Probe 401 Handling

**Modify** `app/Messenger/Validation/IngressProbe.php` line 389:
```php
protected function handleDiscordProbeResponse(Response $response): ProbeResult
{
    if ($response->status() === 401) {
        // 401 is expected for unsigned probe calls
        return ProbeResult::guidance(
            'Webhook endpoint responding. 401 is expected for unsigned requests. ' .
            'Discord will send signed requests once configured.'
        );
    }
    
    if ($response->status() === 200) {
        return ProbeResult::success('Webhook endpoint verified');
    }
    
    return ProbeResult::failure("Unexpected status: {$response->status()}");
}
```

---

## Phase 7: Documentation and Cleanup

### 7.1 Update Installer Help Text

**Modify** `app/Console/Commands/AgentInstallCommand.php` line 26:
```php
{--messenger= : Messenger platform to configure (slack, telegram, discord, whatsapp)}
```

### 7.2 Remove All Placeholder Code

**Files requiring placeholder removal**:
- All 9 handler files: Remove `// TODO:` and `// Placeholder:` comments
- Replace simulation returns with real implementations
- Remove any `throw new NotImplementedException()` calls

**Verification**:
```bash
grep -r "TODO" app/Messenger/ChatAction/Handlers/ # Should return empty
grep -r "Placeholder" app/Messenger/ChatAction/Handlers/ # Should return empty
grep -r "simulate" app/Messenger/ChatAction/Handlers/ # Should return empty
```

---

## Phase 8: Test Coverage Implementation

### 8.1 E2E Test Structure

**Create/Modify** provider-specific test files:

```
tests/Feature/Messenger/
├── Webhooks/
│   ├── SlackWebhookTest.php
│   ├── TelegramWebhookTest.php
│   ├── DiscordWebhookTest.php (existing, extend)
│   └── WhatsAppWebhookTest.php (existing, extend)
├── Actions/
│   ├── JobActionsTest.php
│   ├── RunActionsTest.php
│   └── AuthorizationTest.php
├── Outbound/
│   ├── SendMessageTest.php
│   └── FailureHandlingTest.php
└── Connectors/
    └── ConnectorTestEndpointTest.php
```

### 8.2 Required Test Scenarios per Provider

**For each provider (Slack, Telegram, Discord, WhatsApp)**:

1. **Webhook Verification Test**:
```php
public function test_webhook_verification_succeeds(): void
{
    // Provider-specific verification challenge
}
```

2. **Signed Event Ingestion Test**:
```php
public function test_signed_event_ingests_correctly(): void
{
    // Mock valid signature, verify event processing
}
```

3. **Invalid Signature Rejection Test**:
```php
public function test_invalid_signature_returns_401(): void
{
    // Send request with bad signature, expect 401
}
```

4. **Chat Intent Parsing Test**:
```php
public function test_chat_intent_parsed_correctly(): void
{
    // Send command message, verify intent extraction
}
```

5. **Action Execution with Authorization Test**:
```php
public function test_authorized_action_executes(): void
{
    // Owner sends command, verify execution
}
```

6. **Action Execution with Denial Test**:
```php
public function test_non_owner_action_denied(): void
{
    // Non-owner sends command, verify "Permission denied"
}
```

7. **Outbound Success Test**:
```php
public function test_outbound_message_sends(): void
{
    // Mock successful provider API call
}
```

8. **Outbound Failure Flow Test**:
```php
public function test_outbound_failure_triggers_deadletter_and_notification(): void
{
    // Mock failed sends, verify dead-letter and notification
}
```

9. **Confirmation Workflow Test**:
```php
public function test_destructive_action_requires_confirmation(): void
{
    // User has confirmation enabled, verify prompt returned
}
```

### 8.3 Mock Strategy

All tests must use full mocks:
```php
protected function setUp(): void
{
    parent::setUp();
    
    Http::fake([
        'discord.com/*' => Http::response(['ok' => true], 200),
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'slack.com/*' => Http::response(['ok' => true], 200),
    ]);
}
```

### 8.4 Existing Test Verification

Run existing tests without modification:
```bash
php artisan test tests/Feature/Messenger/Webhooks/DiscordWebhookTest.php
php artisan test tests/Feature/Messenger/Webhooks/WhatsAppWebhookTest.php
php artisan test tests/Feature/Messenger/ChatOrchestrationTest.php
```

All must pass without changes to assertions.

---

## Dependency Graph

```
Phase 1 (Webhook Unification)
    └── No dependencies, can start immediately

Phase 2 (Data Models)
    └── No dependencies, can start immediately
    
Phase 3 (Authorization)
    └── Depends on: Phase 2 (UserChatPreference model for confirmation checks)

Phase 4 (Chat Action Handlers)
    └── Depends on: Phase 3 (authorization methods must exist)

Phase 5 (Outbound Failure Handling)
    └── Depends on: Phase 2 (UserNotificationSetting model)

Phase 6 (Connector Test Enhancement)
    └── Depends on: Phase 1 (canonical webhook URLs)

Phase 7 (Cleanup)
    └── Depends on: Phases 1-6 (all implementations complete)

Phase 8 (Test Coverage)
    └── Depends on: Phase 7 (all code finalized)
```

---

## Verification Checklist

### Functional Verification
- [ ] WhatsApp GET verification works on `/agent/api/v1/connectors/whatsapp/webhook`
- [ ] WhatsApp POST events work on same endpoint
- [ ] Discord slash commands register on connector test success
- [ ] All 9 chat action handlers perform real operations
- [ ] Authorization denials return "Permission denied" uniformly
- [ ] Outbound failures move to dead-letter after 3 retries
- [ ] Owner receives notification on terminal failure
- [ ] Destructive actions respect confirmation preferences

### Code Quality Verification
- [ ] Zero TODO comments in messenger handlers
- [ ] Zero placeholder/simulation code
- [ ] Legacy webhook controllers deleted
- [ ] Legacy web routes removed

### Test Verification
- [ ] All existing messenger tests pass
- [ ] E2E tests exist for all 4 providers
- [ ] Each provider has 9 scenario tests
- [ ] All tests use mocks only

### UI/UX Verification
- [ ] User can navigate to notification settings from main menu
- [ ] User can configure notification channel preference
- [ ] User can configure chat confirmation preferences
- [ ] Settings changes persist correctly

## Sections

- Phase 1: Webhook Architecture Unification
- Phase 2: Data Models and Migrations
- Phase 3: Authorization Implementation
- Phase 4: Chat Action Handlers Implementation
- Phase 5: Outbound Message Failure Handling
- Phase 6: Connector Test Endpoint Enhancement
- Phase 7: Documentation and Cleanup
- Phase 8: Test Coverage Implementation


## Risks

- Removing legacy webhook controllers may break external systems still pointing to old URLs without proper redirect strategy
- WhatsApp multi-account signature fallback scan could cause incorrect account routing if multiple accounts share app secrets
- Discord slash command registration during connector test may fail silently if application ID is incorrect
- Dead-letter integration assumes DeadLetterManager::store() interface matches expected payload structure
- Confirmation flow requires persistent state between chat messages which may not exist in current ChatActionContext
- Existing tests may have hidden dependencies on placeholder behavior that break when real implementations are added
- UserNotificationSetting channel enum migration may conflict with existing notification infrastructure
- Credential filtering in steer handler may be overly aggressive and block legitimate parameter names containing 'token' or 'key'


## Assumptions

- DeadLetterManager already exists with compatible store() method signature
- AgentJob and AgentJobRun models have user_id relationship and standard CRUD operations
- Existing Discord and WhatsApp webhook tests cover signature verification logic that will be retained
- Laravel notification system is already configured for mail and database channels
- ChatActionContext provides getAuthenticatedUser() method returning User model
- DiscordApiClient exists or can be created for slash command registration API calls
- WhatsApp Cloud API credentials include phone_number_id for account identification
- Current job runner infrastructure supports dispatch() returning AgentJobRun instance

