# Implementation Plan

Derived from discovery session 1.

# Implementation Plan

Derived from discovery session 1.

# Messenger Control Plane — Implementation Plan

## Phase A: Foundation and Core Infrastructure

### A.1 Database Migrations and Models

Create the data layer for messenger control plane operations.

**Files to Create:**
- `database/migrations/2026_02_20_150000_create_connector_accounts_table.php`
- `database/migrations/2026_02_20_150001_create_chat_sessions_table.php`
- `database/migrations/2026_02_20_150002_create_chat_messages_table.php`
- `database/migrations/2026_02_20_150003_create_chat_actions_table.php`
- `database/migrations/2026_02_20_150004_create_messenger_identity_links_table.php`
- `database/migrations/2026_02_20_150005_create_chat_attachments_table.php`
- `database/migrations/2026_02_20_150006_create_account_link_tokens_table.php`
- `database/migrations/2026_02_20_150007_create_pending_confirmations_table.php`
- `app/Models/ConnectorAccount.php`
- `app/Models/ChatSession.php`
- `app/Models/ChatMessage.php`
- `app/Models/ChatAction.php`
- `app/Models/MessengerIdentityLink.php`
- `app/Models/ChatAttachment.php`
- `app/Models/AccountLinkToken.php`
- `app/Models/PendingConfirmation.php`

**Implementation Details:**

`connector_accounts` migration:
```php
Schema::create('connector_accounts', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('provider', 32); // enum: slack, telegram, discord, whatsapp
    $table->string('name', 255);
    $table->text('credentials'); // encrypted JSON
    $table->string('webhook_secret', 255)->nullable();
    $table->string('connection_mode', 16)->default('local'); // enum: local, webhook
    $table->string('status', 32)->default('disconnected'); // enum: connected, disconnected, error
    $table->json('config')->nullable(); // provider-specific settings
    $table->string('account_key', 64); // deterministic routing key for multi-bot
    $table->timestamps();
    
    $table->unique(['provider', 'account_key']); // composite unique per provider
    $table->index(['provider', 'status']);
});
```

`chat_messages` migration with unique constraint for idempotency:
```php
Schema::create('chat_messages', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('chat_session_id');
    $table->uuid('connector_account_id');
    $table->string('direction', 16); // inbound, outbound
    $table->text('content');
    $table->json('attachment_ids')->nullable();
    $table->string('idempotency_key', 64);
    $table->string('provider_event_id', 255)->nullable();
    $table->string('provider_message_id', 255)->nullable();
    $table->timestampTz('provider_timestamp')->nullable();
    $table->timestampTz('created_at');
    
    $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
    $table->foreign('connector_account_id')->references('id')->on('connector_accounts')->cascadeOnDelete();
    $table->unique(['connector_account_id', 'idempotency_key']);
    $table->index(['chat_session_id', 'created_at']);
});
```

`chat_actions` migration with extended status enum:
```php
Schema::create('chat_actions', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('chat_message_id');
    $table->string('action_type', 32); // enum: jobs.create, jobs.update, jobs.delete, jobs.list, runs.list_active, runs.stop, runs.retry, runs.run_now, runs.steer
    $table->json('parameters');
    $table->string('status', 16)->default('pending'); // enum: pending, executing, completed, failed, cancelled, timeout
    $table->json('result')->nullable();
    $table->text('error')->nullable();
    $table->boolean('requires_confirmation')->default(false);
    $table->timestampTz('confirmed_at')->nullable();
    $table->timestampTz('created_at');
    $table->timestampTz('executed_at')->nullable();
    
    $table->foreign('chat_message_id')->references('id')->on('chat_messages')->cascadeOnDelete();
    $table->index(['status', 'created_at']);
});
```

`account_link_tokens` migration for DB fallback:
```php
Schema::create('account_link_tokens', function (Blueprint $table): void {
    $table->string('token_hash', 64)->primary();
    $table->uuid('connector_account_id');
    $table->string('provider_user_id', 255);
    $table->timestampTz('issued_at');
    $table->timestampTz('expires_at');
    $table->timestampTz('consumed_at')->nullable();
    
    $table->foreign('connector_account_id')->references('id')->on('connector_accounts')->cascadeOnDelete();
    $table->index('expires_at');
});
```

`pending_confirmations` migration for confirmation workflow:
```php
Schema::create('pending_confirmations', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('chat_action_id');
    $table->uuid('chat_session_id');
    $table->uuid('connector_account_id');
    $table->string('confirmation_token', 64)->unique();
    $table->string('provider_message_id', 255)->nullable(); // for button callbacks
    $table->json('callback_data')->nullable(); // provider-specific callback routing
    $table->timestampTz('expires_at');
    $table->timestampTz('confirmed_at')->nullable();
    $table->timestampTz('cancelled_at')->nullable();
    $table->timestampTz('created_at');
    
    $table->foreign('chat_action_id')->references('id')->on('chat_actions')->cascadeOnDelete();
    $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
    $table->foreign('connector_account_id')->references('id')->on('connector_accounts')->cascadeOnDelete();
    $table->index(['connector_account_id', 'provider_message_id']);
    $table->index('expires_at');
});
```

**ChatAction Model status enum:**
```php
// In ChatAction model
protected $casts = [
    'parameters' => 'array',
    'result' => 'array',
    'requires_confirmation' => 'boolean',
    'confirmed_at' => 'datetime',
    'created_at' => 'datetime',
    'executed_at' => 'datetime',
];

// Valid statuses: pending, executing, completed, failed, cancelled, timeout
public function isPending(): bool
{
    return $this->status === 'pending';
}

public function isTerminal(): bool
{
    return in_array($this->status, ['completed', 'failed', 'cancelled', 'timeout'], true);
}
```

### A.2 Connector Adapter Architecture

Build the abstract connector interface and provider-specific implementations.

**Files to Create:**
- `app/Support/Messenger/Contracts/ConnectorAdapterInterface.php`
- `app/Support/Messenger/Contracts/InboundMessageContract.php`
- `app/Support/Messenger/Contracts/OutboundMessageContract.php`
- `app/Support/Messenger/ConnectorAdapterManager.php`
- `app/Support/Messenger/Adapters/SlackAdapter.php`
- `app/Support/Messenger/Adapters/TelegramAdapter.php`
- `app/Support/Messenger/Adapters/Concerns/VerifiesSignatures.php`
- `app/Support/Messenger/Adapters/Concerns/HandlesRateLimits.php`
- `app/Support/Messenger/Adapters/Concerns/ManagesThreading.php`
- `app/Support/Messenger/AccountResolver.php`

**Interface Definition:**
```php
interface ConnectorAdapterInterface
{
    public function verifyWebhookSignature(Request $request, ConnectorAccount $account): bool;
    public function normalizeInboundMessage(array $payload, ConnectorAccount $account): InboundMessageContract;
    public function sendMessage(OutboundMessageContract $message, ConnectorAccount $account): array;
    public function sendThreadReply(OutboundMessageContract $message, string $threadId, ConnectorAccount $account): array;
    public function editMessage(string $messageId, OutboundMessageContract $message, ConnectorAccount $account): array;
    public function extractProviderUserId(array $payload): string;
    public function generateIdempotencyKey(array $payload, ConnectorAccount $account): string;
    public function supportsLocalMode(): bool;
    public function startLocalConnector(ConnectorAccount $account): void;
    public function stopLocalConnector(ConnectorAccount $account): void;
    public function extractAccountKey(Request $request): ?string;
    public function sendConfirmationPrompt(ChatAction $action, PendingConfirmation $confirmation, ConnectorAccount $account): array;
    public function supportsInteractiveButtons(): bool;
    public function resolveThreadingStrategy(ChatSession $session, ConnectorAccount $account): ThreadingStrategy;
    public function extractConfirmationId(array $callbackPayload): ?string;
    public function extractConfirmationDecision(array $callbackPayload): bool;
    public function extractCallbackMessageId(array $callbackPayload): ?string;
}
```

**Account Resolver (multi-bot routing):**
```php
class AccountResolver
{
    public function resolve(string $provider, Request $request): ?ConnectorAccount
    {
        $adapter = $this->adapterManager->adapter($provider);
        $accountKey = $adapter->extractAccountKey($request);
        
        if ($accountKey) {
            // Deterministic lookup by composite key (provider + account_key)
            return ConnectorAccount::where('provider', $provider)
                ->where('account_key', $accountKey)
                ->where('status', 'connected')
                ->first();
        }
        
        // Fallback: signature-based verification against all accounts
        return $this->resolveBySignatureVerification($provider, $request, $adapter);
    }
    
    private function resolveBySignatureVerification(
        string $provider,
        Request $request,
        ConnectorAdapterInterface $adapter
    ): ?ConnectorAccount {
        $accounts = ConnectorAccount::where('provider', $provider)
            ->where('status', 'connected')
            ->get();
            
        foreach ($accounts as $account) {
            if ($adapter->verifyWebhookSignature($request, $account)) {
                return $account;
            }
        }
        
        return null;
    }
}
```

**Provider-Specific Account Key Extraction:**

Slack:
```php
public function extractAccountKey(Request $request): ?string
{
    // Slack includes team_id in all event payloads
    return $request->input('team_id') 
        ?? $request->input('event.team') 
        ?? null;
}
```

Telegram:
```php
public function extractAccountKey(Request $request): ?string
{
    // Bot token embedded in webhook URL path: /connectors/telegram/webhook/{accountKey}
    return $request->route('accountKey');
}
```

**Slack Adapter Implementation:**
- Socket Mode support for local connector mode (default)
- HMAC-SHA256 signature verification using `X-Slack-Request-Timestamp`
- Timestamp-based replay protection (300-second window configurable)
- Native thread support via `thread_ts`
- Message editing fallback via `chat.update`
- Interactive buttons via Block Kit for confirmation prompts

**Telegram Adapter Implementation:**
- Long polling for local connector mode (default)
- Token-based authentication (bot token in webhook URL)
- Event ID deduplication via `update_id`
- Reply-to threading via `reply_to_message_id`
- Quote reply fallback
- Inline keyboard buttons for confirmation prompts

### A.3 Webhook Security Infrastructure

Implement signature verification and replay protection.

**Files to Create:**
- `app/Support/Messenger/Security/SignatureVerifier.php`
- `app/Support/Messenger/Security/ReplayProtection.php`
- `app/Support/Messenger/Security/Strategies/HmacSha256Verifier.php`
- `app/Support/Messenger/Security/Strategies/Ed25519Verifier.php`
- `app/Support/Messenger/Security/Strategies/TokenVerifier.php`
- `app/Support/Messenger/Security/Strategies/TimestampReplayProtection.php`
- `app/Support/Messenger/Security/Strategies/EventIdReplayProtection.php`

**Signature Verification Strategy Pattern:**
```php
class SignatureVerifier
{
    public function verify(Request $request, ConnectorAccount $account): bool
    {
        $config = $account->config['signature_verification'] ?? [];
        $scheme = $config['scheme'] ?? 'hmac_sha256';
        
        return match ($scheme) {
            'hmac_sha256' => $this->hmacVerifier->verify($request, $config),
            'ed25519' => $this->ed25519Verifier->verify($request, $config),
            'token' => $this->tokenVerifier->verify($request, $config),
            default => false,
        };
    }
}
```

**Ed25519 Verification for Discord:**
```php
public function verify(Request $request, array $config): bool
{
    $signature = $request->header('X-Signature-Ed25519');
    $timestamp = $request->header('X-Signature-Timestamp');
    $body = $request->getContent();
    $publicKey = $config['public_key'];
    
    if (!$signature || !$timestamp || !$publicKey) {
        return false;
    }
    
    $message = $timestamp . $body;
    return sodium_crypto_sign_verify_detached(
        hex2bin($signature),
        $message,
        hex2bin($publicKey)
    );
}
```

**Replay Protection:**
```php
class ReplayProtection
{
    public function isReplay(Request $request, ConnectorAccount $account): bool
    {
        $config = $account->config['replay_protection'] ?? [];
        $strategy = $config['strategy'] ?? 'timestamp';
        
        return match ($strategy) {
            'timestamp' => $this->timestampProtection->isReplay($request, $config),
            'event_id_dedupe' => $this->eventIdProtection->isReplay($request, $config, $account),
            default => false,
        };
    }
}
```

### A.4 Account-Link Flow

Implement identity linking between messenger users and Agent users.

**Files to Create:**
- `app/Support/Messenger/AccountLink/TokenGenerator.php`
- `app/Support/Messenger/AccountLink/TokenValidator.php`
- `app/Support/Messenger/AccountLink/TokenStorage.php`
- `app/Support/Messenger/AccountLink/IdentityLinkExpiryChecker.php`
- `app/Http/Controllers/MessengerLinkController.php`
- `resources/js/Pages/MessengerLink/Confirm.vue`
- `resources/js/Pages/MessengerLink/Success.vue`
- `resources/js/Pages/MessengerLink/Error.vue`
- `app/Jobs/CleanupExpiredAccountLinkTokens.php`

**Token Generator:**
```php
class TokenGenerator
{
    public function generate(ConnectorAccount $account, string $providerUserId): string
    {
        $payload = [
            'connector_account_id' => $account->id,
            'provider_user_id' => $providerUserId,
            'issued_at' => now()->unix(),
        ];
        
        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, config('app.key'));
        
        return base64_encode($payloadJson . '.' . $signature);
    }
}
```

**Token Storage (Redis primary, DB fallback):**
```php
class TokenStorage
{
    public function store(string $token, array $payload): void
    {
        $hash = hash('sha256', $token);
        $ttl = 15 * 60; // 15 minutes
        
        try {
            Cache::store('redis')->put("account_link:{$hash}", $payload, $ttl);
        } catch (\Throwable $e) {
            // DB fallback
            AccountLinkToken::create([
                'token_hash' => $hash,
                'connector_account_id' => $payload['connector_account_id'],
                'provider_user_id' => $payload['provider_user_id'],
                'issued_at' => now(),
                'expires_at' => now()->addSeconds($ttl),
            ]);
        }
    }
    
    public function consume(string $token): ?array
    {
        $hash = hash('sha256', $token);
        
        // Try Redis first (atomic pull)
        $payload = Cache::store('redis')->pull("account_link:{$hash}");
        if ($payload) {
            return $payload;
        }
        
        // DB fallback with atomic consumption
        $affected = AccountLinkToken::where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);
            
        if ($affected === 0) {
            return null; // Token already consumed, expired, or not found
        }
        
        $record = AccountLinkToken::where('token_hash', $hash)->first();
        return [
            'connector_account_id' => $record->connector_account_id,
            'provider_user_id' => $record->provider_user_id,
        ];
    }
    
    public function invalidateOnFailure(string $token): void
    {
        // Called when POST fails after consume to ensure token cannot be reused
        $hash = hash('sha256', $token);
        
        // Redis: already removed by pull
        // DB: already marked consumed_at, no action needed
        // This method exists for explicit documentation and potential future cleanup
    }
}
```

**Identity Link Expiry Checker:**
```php
class IdentityLinkExpiryChecker
{
    public function checkAndHandleExpiry(
        MessengerIdentityLink $link,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter,
        InboundMessageContract $inbound
    ): bool {
        if (!$link->expires_at) {
            return true; // No expiry configured
        }
        
        if (now()->lt($link->expires_at)) {
            return true; // Not expired
        }
        
        // Link expired — prompt for re-authentication
        $this->sendReauthPrompt($account, $adapter, $inbound, $link);
        
        return false;
    }
    
    private function sendReauthPrompt(
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter,
        InboundMessageContract $inbound,
        MessengerIdentityLink $link
    ): void {
        $token = $this->tokenGenerator->generate($account, $link->provider_user_id);
        $this->tokenStorage->store($token, [
            'connector_account_id' => $account->id,
            'provider_user_id' => $link->provider_user_id,
        ]);
        
        $linkUrl = route('messenger-link.show', ['token' => $token]);
        
        $message = new OutboundMessage(
            content: "Your session has expired. Please re-authenticate to continue:\n{$linkUrl}",
            channelId: $inbound->channelId,
            threadId: $inbound->threadId,
        );
        
        $adapter->sendMessage($message, $account);
    }
}
```

**Web Routes (routes/web.php):**
```php
Route::get('/messenger-link/{token}', [MessengerLinkController::class, 'show'])
    ->name('messenger-link.show');

Route::post('/messenger-link/{token}/complete', [MessengerLinkController::class, 'complete'])
    ->middleware('auth')
    ->name('messenger-link.complete');
```

**MessengerLinkController:**
```php
class MessengerLinkController extends Controller
{
    public function show(string $token, TokenValidator $validator)
    {
        $validation = $validator->validate($token);
        
        if (!$validation['valid']) {
            return Inertia::render('MessengerLink/Error', [
                'error' => $validation['error'],
            ]);
        }
        
        return Inertia::render('MessengerLink/Confirm', [
            'token' => $token,
            'provider' => $validation['account']->provider,
            'accountName' => $validation['account']->name,
        ]);
    }
    
    public function complete(
        string $token,
        Request $request,
        TokenStorage $storage
    ) {
        $payload = $storage->consume($token);
        
        if (!$payload) {
            // Token invalid, expired, or already consumed
            return Inertia::render('MessengerLink/Error', [
                'error' => 'This link has expired or was already used.',
            ]);
        }
        
        try {
            $link = MessengerIdentityLink::create([
                'id' => Str::uuid(),
                'user_id' => $request->user()->id,
                'connector_account_id' => $payload['connector_account_id'],
                'provider_user_id' => $payload['provider_user_id'],
                'expires_at' => $this->calculateExpiry($payload['connector_account_id']),
            ]);
            
            // Notify user in messenger
            $this->notifyLinkSuccess($link);
            
            return Inertia::render('MessengerLink/Success', [
                'provider' => $link->connectorAccount->provider,
            ]);
        } catch (\Throwable $e) {
            // Token already consumed, link creation failed
            $storage->invalidateOnFailure($token);
            
            return Inertia::render('MessengerLink/Error', [
                'error' => 'Failed to complete account link. Please try again.',
            ]);
        }
    }
}
```

### A.5 Chat Session Management

Implement session lifecycle and context management.

**Files to Create:**
- `app/Support/Messenger/ChatSessionManager.php`
- `app/Support/Messenger/ChatContextBuilder.php`
- `app/Support/Messenger/ChannelRestrictionEnforcer.php`

**Session Manager:**
```php
class ChatSessionManager
{
    public function findOrCreateSession(
        ConnectorAccount $account,
        string $channelId,
        ?string $threadId,
        User $user
    ): ChatSession {
        return ChatSession::firstOrCreate(
            [
                'connector_account_id' => $account->id,
                'channel_id' => $channelId,
                'thread_id' => $threadId,
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'provider' => $account->provider,
                'status' => 'active',
            ]
        );
    }
    
    public function getSessionHistory(ChatSession $session, int $limit = 20): Collection
    {
        return $session->messages()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
```

**Channel Restriction Enforcer:**
```php
class ChannelRestrictionEnforcer
{
    public function canExecuteMutation(
        ChatSession $session,
        ConnectorAccount $account,
        ActionType $actionType
    ): ChannelPermissionResult {
        $restrictions = $account->config['channel_restrictions'] ?? [];
        
        if (empty($restrictions)) {
            return ChannelPermissionResult::allowed();
        }
        
        $isRestricted = in_array($session->channel_id, $restrictions, true);
        
        if (!$isRestricted) {
            return ChannelPermissionResult::allowed();
        }
        
        // Channel is restricted — only allow read operations
        if ($actionType->isReadOnly()) {
            return ChannelPermissionResult::allowed();
        }
        
        return ChannelPermissionResult::denied(
            "This channel is configured for read-only access. " .
            "Mutation actions like `{$actionType->value}` are not permitted here."
        );
    }
}
```

**ActionType read-only classification:**
```php
enum ActionType: string
{
    // ... existing cases ...
    
    public function isReadOnly(): bool
    {
        return in_array($this, [
            self::JOBS_LIST,
            self::RUNS_LIST_ACTIVE,
        ]);
    }
}
```

### A.6 Chat Action Schema and Orchestration

Implement structured action parsing and execution.

**Files to Create:**
- `app/Support/Messenger/Actions/ActionSchema.php`
- `app/Support/Messenger/Actions/ActionParser.php`
- `app/Support/Messenger/Actions/ActionExecutor.php`
- `app/Support/Messenger/Actions/ConfirmationManager.php`
- `app/Support/Messenger/Actions/Handlers/JobsCreateHandler.php`
- `app/Support/Messenger/Actions/Handlers/JobsUpdateHandler.php`
- `app/Support/Messenger/Actions/Handlers/JobsDeleteHandler.php`
- `app/Support/Messenger/Actions/Handlers/JobsListHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsListActiveHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsStopHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsRetryHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsRunNowHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsSteerHandler.php`
- `app/Jobs/ExecuteChatActionJob.php`
- `app/Jobs/ProcessConfirmationCallback.php`

**Action Schema:**
```php
enum ActionType: string
{
    case JOBS_CREATE = 'jobs.create';
    case JOBS_UPDATE = 'jobs.update';
    case JOBS_DELETE = 'jobs.delete';
    case JOBS_LIST = 'jobs.list';
    case RUNS_LIST_ACTIVE = 'runs.list_active';
    case RUNS_STOP = 'runs.stop';
    case RUNS_RETRY = 'runs.retry';
    case RUNS_RUN_NOW = 'runs.run_now';
    case RUNS_STEER = 'runs.steer';
    
    public function isDestructive(): bool
    {
        return in_array($this, [
            self::RUNS_STOP,
            self::JOBS_UPDATE,
            self::JOBS_DELETE,
        ]);
    }
    
    public function isMutation(): bool
    {
        return !$this->isReadOnly();
    }
    
    public function isReadOnly(): bool
    {
        return in_array($this, [
            self::JOBS_LIST,
            self::RUNS_LIST_ACTIVE,
        ]);
    }
    
    public function requiresConfirmation(array $config): bool
    {
        if (!($config['confirmation_required'] ?? false)) {
            return false;
        }
        return $this->isDestructive();
    }
}
```

**Confirmation Manager:**
```php
class ConfirmationManager
{
    public function createPendingConfirmation(
        ChatAction $action,
        ChatSession $session,
        ConnectorAccount $account
    ): PendingConfirmation {
        $token = Str::random(32);
        
        return PendingConfirmation::create([
            'id' => Str::uuid(),
            'chat_action_id' => $action->id,
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'confirmation_token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(5),
        ]);
    }
    
    public function promptForConfirmation(
        ChatAction $action,
        PendingConfirmation $confirmation,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): void {
        $response = $adapter->sendConfirmationPrompt($action, $confirmation, $account);
        
        // Store provider message ID for button callback routing
        $confirmation->update([
            'provider_message_id' => $response['message_id'] ?? null,
            'callback_data' => $response['callback_data'] ?? null,
        ]);
    }
    
    public function processConfirmationResponse(
        string $provider,
        array $callbackPayload,
        ConnectorAccount $account
    ): ?ChatAction {
        $adapter = $this->adapterManager->adapter($provider);
        $confirmationId = $adapter->extractConfirmationId($callbackPayload);
        $isConfirmed = $adapter->extractConfirmationDecision($callbackPayload);
        
        $pending = PendingConfirmation::where('connector_account_id', $account->id)
            ->where(function ($q) use ($confirmationId, $callbackPayload, $adapter) {
                // Match by provider message ID or callback data
                $q->where('provider_message_id', $adapter->extractCallbackMessageId($callbackPayload))
                  ->orWhere('callback_data->action_id', $confirmationId);
            })
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$pending) {
            return null;
        }
        
        if ($isConfirmed) {
            $pending->update(['confirmed_at' => now()]);
            $pending->chatAction->update(['confirmed_at' => now()]);
            return $pending->chatAction;
        } else {
            $pending->update(['cancelled_at' => now()]);
            $pending->chatAction->update(['status' => 'cancelled']);
            return null;
        }
    }
}
```

**ProcessConfirmationCallback Job:**
```php
class ProcessConfirmationCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public string $accountId,
        public array $callbackPayload
    ) {}
    
    public function handle(
        ConfirmationManager $confirmationManager,
        ConnectorAdapterManager $adapterManager,
        ActionExecutor $actionExecutor
    ): void {
        $account = ConnectorAccount::findOrFail($this->accountId);
        $adapter = $adapterManager->adapter($account->provider);
        
        // Process the confirmation response
        $action = $confirmationManager->processConfirmationResponse(
            $account->provider,
            $this->callbackPayload,
            $account
        );
        
        if (!$action) {
            // Confirmation was cancelled or expired — send acknowledgment
            $this->sendCancellationAck($adapter, $account);
            return;
        }
        
        // Action was confirmed — dispatch for execution
        $session = $action->chatMessage->chatSession;
        $user = $session->user;
        
        ExecuteChatActionJob::dispatch(
            $action->id,
            $user->id,
            $session->id,
            $account->id
        )->onQueue('messenger');
        
        // Send confirmation acknowledgment
        $this->sendConfirmationAck($adapter, $account, $action);
    }
    
    private function sendCancellationAck(
        ConnectorAdapterInterface $adapter,
        ConnectorAccount $account
    ): void {
        // Update the original confirmation message to show cancelled state
        $messageId = $adapter->extractCallbackMessageId($this->callbackPayload);
        if ($messageId) {
            $adapter->editMessage(
                $messageId,
                new OutboundMessage(content: '❌ Action cancelled.'),
                $account
            );
        }
    }
    
    private function sendConfirmationAck(
        ConnectorAdapterInterface $adapter,
        ConnectorAccount $account,
        ChatAction $action
    ): void {
        $messageId = $adapter->extractCallbackMessageId($this->callbackPayload);
        if ($messageId) {
            $adapter->editMessage(
                $messageId,
                new OutboundMessage(content: "✓ Confirmed. Executing {$action->action_type}..."),
                $account
            );
        }
    }
}
```

**Action Executor (sync for queries, async for mutations):**
```php
class ActionExecutor
{
    public function dispatch(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account
    ): void {
        $actionType = ActionType::from($action->action_type);
        
        // Check channel restrictions before any execution
        $permissionResult = $this->channelRestrictionEnforcer->canExecuteMutation(
            $session,
            $account,
            $actionType
        );
        
        if (!$permissionResult->allowed) {
            $this->sendDenialResponse($action, $session, $account, $permissionResult->reason);
            return;
        }
        
        if ($actionType->isReadOnly()) {
            // Sync execution for queries
            $this->executeSync($action, $user, $session, $account);
        } else {
            // Async execution for mutations
            $this->dispatchAsync($action, $user, $session, $account);
        }
    }
    
    private function executeSync(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account
    ): void {
        $adapter = $this->adapterManager->adapter($account->provider);
        
        try {
            $this->authorizeAction($action, $user);
            
            $handler = $this->resolveHandler($action->action_type);
            $result = $handler->handle($action->parameters, $user);
            
            $action->update([
                'status' => 'completed',
                'result' => $result,
                'executed_at' => now(),
            ]);
            
            $this->sendResponse($action, $result, $session, $account, $adapter);
            $this->auditAction($action, $user, 'success');
            
        } catch (\Throwable $e) {
            $action->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'executed_at' => now(),
            ]);
            
            $this->sendErrorResponse($action, $e, $session, $account, $adapter);
            $this->auditAction($action, $user, 'failure', $e->getMessage());
        }
    }
    
    private function dispatchAsync(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account
    ): void {
        $adapter = $this->adapterManager->adapter($account->provider);
        $actionType = ActionType::from($action->action_type);
        
        // Check confirmation requirement
        if ($actionType->requiresConfirmation($account->config)) {
            if (!$action->confirmed_at) {
                $action->update(['requires_confirmation' => true]);
                
                $pending = $this->confirmationManager->createPendingConfirmation(
                    $action,
                    $session,
                    $account
                );
                
                $this->confirmationManager->promptForConfirmation(
                    $action,
                    $pending,
                    $account,
                    $adapter
                );
                
                return;
            }
        }
        
        // Send immediate ack
        $this->sendAckResponse($action, $session, $account, $adapter);
        
        // Queue for async execution
        ExecuteChatActionJob::dispatch(
            $action->id,
            $user->id,
            $session->id,
            $account->id
        )->onQueue('messenger');
    }
}
```

**ExecuteChatActionJob:**
```php
class ExecuteChatActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public string $actionId,
        public int $userId,
        public string $sessionId,
        public string $accountId
    ) {}
    
    public function handle(
        ActionExecutor $executor,
        ProgressReporter $progressReporter
    ): void {
        $action = ChatAction::findOrFail($this->actionId);
        $user = User::findOrFail($this->userId);
        $session = ChatSession::findOrFail($this->sessionId);
        $account = ConnectorAccount::findOrFail($this->accountId);
        
        $adapter = app(ConnectorAdapterManager::class)->adapter($account->provider);
        
        $action->update(['status' => 'executing']);
        
        try {
            $executor->authorizeAction($action, $user);
            
            $handler = $executor->resolveHandler($action->action_type);
            $result = $handler->handle($action->parameters, $user);
            
            $action->update([
                'status' => 'completed',
                'result' => $result,
                'executed_at' => now(),
            ]);
            
            // Send completion response with threading
            $progressReporter->sendCompletionUpdate($action, $result, $session, $account, $adapter);
            
            app(AuditLogger::class)->recordMessengerAction(
                $user,
                $this->getIdentityLink($account, $session),
                $action,
                'success'
            );
            
        } catch (\Throwable $e) {
            $action->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'executed_at' => now(),
            ]);
            
            $progressReporter->sendErrorUpdate($action, $e, $session, $account, $adapter);
            
            app(AuditLogger::class)->recordMessengerAction(
                $user,
                $this->getIdentityLink($account, $session),
                $action,
                'failure',
                get_class($e),
                $e->getMessage()
            );
            
            throw $e;
        }
    }
}
```

### A.7 Inbound Message Processing

Implement webhook handlers and message processing queue.

**Files to Create:**
- `app/Http/Controllers/Api/V1/Connectors/SlackWebhookController.php`
- `app/Http/Controllers/Api/V1/Connectors/TelegramWebhookController.php`
- `app/Jobs/ProcessInboundMessengerMessage.php`
- `app/Support/Messenger/InboundMessageProcessor.php`
- `app/Support/Messenger/Threading/ThreadingStrategyResolver.php`
- `app/Support/Messenger/Threading/ThreadingStrategy.php`
- `app/Support/Messenger/Threading/ThreadedMessageSender.php`

**Webhook Controller (Slack example with multi-bot routing):**
```php
class SlackWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        AccountResolver $accountResolver,
        SignatureVerifier $signatureVerifier,
        ReplayProtection $replayProtection,
        MetricsCollector $metrics
    ): Response {
        // Resolve account using team_id for deterministic multi-bot routing
        $account = $accountResolver->resolve('slack', $request);
            
        if (!$account) {
            $metrics->incrementVerificationFailure('slack', 'account_not_found');
            return response('', 404);
        }
        
        // Verify signature against resolved account
        if (!$signatureVerifier->verify($request, $account)) {
            $metrics->incrementVerificationFailure('slack', 'signature_invalid');
            return response('', 401);
        }
        
        // Check replay
        if ($replayProtection->isReplay($request, $account)) {
            return response('', 200); // Ack but don't process
        }
        
        // Handle URL verification challenge
        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }
        
        // Handle interactive callbacks (button confirmations)
        if ($request->input('type') === 'block_actions') {
            ProcessConfirmationCallback::dispatch(
                $account->id,
                $request->all()
            )->onQueue('messenger');
            
            return response('', 200);
        }
        
        // Queue for async processing
        ProcessInboundMessengerMessage::dispatch(
            $account->id,
            $request->all()
        )->onQueue('messenger');
        
        return response('', 200);
    }
}
```

**Telegram Webhook Controller (accountKey in URL):**
```php
class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $accountKey,
        AccountResolver $accountResolver,
        ReplayProtection $replayProtection,
        MetricsCollector $metrics
    ): Response {
        // Lookup by account key from URL
        $account = ConnectorAccount::where('provider', 'telegram')
            ->where('account_key', $accountKey)
            ->where('status', 'connected')
            ->first();
            
        if (!$account) {
            $metrics->incrementVerificationFailure('telegram', 'account_not_found');
            return response('', 404);
        }
        
        // Telegram uses token-based auth (token in URL is the secret)
        // No additional signature verification needed
        
        // Check replay via update_id
        if ($replayProtection->isReplay($request, $account)) {
            return response('', 200);
        }
        
        // Handle callback queries (button confirmations)
        if ($request->has('callback_query')) {
            ProcessConfirmationCallback::dispatch(
                $account->id,
                $request->all()
            )->onQueue('messenger');
            
            return response('', 200);
        }
        
        // Queue for async processing
        ProcessInboundMessengerMessage::dispatch(
            $account->id,
            $request->all()
        )->onQueue('messenger');
        
        return response('', 200);
    }
}
```

**Webhook Routes:**
```php
// Slack: team_id in payload for routing
Route::post('/connectors/slack/webhook', SlackWebhookController::class);

// Telegram: accountKey in URL for routing
Route::post('/connectors/telegram/webhook/{accountKey}', TelegramWebhookController::class);
```

**Inbound Message Processor:**
```php
class InboundMessageProcessor
{
    public function process(ConnectorAccount $account, array $payload): void
    {
        $adapter = $this->adapterManager->adapter($account->provider);
        
        // Normalize message
        $inbound = $adapter->normalizeInboundMessage($payload, $account);
        
        // Check identity link
        $providerUserId = $adapter->extractProviderUserId($payload);
        $identityLink = MessengerIdentityLink::where('connector_account_id', $account->id)
            ->where('provider_user_id', $providerUserId)
            ->first();
            
        if (!$identityLink) {
            $this->sendOnboardingPrompt($account, $adapter, $inbound);
            return;
        }
        
        // Check link expiry and handle re-auth
        if (!$this->expiryChecker->checkAndHandleExpiry($identityLink, $account, $adapter, $inbound)) {
            return; // Re-auth prompt sent, stop processing
        }
        
        $user = $identityLink->user;
        
        // Find or create session
        $session = $this->sessionManager->findOrCreateSession(
            $account,
            $inbound->channelId,
            $inbound->threadId,
            $user
        );
        
        // Generate idempotency key
        $idempotencyKey = $adapter->generateIdempotencyKey($payload, $account);
        
        // Store message (with duplicate check via unique constraint)
        try {
            $message = ChatMessage::create([
                'id' => Str::uuid(),
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => 'inbound',
                'content' => $inbound->content,
                'idempotency_key' => $idempotencyKey,
                'provider_event_id' => $inbound->eventId,
                'provider_message_id' => $inbound->messageId,
                'provider_timestamp' => $inbound->timestamp,
            ]);
        } catch (QueryException $e) {
            // Duplicate message, skip processing
            return;
        }
        
        // Parse action intent
        $action = $this->actionParser->parse($message, $session);
        
        if ($action) {
            // Dispatch action (sync for queries, async for mutations)
            $this->actionExecutor->dispatch($action, $user, $session, $account);
        }
    }
}
```

**Threading Strategy Resolver with Fallback Chain:**
```php
class ThreadingStrategyResolver
{
    public function resolve(
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): ThreadingStrategy {
        $config = $account->config;
        $primaryMode = $config['threading_mode'] ?? 'native';
        $fallbackMode = $config['threading_fallback'] ?? 'edit';
        
        // Check if primary mode is available for this context
        $isDM = $this->isDMContext($session, $account);
        $canUseNativeThread = $adapter->supportsNativeThreads($session, $isDM);
        
        if ($primaryMode === 'native' && $canUseNativeThread) {
            return new ThreadingStrategy(
                mode: 'native',
                threadId: $session->thread_id,
                originalMessageId: null,
                fallbackChain: $this->buildFallbackChain($fallbackMode)
            );
        }
        
        // Apply fallback chain
        return match ($fallbackMode) {
            'edit' => new ThreadingStrategy(
                mode: 'edit',
                threadId: null,
                originalMessageId: $session->latestOutboundMessage()?->provider_message_id,
                fallbackChain: ['quote', 'top_level']
            ),
            'quote' => new ThreadingStrategy(
                mode: 'quote',
                threadId: null,
                originalMessageId: $session->latestInboundMessage()?->provider_message_id,
                fallbackChain: ['top_level']
            ),
            default => new ThreadingStrategy(
                mode: 'top_level',
                threadId: null,
                originalMessageId: null,
                fallbackChain: []
            ),
        };
    }
    
    private function buildFallbackChain(string $primaryFallback): array
    {
        return match ($primaryFallback) {
            'edit' => ['quote', 'top_level'],
            'quote' => ['edit', 'top_level'],
            default => ['top_level'],
        };
    }
    
    private function isDMContext(ChatSession $session, ConnectorAccount $account): bool
    {
        // Provider-specific DM detection
        return match ($account->provider) {
            'slack' => str_starts_with($session->channel_id, 'D'),
            'telegram' => $session->channel_id === $session->thread_id, // Private chat
            'discord' => $this->isDiscordDM($session),
            default => false,
        };
    }
}
```

**Threaded Message Sender with Fallback Retry:**
```php
class ThreadedMessageSender
{
    public function send(
        OutboundMessage $message,
        ThreadingStrategy $strategy,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): array {
        $modes = array_merge([$strategy->mode], $strategy->fallbackChain);
        
        foreach ($modes as $mode) {
            try {
                return $this->sendWithMode($message, $mode, $strategy, $account, $adapter);
            } catch (ThreadingUnavailableException $e) {
                // Log and try next fallback
                Log::info('Threading mode unavailable, trying fallback', [
                    'provider' => $account->provider,
                    'attempted_mode' => $mode,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
        
        // All modes failed — send as top-level as last resort
        return $adapter->sendMessage($message, $account);
    }
    
    private function sendWithMode(
        OutboundMessage $message,
        string $mode,
        ThreadingStrategy $strategy,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): array {
        return match ($mode) {
            'native' => $adapter->sendThreadReply($message, $strategy->threadId, $account),
            'edit' => $this->sendAsEdit($message, $strategy, $account, $adapter),
            'quote' => $adapter->sendQuoteReply($message, $strategy->originalMessageId, $account),
            'top_level' => $adapter->sendMessage($message, $account),
        };
    }
    
    private function sendAsEdit(
        OutboundMessage $message,
        ThreadingStrategy $strategy,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): array {
        if (!$strategy->originalMessageId) {
            throw new ThreadingUnavailableException('No original message ID for edit mode');
        }
        
        return $adapter->editMessage($strategy->originalMessageId, $message, $account);
    }
}
```

### A.8 Outbound Message Processing

Implement outbound message queue with rate limiting.

**Files to Create:**
- `app/Jobs/SendOutboundMessengerMessage.php`
- `app/Support/Messenger/OutboundMessageSender.php`
- `app/Support/Messenger/ProgressReporter.php`
- `app/Support/Messenger/RateLimiter/ConnectorRateLimiter.php`
- `app/Support/Messenger/RateLimiter/CircuitBreaker.php`
- `app/Support/Messenger/VerbosityFormatter.php`
- `app/Jobs/MoveToDeadLetter.php`
- `app/Notifications/DeadLetterNotification.php`

**Progress Reporter:**
```php
class ProgressReporter
{
    public function __construct(
        private ThreadingStrategyResolver $threadingResolver,
        private ThreadedMessageSender $messageSender,
        private VerbosityFormatter $verbosityFormatter
    ) {}
    
    public function sendAck(
        ChatAction $action,
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): void {
        $strategy = $this->threadingResolver->resolve($session, $account, $adapter);
        
        $message = new OutboundMessage(
            content: "Processing your request...",
            channelId: $session->channel_id,
        );
        
        $this->messageSender->send($message, $strategy, $account, $adapter);
    }
    
    public function sendCompletionUpdate(
        ChatAction $action,
        array $result,
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): void {
        $strategy = $this->threadingResolver->resolve($session, $account, $adapter);
        $verbosity = $this->determineVerbosity($action, $session, $account);
        
        $content = $this->verbosityFormatter->format($result, $verbosity);
        
        $message = new OutboundMessage(
            content: $content,
            channelId: $session->channel_id,
        );
        
        $this->messageSender->send($message, $strategy, $account, $adapter);
    }
    
    private function determineVerbosity(
        ChatAction $action,
        ChatSession $session,
        ConnectorAccount $account
    ): string {
        // Check for per-run override in action parameters
        if (isset($action->parameters['verbosity'])) {
            return $action->parameters['verbosity'];
        }
        
        // Fall back to account default
        return $account->config['default_verbosity'] ?? 'summary';
    }
}
```

**Verbosity Formatter:**
```php
class VerbosityFormatter
{
    public function format(array $result, string $verbosity): string
    {
        return match ($verbosity) {
            'full' => $this->formatFull($result),
            'summary' => $this->formatSummary($result),
            'errors_only' => $this->formatErrorsOnly($result),
            default => $this->formatSummary($result),
        };
    }
}
```

**Rate Limiter:**
```php
class ConnectorRateLimiter
{
    public function attempt(ConnectorAccount $account, callable $callback): mixed
    {
        $config = $account->config['rate_limit'] ?? [];
        $key = "messenger:rate_limit:{$account->id}";
        
        // Check circuit breaker
        if ($this->circuitBreaker->isOpen($account)) {
            $this->metrics->incrementCircuitBreakerReject($account->provider);
            throw new CircuitBreakerOpenException();
        }
        
        // Apply rate limit
        $limiter = RateLimiter::for($key)
            ->perSecond($config['requests_per_second'] ?? 1)
            ->maxBurst($config['burst_limit'] ?? 5);
            
        return $limiter->attempt(function () use ($callback, $account, $config) {
            try {
                $result = $callback();
                $this->circuitBreaker->recordSuccess($account);
                return $result;
            } catch (RateLimitException $e) {
                $this->metrics->incrementRateLimitEvent($account->provider);
                $this->handleRateLimit($account, $e, $config);
                throw $e;
            } catch (\Throwable $e) {
                $this->circuitBreaker->recordFailure($account);
                throw $e;
            }
        });
    }
    
    private function handleRateLimit(ConnectorAccount $account, RateLimitException $e, array $config): void
    {
        $baseDelay = $config['backoff_base_seconds'] ?? 1;
        $maxDelay = $config['backoff_max_seconds'] ?? 300;
        $jitter = ($config['jitter_percent'] ?? 20) / 100;
        
        $retryAfter = $e->retryAfter ?? $baseDelay;
        $delay = min($retryAfter, $maxDelay);
        $delay += $delay * (random_int(-100, 100) / 100) * $jitter;
        
        // Re-queue with delay
        throw new RetryWithDelayException((int) $delay);
    }
}
```

**Circuit Breaker:**
```php
class CircuitBreaker
{
    public function isOpen(ConnectorAccount $account): bool
    {
        $key = "messenger:circuit:{$account->id}";
        $state = Cache::get($key);
        
        if (!$state) {
            return false;
        }
        
        if ($state['status'] === 'open' && now()->lt($state['cooldown_until'])) {
            return true;
        }
        
        // Cooldown expired, transition to half-open
        if ($state['status'] === 'open') {
            $this->transitionToHalfOpen($account);
        }
        
        return false;
    }
    
    public function recordFailure(ConnectorAccount $account): void
    {
        $config = $account->config['rate_limit'] ?? [];
        $threshold = $config['circuit_breaker_threshold'] ?? 10;
        $cooldown = $config['circuit_breaker_cooldown_seconds'] ?? 60;
        
        $key = "messenger:circuit:{$account->id}";
        $failures = Cache::increment("{$key}:failures");
        
        if ($failures >= $threshold) {
            Cache::put($key, [
                'status' => 'open',
                'cooldown_until' => now()->addSeconds($cooldown),
                'opened_at' => now(),
            ], $cooldown + 60);
            
            $this->metrics->recordCircuitBreakerOpen($account->provider, $account->id);
        }
    }
    
    public function recordSuccess(ConnectorAccount $account): void
    {
        $key = "messenger:circuit:{$account->id}";
        $state = Cache::get($key);
        
        if ($state && $state['status'] === 'half_open') {
            // Success in half-open state closes the circuit
            Cache::forget($key);
            Cache::forget("{$key}:failures");
            $this->metrics->recordCircuitBreakerClose($account->provider, $account->id);
        }
    }
    
    private function transitionToHalfOpen(ConnectorAccount $account): void
    {
        $key = "messenger:circuit:{$account->id}";
        Cache::put($key, [
            'status' => 'half_open',
            'transitioned_at' => now(),
        ], 300);
        
        $this->metrics->recordCircuitBreakerHalfOpen($account->provider, $account->id);
    }
}
```

**Dead Letter Handler:**
```php
class SendOutboundMessengerMessage implements ShouldQueue
{
    public int $tries = 0; // Unlimited retries within duration
    public int $maxExceptions = 3;
    
    public function retryUntil(): DateTime
    {
        $account = ConnectorAccount::find($this->accountId);
        $hours = $account?->config['retry_duration_hours'] ?? 1;
        
        return now()->addHours($hours);
    }
    
    public function failed(\Throwable $exception): void
    {
        // Move to dead letter queue
        MoveToDeadLetter::dispatch(
            'outbound_message',
            [
                'account_id' => $this->accountId,
                'message_id' => $this->messageId,
                'session_id' => $this->sessionId,
                'error' => $exception->getMessage(),
                'failed_at' => now(),
            ]
        )->onQueue('messenger-dead-letter');
        
        // Notify admins
        $account = ConnectorAccount::find($this->accountId);
        if ($account) {
            Notification::route('mail', config('messenger.admin_notification_email'))
                ->notify(new DeadLetterNotification(
                    provider: $account->provider,
                    accountName: $account->name,
                    messageType: 'outbound',
                    error: $exception->getMessage()
                ));
        }
    }
}
```

### A.9 Attachment Handling (Slack + Telegram)

Implement bidirectional attachment support.

**Files to Create:**
- `app/Support/Messenger/Attachments/AttachmentDownloader.php`
- `app/Support/Messenger/Attachments/AttachmentUploader.php`
- `app/Support/Messenger/Attachments/MalwareScanner.php`
- `app/Support/Messenger/Attachments/StorageManager.php`
- `app/Support/Messenger/Attachments/QuarantineManager.php`
- `app/Jobs/ProcessInboundAttachment.php`
- `app/Jobs/CleanupExpiredAttachments.php`
- `app/Jobs/CleanupExpiredPendingConfirmations.php`
- `app/Events/AttachmentQuarantined.php`
- `app/Listeners/NotifyAttachmentQuarantine.php`

**Attachment Downloader with Quarantine:**
```php
class AttachmentDownloader
{
    public function download(
        string $url,
        ConnectorAccount $account,
        ChatMessage $message
    ): ChatAttachment {
        $config = $account->config['attachment_config'] ?? [];
        $maxSize = ($config['max_file_size_mb'] ?? 10) * 1024 * 1024;
        $allowedTypes = $config['allowed_mime_types'] ?? ['image/*', 'application/pdf', 'text/*'];
        
        // Download to temp
        $tempPath = $this->downloadToTemp($url, $account);
        
        // Capture file metadata BEFORE any potential deletion
        $size = filesize($tempPath);
        $mimeType = mime_content_type($tempPath);
        $filename = basename($url);
        
        // Validate size
        if ($size > $maxSize) {
            unlink($tempPath);
            throw new AttachmentTooLargeException();
        }
        
        // Validate MIME type
        if (!$this->isMimeTypeAllowed($mimeType, $allowedTypes)) {
            unlink($tempPath);
            throw new AttachmentTypeNotAllowedException();
        }
        
        // Scan for malware
        $scanStatus = 'skipped';
        if ($config['malware_scan_enabled'] ?? true) {
            $scanResult = $this->malwareScanner->scan($tempPath);
            $scanStatus = $scanResult->status;
            
            if ($scanStatus === 'infected') {
                // Quarantine — pass captured metadata to avoid reading after unlink
                $attachment = $this->quarantineManager->quarantine(
                    tempPath: $tempPath,
                    message: $message,
                    account: $account,
                    scanResult: $scanResult,
                    fileMetadata: [
                        'filename' => $filename,
                        'mime_type' => $mimeType,
                        'size_bytes' => $size,
                    ]
                );
                
                // Notify user
                $this->notifyUserOfInfectedAttachment($message, $account, $scanResult);
                
                // Log security event
                $this->securityLogger->logInfectedAttachment(
                    messageId: $message->id,
                    accountId: $account->id,
                    filename: $filename,
                    threatName: $scanResult->threatName,
                    scanEngine: $scanResult->engine
                );
                
                return $attachment;
            }
        }
        
        // Store clean file
        $storagePath = $this->storageManager->store($tempPath, $account);
        unlink($tempPath);
        
        // Create record
        return ChatAttachment::create([
            'id' => Str::uuid(),
            'chat_message_id' => $message->id,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'storage_path' => $storagePath,
            'scan_status' => $scanStatus,
            'expires_at' => now()->addDays($config['retention_days'] ?? 30),
        ]);
    }
    
    private function notifyUserOfInfectedAttachment(
        ChatMessage $message,
        ConnectorAccount $account,
        ScanResult $scanResult
    ): void {
        $adapter = $this->adapterManager->adapter($account->provider);
        
        $notification = new OutboundMessage(
            content: "⚠️ The attachment you sent was detected as potentially harmful and has been quarantined.\n" .
                     "Threat: {$scanResult->threatName}\n" .
                     "If you believe this is an error, please contact an administrator.",
            channelId: $message->chatSession->channel_id,
            threadId: $message->chatSession->thread_id,
        );
        
        $adapter->sendMessage($notification, $account);
    }
}
```

**Quarantine Manager:**
```php
class QuarantineManager
{
    public function quarantine(
        string $tempPath,
        ChatMessage $message,
        ConnectorAccount $account,
        ScanResult $scanResult,
        array $fileMetadata
    ): ChatAttachment {
        $quarantinePath = sprintf(
            'quarantine/%s/%s/%s',
            $account->id,
            now()->format('Y/m/d'),
            Str::uuid() . '.quarantined'
        );
        
        // Store in quarantine location (not accessible via normal retrieval)
        Storage::disk('local')->put($quarantinePath, file_get_contents($tempPath));
        unlink($tempPath);
        
        // Create record with infected status using pre-captured metadata
        $attachment = ChatAttachment::create([
            'id' => Str::uuid(),
            'chat_message_id' => $message->id,
            'filename' => $fileMetadata['filename'],
            'mime_type' => $fileMetadata['mime_type'],
            'size_bytes' => $fileMetadata['size_bytes'],
            'storage_path' => $quarantinePath,
            'scan_status' => 'infected',
            'scan_metadata' => [
                'threat_name' => $scanResult->threatName,
                'engine' => $scanResult->engine,
                'scanned_at' => now()->toIso8601String(),
            ],
            'expires_at' => now()->addDays(7), // Shorter retention for quarantine
        ]);
        
        // Dispatch event for additional handling
        event(new AttachmentQuarantined($attachment, $scanResult));
        
        return $attachment;
    }
}
```

**Storage Manager:**
```php
class StorageManager
{
    public function store(string $tempPath, ConnectorAccount $account): string
    {
        $config = $account->config['attachment_config'] ?? [];
        $disk = $config['storage_disk'] ?? 'local';
        
        $relativePath = sprintf(
            'messenger-attachments/%s/%s/%s',
            $account->id,
            now()->format('Y/m/d'),
            Str::uuid() . '.' . pathinfo($tempPath, PATHINFO_EXTENSION)
        );
        
        if ($disk === 's3') {
            Storage::disk('s3')->put($relativePath, file_get_contents($tempPath), [
                'ServerSideEncryption' => $config['s3_encryption'] ?? 'AES256',
            ]);
        } else {
            Storage::disk('local')->put($relativePath, file_get_contents($tempPath));
        }
        
        return $relativePath;
    }
    
    public function generateSignedUrl(ChatAttachment $attachment, ConnectorAccount $account): string
    {
        // Never generate URLs for quarantined files
        if ($attachment->scan_status === 'infected') {
            throw new QuarantinedAttachmentAccessException();
        }
        
        $config = $account->config['attachment_config'] ?? [];
        $disk = $config['storage_disk'] ?? 'local';
        $ttl = $config['signed_url_ttl_minutes'] ?? 15;
        
        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $attachment->storage_path,
                now()->addMinutes($ttl)
            );
        }
        
        // For local storage, generate signed URL through our endpoint
        return URL::signedRoute(
            'api.v1.chat.attachments.download',
            ['id' => $attachment->id],
            now()->addMinutes($ttl)
        );
    }
}
```

**CleanupExpiredAttachments Job:**
```php
class CleanupExpiredAttachments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle(): void
    {
        $expiredAttachments = ChatAttachment::where('expires_at', '<', now())
            ->cursor();
            
        foreach ($expiredAttachments as $attachment) {
            try {
                // Delete file from storage
                $disk = $attachment->scan_status === 'infected' ? 'local' : $this->resolveDisk($attachment);
                Storage::disk($disk)->delete($attachment->storage_path);
                
                // Delete record
                $attachment->delete();
                
                Log::info('Expired attachment cleaned up', [
                    'attachment_id' => $attachment->id,
                    'scan_status' => $attachment->scan_status,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to cleanup expired attachment', [
                    'attachment_id' => $attachment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

**CleanupExpiredPendingConfirmations Job:**
```php
class CleanupExpiredPendingConfirmations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle(): void
    {
        // First, update chat_actions to timeout status for expired pending confirmations
        // Do this BEFORE deleting the pending_confirmations to maintain referential integrity
        ChatAction::whereIn('id', function ($query) {
            $query->select('chat_action_id')
                ->from('pending_confirmations')
                ->where('expires_at', '<', now())
                ->whereNull('confirmed_at')
                ->whereNull('cancelled_at');
        })
        ->where('status', 'pending')
        ->update(['status' => 'timeout']);
        
        // Then delete expired pending confirmations that were never confirmed or cancelled
        $deleted = PendingConfirmation::where('expires_at', '<', now())
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->delete();
            
        if ($deleted > 0) {
            Log::info('Cleaned up expired pending confirmations', [
                'count' => $deleted,
            ]);
        }
    }
}
```

### A.10 Idempotency Key Generation

Implement collision-resistant idempotency key generation.

**Files to Create:**
- `app/Support/Messenger/IdempotencyKeyGenerator.php`

```php
class IdempotencyKeyGenerator
{
    public function generate(array $payload, ConnectorAccount $account): string
    {
        $provider = $account->provider;
        
        // Use provider message ID if available
        $messageId = $this->extractMessageId($payload, $provider);
        if ($messageId) {
            return hash('sha256', implode(':', [
                $provider,
                $account->id,
                $messageId,
            ]));
        }
        
        // Fallback: content-based key
        $channelId = $this->extractChannelId($payload, $provider);
        $senderId = $this->extractSenderId($payload, $provider);
        $timestamp = $this->extractTimestampMs($payload, $provider);
        $content = $this->extractContent($payload, $provider);
        $attachmentIds = $this->extractAttachmentIds($payload, $provider);
        
        $contentHash = hash('sha256', $content . json_encode($attachmentIds));
        
        return hash('sha256', implode(':', [
            $provider,
            $account->id,
            $channelId,
            $senderId,
            $timestamp,
            $contentHash,
        ]));
    }
}
```

### A.11 API Endpoints

Implement Chat API and Connector Management API.

**Files to Create:**
- `app/Http/Controllers/Api/V1/ChatSessionController.php`
- `app/Http/Controllers/Api/V1/ChatAttachmentController.php`
- `app/Http/Controllers/Api/V1/ConnectorController.php`

**API Routes (routes/api.php additions):**
```php
Route::middleware('auth:sanctum')->group(function (): void {
    // Chat API
    Route::get('/chat/sessions', [ChatSessionController::class, 'index']);
    Route::get('/chat/sessions/{id}/messages', [ChatSessionController::class, 'messages']);
    Route::get('/chat/actions/{id}', [ChatSessionController::class, 'actionStatus']);
    Route::get('/chat/runs/{id}/stream', [ChatSessionController::class, 'runStream']);
    
    // Attachment API
    Route::get('/chat/attachments/{id}', [ChatAttachmentController::class, 'show'])
        ->name('api.v1.chat.attachments.download');
    
    // Connector Management
    Route::get('/connectors', [ConnectorController::class, 'index']);
    Route::post('/connectors', [ConnectorController::class, 'store'])
        ->middleware('throttle:agent-mutations');
    Route::delete('/connectors/{id}', [ConnectorController::class, 'destroy'])
        ->middleware('throttle:agent-mutations');
    Route::post('/connectors/{id}/test', [ConnectorController::class, 'test'])
        ->middleware('throttle:agent-mutations');
});

// Webhook routes (no auth, signature verified)
Route::post('/connectors/slack/webhook', SlackWebhookController::class);
Route::post('/connectors/telegram/webhook/{accountKey}', TelegramWebhookController::class);
```

### A.12 CLI Commands

Implement `agent:install` and `agent:restart` commands.

**Files to Create:**
- `app/Console/Commands/AgentInstallCommand.php`
- `app/Console/Commands/AgentRestartCommand.php`
- `app/Support/Messenger/Installer/PreflightChecker.php`
- `app/Support/Messenger/Installer/ConnectorConfigurator.php`

**Agent Install Command:**
```php
class AgentInstallCommand extends Command
{
    protected $signature = 'agent:install
        {--connector=* : Providers to configure (slack,telegram,discord,whatsapp)}
        {--mode=local : Connection mode (local or webhook)}
        {--non-interactive : Fail on missing required values}
        {--config= : Path to config YAML file}';
        
    protected $description = 'Bootstrap Agent with messenger connector configuration';
    
    public function handle(
        PreflightChecker $preflightChecker,
        ConnectorConfigurator $configurator
    ): int {
        $this->info('Starting Agent installation...');
        
        // Run preflight checks
        $this->info('Running preflight checks...');
        $preflightResult = $preflightChecker->run();
        
        if (!$preflightResult['ok']) {
            $this->error('Preflight checks failed:');
            foreach ($preflightResult['errors'] as $error) {
                $this->error("  - {$error}");
            }
            return self::FAILURE;
        }
        
        $this->info('Preflight checks passed.');
        
        // Determine connectors to configure
        $connectors = $this->option('connector') ?: [];
        if (empty($connectors) && !$this->option('non-interactive')) {
            $connectors = $this->choice(
                'Which connectors would you like to configure?',
                ['slack', 'telegram', 'none'],
                null,
                null,
                true
            );
        }
        
        $mode = $this->option('mode');
        
        // Configure each connector
        foreach ($connectors as $connector) {
            if ($connector === 'none') continue;
            
            // Enforce WhatsApp webhook-only constraint
            if ($connector === 'whatsapp' && $mode === 'local') {
                $this->warn("WhatsApp Cloud API requires webhook mode. Switching to webhook mode for WhatsApp.");
                $connectorMode = 'webhook';
            } else {
                $connectorMode = $mode;
            }
            
            $this->info("Configuring {$connector} in {$connectorMode} mode...");
            $result = $configurator->configure($connector, $connectorMode, $this);
            
            if (!$result['ok']) {
                $this->error("Failed to configure {$connector}: {$result['error']}");
                return self::FAILURE;
            }
            
            $this->info("{$connector} configured successfully.");
        }
        
        // Run health checks
        $this->info('Running health checks...');
        $this->call('agent:health');
        
        $this->info('Agent installation complete.');
        return self::SUCCESS;
    }
}
```

**Agent Restart Command:**
```php
class AgentRestartCommand extends Command
{
    protected $signature = 'agent:restart
        {--force : Force restart without graceful shutdown}';
        
    protected $description = 'Gracefully restart Agent runtime services';
    
    public function handle(): int
    {
        $services = [
            'horizon' => 'php artisan horizon',
            'reverb' => 'php artisan reverb:start',
            'scheduler' => 'php artisan schedule:work',
        ];
        
        $config = config('messenger.restart', []);
        
        if ($config['include_web_server'] ?? false) {
            $services['serve'] = 'php artisan serve';
        }
        
        if ($config['include_npm_dev'] ?? false) {
            $services['npm'] = 'npm run dev';
        }
        
        foreach ($services as $name => $command) {
            $this->info("Restarting {$name}...");
            
            // Terminate existing process
            $this->terminateService($name);
            
            // Start new process
            $result = $this->startService($name, $command);
            
            if ($result) {
                $this->info("  ✓ {$name} restarted");
            } else {
                $this->error("  ✗ {$name} failed to restart");
            }
        }
        
        return self::SUCCESS;
    }
}
```

### A.13 Configuration

Add messenger configuration.

**Files to Create:**
- `config/messenger.php`

```php
// config/messenger.php
return [
    'connectors' => [
        'slack' => [
            'default_mode' => env('MESSENGER_SLACK_MODE', 'local'),
            'socket_mode_app_token' => env('SLACK_APP_TOKEN'),
            'bot_token' => env('SLACK_BOT_TOKEN'),
            'signing_secret' => env('SLACK_SIGNING_SECRET'),
            'signature_verification' => [
                'scheme' => 'hmac_sha256',
                'signing_secret' => env('SLACK_SIGNING_SECRET'),
            ],
            'replay_protection' => [
                'strategy' => 'timestamp',
                'window_seconds' => 300,
            ],
        ],
        'telegram' => [
            'default_mode' => env('MESSENGER_TELEGRAM_MODE', 'local'),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'signature_verification' => [
                'scheme' => 'token',
            ],
            'replay_protection' => [
                'strategy' => 'event_id_dedupe',
                'dedupe_ttl_seconds' => 3600,
            ],
        ],
        'discord' => [
            'default_mode' => env('MESSENGER_DISCORD_MODE', 'local'),
            'application_id' => env('DISCORD_APPLICATION_ID'),
            'public_key' => env('DISCORD_PUBLIC_KEY'),
            'bot_token' => env('DISCORD_BOT_TOKEN'),
            'signature_verification' => [
                'scheme' => 'ed25519',
                'public_key' => env('DISCORD_PUBLIC_KEY'),
            ],
            'replay_protection' => [
                'strategy' => 'timestamp',
                'window_seconds' => 300,
            ],
        ],
        'whatsapp' => [
            'default_mode' => 'webhook', // WhatsApp is webhook-only
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'app_secret' => env('WHATSAPP_APP_SECRET'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
            'signature_verification' => [
                'scheme' => 'hmac_sha256',
                'signing_secret' => env('WHATSAPP_APP_SECRET'),
            ],
            'replay_protection' => [
                'strategy' => 'event_id_dedupe',
                'dedupe_ttl_seconds' => 3600,
            ],
        ],
    ],
    
    'defaults' => [
        'confirmation_required' => env('MESSENGER_CONFIRMATION_REQUIRED', true),
        'session_history_limit' => env('MESSENGER_SESSION_HISTORY_LIMIT', 20),
        'default_verbosity' => env('MESSENGER_DEFAULT_VERBOSITY', 'summary'),
        'retry_duration_hours' => env('MESSENGER_RETRY_DURATION_HOURS', 1),
    ],
    
    'attachment_config' => [
        'max_file_size_mb' => env('MESSENGER_MAX_ATTACHMENT_SIZE_MB', 10),
        'allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
        'malware_scan_enabled' => env('MESSENGER_MALWARE_SCAN_ENABLED', true),
        'retention_days' => env('MESSENGER_ATTACHMENT_RETENTION_DAYS', 30),
        'quarantine_retention_days' => env('MESSENGER_QUARANTINE_RETENTION_DAYS', 7),
        'storage_disk' => env('MESSENGER_ATTACHMENT_DISK', 'local'),
        's3_encryption' => env('MESSENGER_S3_ENCRYPTION', 'AES256'),
        'signed_url_ttl_minutes' => 15,
    ],
    
    'rate_limit' => [
        'requests_per_second' => env('MESSENGER_RATE_LIMIT_RPS', 1),
        'burst_limit' => env('MESSENGER_RATE_LIMIT_BURST', 5),
        'backoff_base_seconds' => 1,
        'backoff_max_seconds' => 300,
        'jitter_percent' => 20,
        'circuit_breaker_threshold' => 10,
        'circuit_breaker_cooldown_seconds' => 60,
    ],
    
    'restart' => [
        'include_web_server' => env('MESSENGER_RESTART_WEB_SERVER', false),
        'include_npm_dev' => env('MESSENGER_RESTART_NPM_DEV', false),
    ],
    
    'admin_notification_email' => env('MESSENGER_ADMIN_EMAIL'),
];
```

### A.14 Horizon Queue Configuration

Add messenger queue to Horizon.

**Files to Modify:**
- `config/horizon.php` (add messenger queue)

```php
// Add to defaults
'supervisor-messenger' => [
    'connection' => 'redis',
    'queue' => ['messenger', 'messenger-dead-letter'],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'maxProcesses' => max(1, min(4, (int) env('HORIZON_MESSENGER_MAX_PROCESSES', 2))),
    'maxTime' => 0,
    'maxJobs' => 0,
    'memory' => 128,
    'tries' => 3,
    'backoff' => [10, 60, 300],
    'timeout' => 120,
    'nice' => 0,
],
```

**Add waits configuration:**
```php
'waits' => [
    'redis:agent' => 60,
    'redis:interrogation' => 30,
    'redis:messenger' => 30,
],
```

### A.15 Audit Integration

Extend existing AuditLogger for messenger actions.

**Files to Modify:**
- `app/Support/Agent/AuditLogger.php` (add recordMessengerAction method)

```php
public function recordMessengerAction(
    User $user,
    MessengerIdentityLink $identityLink,
    ChatAction $action,
    string $outcome = 'success',
    ?string $errorCode = null,
    ?string $errorMessage = null,
): AgentAuditLog {
    return $this->record(
        userId: $user->id,
        actorType: 'messenger',
        actorId: $user->id,
        action: "chat.{$action->action_type}",
        targetType: $this->resolveTargetType($action->action_type),
        targetId: $action->parameters['job_id'] ?? $action->parameters['run_id'] ?? '',
        changedFields: [],
        before: null,
        after: $action->result,
        requestId: $action->id,
        ipAddress: null,
        userAgent: "messenger:{$identityLink->connectorAccount->provider}",
        hostname: gethostname() ?: null,
        outcome: $outcome,
        errorCode: $errorCode,
        errorMessage: $errorMessage,
        metadata: [
            'connector_account_id' => $identityLink->connector_account_id,
            'provider_user_id' => $identityLink->provider_user_id,
            'chat_session_id' => $action->chatMessage->chat_session_id,
            'chat_message_id' => $action->chat_message_id,
            'channel_id' => $action->chatMessage->chatSession->channel_id,
            'thread_id' => $action->chatMessage->chatSession->thread_id,
        ],
    );
}

private function resolveTargetType(string $actionType): string
{
    return str_starts_with($actionType, 'jobs.') ? 'agent_job' : 'agent_job_run';
}
```

### A.16 Observability

Implement metrics collection for required observability coverage.

**Files to Create:**
- `app/Support/Messenger/Metrics/MetricsCollector.php`
- `app/Support/Messenger/Metrics/SecurityLogger.php`
- `app/Support/Messenger/Metrics/QueueDepthMonitor.php`

**Metrics Collector:**
```php
class MetricsCollector
{
    public function incrementVerificationFailure(string $provider, string $reason): void
    {
        $this->increment("messenger.webhook.verification_failure", [
            'provider' => $provider,
            'reason' => $reason,
        ]);
    }
    
    public function incrementRateLimitEvent(string $provider): void
    {
        $this->increment("messenger.rate_limit.triggered", [
            'provider' => $provider,
        ]);
    }
    
    public function recordBackoffEvent(string $provider, int $delaySeconds): void
    {
        $this->histogram("messenger.rate_limit.backoff_seconds", $delaySeconds, [
            'provider' => $provider,
        ]);
    }
    
    public function recordCircuitBreakerOpen(string $provider, string $accountId): void
    {
        $this->increment("messenger.circuit_breaker.opened", [
            'provider' => $provider,
            'account_id' => $accountId,
        ]);
        
        Log::warning('Circuit breaker opened', [
            'provider' => $provider,
            'account_id' => $accountId,
        ]);
    }
    
    public function recordCircuitBreakerClose(string $provider, string $accountId): void
    {
        $this->increment("messenger.circuit_breaker.closed", [
            'provider' => $provider,
            'account_id' => $accountId,
        ]);
        
        Log::info('Circuit breaker closed', [
            'provider' => $provider,
            'account_id' => $accountId,
        ]);
    }
    
    public function recordCircuitBreakerHalfOpen(string $provider, string $accountId): void
    {
        $this->increment("messenger.circuit_breaker.half_open", [
            'provider' => $provider,
            'account_id' => $accountId,
        ]);
    }
    
    public function incrementCircuitBreakerReject(string $provider): void
    {
        $this->increment("messenger.circuit_breaker.rejected", [
            'provider' => $provider,
        ]);
    }
    
    public function recordAttachmentScanResult(string $provider, string $status): void
    {
        $this->increment("messenger.attachment.scan_result", [
            'provider' => $provider,
            'status' => $status, // clean, infected, skipped
        ]);
    }
    
    public function recordInboundMessage(string $provider): void
    {
        $this->increment("messenger.inbound.message", [
            'provider' => $provider,
        ]);
    }
    
    public function recordActionExecution(string $provider, string $actionType, string $status): void
    {
        $this->increment("messenger.action.executed", [
            'provider' => $provider,
            'action_type' => $actionType,
            'status' => $status, // success, failure
        ]);
    }
    
    public function recordActionLatency(string $provider, string $actionType, float $durationMs): void
    {
        $this->histogram("messenger.action.latency_ms", $durationMs, [
            'provider' => $provider,
            'action_type' => $actionType,
        ]);
    }
    
    public function recordDeadLetter(string $provider, string $messageType): void
    {
        $this->increment("messenger.dead_letter.added", [
            'provider' => $provider,
            'message_type' => $messageType,
        ]);
    }
}
```

**Queue Depth Monitor:**
```php
class QueueDepthMonitor
{
    public function __construct(
        private MetricsCollector $metrics
    ) {}
    
    public function recordQueueDepths(): void
    {
        $queues = ['messenger', 'messenger-dead-letter'];
        
        foreach ($queues as $queue) {
            $size = $this->getQueueSize($queue);
            $this->metrics->gauge("messenger.queue.depth", $size, [
                'queue' => $queue,
            ]);
        }
    }
    
    private function getQueueSize(string $queue): int
    {
        $connection = config('queue.default');
        
        if ($connection === 'redis') {
            $prefix = config('database.redis.options.prefix', '');
            $key = $prefix . 'queues:' . $queue;
            return (int) Redis::connection()->llen($key);
        }
        
        // Fallback: use Horizon's metrics repository via explicit resolution
        $metricsRepository = app(\Laravel\Horizon\Contracts\MetricsRepository::class);
        $pendingJobs = $metricsRepository->queueWithPendingJobs();
        
        return (int) ($pendingJobs[$queue] ?? 0);
    }
}
```

**Scheduled Queue Depth Recording (add to routes/console.php):**
```php
Schedule::call(function () {
    app(QueueDepthMonitor::class)->recordQueueDepths();
})->everyMinute()->name('messenger:record-queue-depths');
```

**Security Logger:**
```php
class SecurityLogger
{
    public function logInfectedAttachment(
        string $messageId,
        string $accountId,
        string $filename,
        string $threatName,
        string $scanEngine
    ): void {
        Log::channel('security')->warning('Infected attachment quarantined', [
            'event_type' => 'attachment.infected',
            'message_id' => $messageId,
            'account_id' => $accountId,
            'filename' => $filename,
            'threat_name' => $threatName,
            'scan_engine' => $scanEngine,
            'timestamp' => now()->toIso8601String(),
        ]);
        
        $this->metrics->recordAttachmentScanResult(
            $this->getProviderFromAccount($accountId),
            'infected'
        );
    }
    
    public function logSignatureVerificationFailure(
        string $provider,
        string $reason,
        ?string $ipAddress = null
    ): void {
        Log::channel('security')->warning('Webhook signature verification failed', [
            'event_type' => 'webhook.signature_failure',
            'provider' => $provider,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

### A.17 Scheduled Cleanup Jobs

Register cleanup jobs in the scheduler.

**Files to Modify:**
- `routes/console.php` (add cleanup schedules)

```php
// Cleanup expired attachments daily
Schedule::job(new CleanupExpiredAttachments)
    ->dailyAt('04:00')
    ->name('messenger:cleanup-attachments');

// Cleanup expired account link tokens hourly
Schedule::job(new CleanupExpiredAccountLinkTokens)
    ->hourly()
    ->name('messenger:cleanup-account-link-tokens');

// Cleanup expired pending confirmations hourly
Schedule::job(new CleanupExpiredPendingConfirmations)
    ->hourly()
    ->name('messenger:cleanup-pending-confirmations');

// Record queue depth metrics every minute
Schedule::call(function () {
    app(QueueDepthMonitor::class)->recordQueueDepths();
})->everyMinute()->name('messenger:record-queue-depths');
```

### A.18 Testing

Create test suites for messenger functionality.

**Files to Create:**
- `tests/Feature/Messenger/SlackWebhookTest.php`
- `tests/Feature/Messenger/TelegramWebhookTest.php`
- `tests/Feature/Messenger/AccountLinkTest.php`
- `tests/Feature/Messenger/ChatActionTest.php`
- `tests/Feature/Messenger/AttachmentTest.php`
- `tests/Feature/Messenger/ConfirmationWorkflowTest.php`
- `tests/Feature/Messenger/ChannelRestrictionTest.php`
- `tests/Feature/Messenger/IdentityLinkExpiryTest.php`
- `tests/Feature/Messenger/MultiBotRoutingTest.php`
- `tests/Feature/Messenger/ThreadingFallbackTest.php`
- `tests/Unit/Messenger/SignatureVerifierTest.php`
- `tests/Unit/Messenger/ReplayProtectionTest.php`
- `tests/Unit/Messenger/IdempotencyKeyGeneratorTest.php`
- `tests/Unit/Messenger/RateLimiterTest.php`
- `tests/Unit/Messenger/CircuitBreakerTest.php`
- `tests/Unit/Messenger/ThreadingStrategyTest.php`
- `tests/Unit/Messenger/VerbosityFormatterTest.php`
- `tests/Unit/Messenger/AccountResolverTest.php`
- `tests/Unit/Messenger/QuarantineManagerTest.php`
- `tests/Unit/Messenger/ProcessConfirmationCallbackTest.php`

---

## Phase B: Connector Expansion (Post-MVP)

### B.1 Discord Adapter

**Files to Create:**
- `app/Support/Messenger/Adapters/DiscordAdapter.php`
- `app/Http/Controllers/Api/V1/Connectors/DiscordWebhookController.php`
- `tests/Feature/Messenger/DiscordWebhookTest.php`

**Key Implementation:**
- Gateway WebSocket for local mode
- Ed25519 signature verification
- Timestamp-based replay protection (300s window)
- Native threads in channels, edit fallback in DMs

### B.2 WhatsApp Adapter

**Files to Create:**
- `app/Support/Messenger/Adapters/WhatsAppAdapter.php`
- `app/Http/Controllers/Api/V1/Connectors/WhatsAppWebhookController.php`
- `tests/Feature/Messenger/WhatsAppWebhookTest.php`

**Key Implementation:**
- Webhook-only mode (no local connector)
- HMAC-SHA256 signature verification
- Event ID deduplication via `messages[].id`
- Quote reply threading

**agent:install enforcement for WhatsApp:**
The `AgentInstallCommand` already enforces webhook-only mode for WhatsApp (see A.12). When `--connector=whatsapp` is specified with `--mode=local`, the command automatically switches to webhook mode and warns the user.

### B.3 Discord and WhatsApp Attachments

Extend attachment handling to support Discord and WhatsApp file formats and APIs.

---

## Phase C: Enhanced Steering (Future)

### C.1 Advanced Steering Protocol

Design and implement richer run steering semantics beyond stop+restart:
- Live context injection
- Priority queue manipulation
- Branching and fork support

---

## Dependency Order

1. **A.1** Database Migrations and Models (foundation)
2. **A.2** Connector Adapter Architecture (depends on A.1)
3. **A.3** Webhook Security Infrastructure (depends on A.2)
4. **A.4** Account-Link Flow (depends on A.1, A.2)
5. **A.5** Chat Session Management (depends on A.1, A.4)
6. **A.10** Idempotency Key Generation (depends on A.2)
7. **A.6** Chat Action Schema and Orchestration (depends on A.5)
8. **A.7** Inbound Message Processing (depends on A.2, A.3, A.5, A.6, A.10)
9. **A.8** Outbound Message Processing (depends on A.2, A.7)
10. **A.9** Attachment Handling (depends on A.1, A.7)
11. **A.11** API Endpoints (depends on A.5, A.6, A.9)
12. **A.12** CLI Commands (depends on A.2, A.13)
13. **A.13** Configuration (independent, can be early)
14. **A.14** Horizon Queue Configuration (depends on A.13)
15. **A.15** Audit Integration (depends on A.6)
16. **A.16** Observability (depends on A.8, A.9)
17. **A.17** Scheduled Cleanup Jobs (depends on A.1, A.4, A.6, A.9)
18. **A.18** Testing (depends on all above)
19. **B.1** Discord Adapter (depends on Phase A completion)
20. **B.2** WhatsApp Adapter (depends on Phase A completion)
21. **B.3** Discord and WhatsApp Attachments (depends on B.1, B.2)

## Sections

- Phase A: Foundation and Core Infrastructure
- A.1 Database Migrations and Models
- A.2 Connector Adapter Architecture
- A.3 Webhook Security Infrastructure
- A.4 Account-Link Flow
- A.5 Chat Session Management
- A.6 Chat Action Schema and Orchestration
- A.7 Inbound Message Processing
- A.8 Outbound Message Processing
- A.9 Attachment Handling (Slack + Telegram)
- A.10 Idempotency Key Generation
- A.11 API Endpoints
- A.12 CLI Commands
- A.13 Configuration
- A.14 Horizon Queue Configuration
- A.15 Audit Integration
- A.16 Observability
- A.17 Scheduled Cleanup Jobs
- A.18 Testing
- Phase B: Connector Expansion (Post-MVP)
- B.1 Discord Adapter
- B.2 WhatsApp Adapter
- B.3 Discord and WhatsApp Attachments
- Phase C: Enhanced Steering (Future)
- Dependency Order


## Risks

- Redis availability dependency for account-link tokens may cause degraded UX if Redis is unavailable and DB fallback activates, introducing latency
- Ed25519 signature verification for Discord requires sodium PHP extension which may not be installed in all environments
- Malware scanning with ClamAV introduces external service dependency and potential processing bottleneck for attachment-heavy workloads
- Long-polling and Socket Mode connections for local connector mode require persistent processes that may conflict with process managers or container orchestration
- WhatsApp Business Platform has strict approval requirements and rate limits that may delay or restrict production deployment
- Provider API changes (especially Slack and Discord platform updates) may require adapter modifications without warning
- Multi-bot support increases credential management complexity and potential for misconfiguration
- Circuit breaker patterns may cause message delivery delays during transient provider issues
- Attachment storage costs may scale unexpectedly with high-volume deployments using S3
- Session history retention policies may not comply with data retention regulations in all jurisdictions
- Confirmation workflow expiry (5 minutes) may be too short for users who step away, causing action abandonment
- Account key extraction failures could cause requests to fall back to expensive signature iteration across all accounts
- Quarantine storage for infected attachments requires separate retention policy and cleanup job to prevent disk exhaustion
- Threading fallback chain may result in inconsistent user experience when primary threading modes fail silently
- Queue depth monitoring relies on Redis LLEN which may not reflect true depth if using other queue backends


## Assumptions

- Redis is available as the primary cache store for token storage and rate limiting
- ClamAV or equivalent malware scanner is available and configured when malware scanning is enabled
- PHP sodium extension is available for Ed25519 signature verification (Discord)
- Existing Horizon supervisor infrastructure is sufficient to handle additional messenger queue
- Provider webhooks can reach the Agent instance (network/firewall configured appropriately for webhook mode)
- Users have appropriate provider credentials (bot tokens, signing secrets) available during installation
- Existing CommandPolicy, PathPolicy, and EnvPolicy implementations are sufficient for chat action validation
- The existing audit logging infrastructure can be extended without schema changes
- Laravel's rate limiter facade provides sufficient granularity for per-connector rate limiting
- File storage (local or S3) is configured with appropriate permissions and encryption settings
- Slack team_id and Telegram accountKey in URL provide reliable deterministic routing for multi-bot scenarios
- Provider interactive button/callback APIs are stable and support the confirmation workflow requirements
- Admin notification email is configured for dead-letter queue alerts
- Scheduled task runner (schedule:work) is operational for cleanup jobs and queue depth monitoring

