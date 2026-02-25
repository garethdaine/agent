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
- `app/Models/ConnectorAccount.php`
- `app/Models/ChatSession.php`
- `app/Models/ChatMessage.php`
- `app/Models/ChatAction.php`
- `app/Models/MessengerIdentityLink.php`
- `app/Models/ChatAttachment.php`
- `app/Models/AccountLinkToken.php`

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
    $table->timestamps();
    
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
}
```

**Slack Adapter Implementation:**
- Socket Mode support for local connector mode (default)
- HMAC-SHA256 signature verification using `X-Slack-Request-Timestamp`
- Timestamp-based replay protection (300-second window configurable)
- Native thread support via `thread_ts`
- Message editing fallback via `chat.update`

**Telegram Adapter Implementation:**
- Long polling for local connector mode (default)
- Token-based authentication (bot token in webhook URL)
- Event ID deduplication via `update_id`
- Reply-to threading via `reply_to_message_id`
- Quote reply fallback

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
- `app/Http/Controllers/MessengerLinkController.php`
- `resources/js/Pages/MessengerLink/Confirm.vue`
- `resources/js/Pages/MessengerLink/Success.vue`
- `resources/js/Pages/MessengerLink/Error.vue`

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
        
        // Try Redis first
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
            return null;
        }
        
        $record = AccountLinkToken::where('token_hash', $hash)->first();
        return [
            'connector_account_id' => $record->connector_account_id,
            'provider_user_id' => $record->provider_user_id,
        ];
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

### A.5 Chat Session Management

Implement session lifecycle and context management.

**Files to Create:**
- `app/Support/Messenger/ChatSessionManager.php`
- `app/Support/Messenger/ChatContextBuilder.php`

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

### A.6 Chat Action Schema and Orchestration

Implement structured action parsing and execution.

**Files to Create:**
- `app/Support/Messenger/Actions/ActionSchema.php`
- `app/Support/Messenger/Actions/ActionParser.php`
- `app/Support/Messenger/Actions/ActionExecutor.php`
- `app/Support/Messenger/Actions/Handlers/JobsCreateHandler.php`
- `app/Support/Messenger/Actions/Handlers/JobsUpdateHandler.php`
- `app/Support/Messenger/Actions/Handlers/JobsDeleteHandler.php`
- `app/Support/Messenger/Actions/Handlers/JobsListHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsListActiveHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsStopHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsRetryHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsRunNowHandler.php`
- `app/Support/Messenger/Actions/Handlers/RunsSteerHandler.php`

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
    
    public function requiresConfirmation(array $config): bool
    {
        if (!($config['confirmation_required'] ?? false)) {
            return false;
        }
        return $this->isDestructive();
    }
}
```

**Action Executor:**
```php
class ActionExecutor
{
    public function execute(ChatAction $action, User $user): array
    {
        // Validate authorization
        $this->authorizeAction($action, $user);
        
        // Check confirmation for destructive actions
        if ($action->requires_confirmation && !$action->confirmed_at) {
            return ['status' => 'awaiting_confirmation'];
        }
        
        // Route to handler
        $handler = $this->resolveHandler($action->action_type);
        
        $action->update(['status' => 'executing']);
        
        try {
            $result = $handler->handle($action->parameters, $user);
            $action->update([
                'status' => 'completed',
                'result' => $result,
                'executed_at' => now(),
            ]);
            
            $this->auditAction($action, $user, 'success');
            
            return $result;
        } catch (\Throwable $e) {
            $action->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'executed_at' => now(),
            ]);
            
            $this->auditAction($action, $user, 'failure', $e->getMessage());
            
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

**Webhook Controller (Slack example):**
```php
class SlackWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ConnectorAdapterManager $adapterManager,
        SignatureVerifier $signatureVerifier,
        ReplayProtection $replayProtection
    ): Response {
        $account = ConnectorAccount::where('provider', 'slack')
            ->where('status', 'connected')
            ->first();
            
        if (!$account) {
            return response('', 404);
        }
        
        $adapter = $adapterManager->adapter('slack');
        
        // Verify signature
        if (!$signatureVerifier->verify($request, $account)) {
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
        
        // Queue for async processing
        ProcessInboundMessengerMessage::dispatch(
            $account->id,
            $request->all()
        )->onQueue('messenger');
        
        return response('', 200);
    }
}
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
            // Execute action
            $this->executeAction($action, $user, $session, $account, $adapter);
        }
    }
}
```

### A.8 Outbound Message Processing

Implement outbound message queue with rate limiting.

**Files to Create:**
- `app/Jobs/SendOutboundMessengerMessage.php`
- `app/Support/Messenger/OutboundMessageSender.php`
- `app/Support/Messenger/RateLimiter/ConnectorRateLimiter.php`
- `app/Support/Messenger/RateLimiter/CircuitBreaker.php`

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
            ], $cooldown);
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
- `app/Jobs/ProcessInboundAttachment.php`
- `app/Jobs/CleanupExpiredAttachments.php`

**Attachment Downloader:**
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
        
        // Validate size
        $size = filesize($tempPath);
        if ($size > $maxSize) {
            unlink($tempPath);
            throw new AttachmentTooLargeException();
        }
        
        // Validate MIME type
        $mimeType = mime_content_type($tempPath);
        if (!$this->isMimeTypeAllowed($mimeType, $allowedTypes)) {
            unlink($tempPath);
            throw new AttachmentTypeNotAllowedException();
        }
        
        // Scan for malware
        $scanStatus = 'skipped';
        if ($config['malware_scan_enabled'] ?? true) {
            $scanStatus = $this->malwareScanner->scan($tempPath);
            if ($scanStatus === 'infected') {
                unlink($tempPath);
                throw new AttachmentInfectedException();
            }
        }
        
        // Store
        $storagePath = $this->storageManager->store($tempPath, $account);
        unlink($tempPath);
        
        // Create record
        return ChatAttachment::create([
            'id' => Str::uuid(),
            'chat_message_id' => $message->id,
            'filename' => basename($url),
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'storage_path' => $storagePath,
            'scan_status' => $scanStatus,
            'expires_at' => now()->addDays($config['retention_days'] ?? 30),
        ]);
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
Route::post('/connectors/telegram/webhook', TelegramWebhookController::class);
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
        {--connector=* : Providers to configure (slack,telegram)}
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
        
        // Configure each connector
        foreach ($connectors as $connector) {
            if ($connector === 'none') continue;
            
            $this->info("Configuring {$connector}...");
            $result = $configurator->configure($connector, $this);
            
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
        
        $config = config('agent.restart', []);
        
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

**Files to Modify:**
- `config/agent.php` (add messenger section)

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
        ],
        'telegram' => [
            'default_mode' => env('MESSENGER_TELEGRAM_MODE', 'local'),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
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
        'include_web_server' => env('AGENT_RESTART_WEB_SERVER', false),
        'include_npm_dev' => env('AGENT_RESTART_NPM_DEV', false),
    ],
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
        action: "chat.{$action->action_type->value}",
        targetType: $action->action_type->isJobAction() ? 'agent_job' : 'agent_job_run',
        targetId: $action->parameters['job_id'] ?? $action->parameters['run_id'] ?? '',
        changedFields: [],
        before: null,
        after: $action->result,
        requestId: $action->id,
        ipAddress: null,
        userAgent: "messenger:{$identityLink->connector_account->provider}",
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
```

### A.16 Testing

Create test suites for messenger functionality.

**Files to Create:**
- `tests/Feature/Messenger/SlackWebhookTest.php`
- `tests/Feature/Messenger/TelegramWebhookTest.php`
- `tests/Feature/Messenger/AccountLinkTest.php`
- `tests/Feature/Messenger/ChatActionTest.php`
- `tests/Feature/Messenger/AttachmentTest.php`
- `tests/Unit/Messenger/SignatureVerifierTest.php`
- `tests/Unit/Messenger/ReplayProtectionTest.php`
- `tests/Unit/Messenger/IdempotencyKeyGeneratorTest.php`
- `tests/Unit/Messenger/RateLimiterTest.php`
- `tests/Unit/Messenger/CircuitBreakerTest.php`

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
9. **A.8** Outbound Message Processing (depends on A.2)
10. **A.9** Attachment Handling (depends on A.1, A.7)
11. **A.11** API Endpoints (depends on A.5, A.6, A.9)
12. **A.12** CLI Commands (depends on A.2, A.13)
13. **A.13** Configuration (independent, can be early)
14. **A.14** Horizon Queue Configuration (depends on A.13)
15. **A.15** Audit Integration (depends on A.6)
16. **A.16** Testing (depends on all above)
17. **B.1** Discord Adapter (depends on Phase A completion)
18. **B.2** WhatsApp Adapter (depends on Phase A completion)
19. **B.3** Discord and WhatsApp Attachments (depends on B.1, B.2)

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
- A.16 Testing
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

