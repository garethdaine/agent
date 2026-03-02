# Implementation Plan

Derived from discovery session 12.

# Messenger Integration Feature Completion

## Executive Summary

Complete the messenger integration subsystem by replacing all placeholder/simulation code with real implementations, unifying webhook architecture to canonical API routes, implementing creator-only authorization, integrating dead-letter handling with owner notifications, and achieving comprehensive test coverage across all four providers (Slack, Telegram, Discord, WhatsApp).

---

## Section 1: Webhook Architecture Unification

### 1.1 Remove Legacy Web Route Controllers

**Files to Delete:**
- `app/Http/Controllers/Messenger/DiscordWebhookController.php`
- `app/Http/Controllers/Messenger/WhatsAppWebhookController.php`

**Route Removal in `routes/web.php`:**
Remove lines 26-35 containing legacy webhook routes for Discord and WhatsApp.

### 1.2 Extend API Webhook Routes

**Modify `routes/api.php`:**

Add WhatsApp GET verification support:
```php
Route::match(['get', 'post'], '/connectors/whatsapp/webhook', [WebhookController::class, 'whatsapp'])
    ->name('api.connectors.whatsapp.webhook');
```

### 1.3 Implement WhatsApp Verification Handler

**Modify `app/Http/Controllers/Api/V1/Messenger/WebhookController.php`:**

Add method to handle WhatsApp hub.mode=subscribe verification:
```php
public function whatsapp(Request $request)
{
    if ($request->isMethod('get') && $request->query('hub_mode') === 'subscribe') {
        return $this->handleWhatsAppVerification($request);
    }
    
    return $this->handleWhatsAppEvent($request);
}

private function handleWhatsAppVerification(Request $request): Response
{
    $verifyToken = $request->query('hub_verify_token');
    $challenge = $request->query('hub_challenge');
    
    // Validate against configured verify tokens across accounts
    if ($this->whatsAppAdapter->validateVerifyToken($verifyToken)) {
        return response($challenge, 200);
    }
    
    return response('Invalid verify token', 403);
}
```

### 1.4 Fix WhatsApp Webhook URL in Credential Manager

**Modify `app/Support/Messenger/ConnectorCredentialManager.php` line 197:**

Update `getWebhookUrl()` method for WhatsApp to return:
```php
return config('app.url') . '/agent/api/v1/connectors/whatsapp/webhook';
```

### 1.5 Acceptance Verification

- WhatsApp GET requests with `hub.mode=subscribe` return challenge value
- WhatsApp POST requests with valid signature are processed
- Legacy web route URLs return 404
- Connector setup displays correct API route URLs

---

## Section 2: Chat Action Handlers Implementation

### 2.1 Jobs List Handler

**Modify `app/Messenger/ChatAction/Handlers/JobsListHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $user = $context->getAuthenticatedUser();
    
    $jobs = AgentJob::where('user_id', $user->id)
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get(['id', 'name', 'status', 'schedule', 'updated_at']);
    
    return ChatActionResult::success(
        $this->formatJobsList($jobs),
        ['jobs' => $jobs->toArray()]
    );
}
```

### 2.2 Jobs Create Handler

**Modify `app/Messenger/ChatAction/Handlers/JobsCreateHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $user = $context->getAuthenticatedUser();
    $params = $context->getParameters();
    
    $validated = $this->validateJobParameters($params);
    
    $job = AgentJob::create([
        'user_id' => $user->id,
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'schedule' => $validated['schedule'] ?? null,
        'configuration' => $validated['configuration'] ?? [],
        'status' => 'pending',
    ]);
    
    return ChatActionResult::success(
        "Job '{$job->name}' created successfully (ID: {$job->id})",
        ['job' => $job->toArray()]
    );
}
```

### 2.3 Jobs Update Handler

**Modify `app/Messenger/ChatAction/Handlers/JobsUpdateHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $job = $context->getTargetJob();
    $params = $context->getParameters();
    
    $validated = $this->validateUpdateParameters($params);
    
    $job->update($validated);
    
    return ChatActionResult::success(
        "Job '{$job->name}' updated successfully",
        ['job' => $job->fresh()->toArray()]
    );
}
```

### 2.4 Jobs Delete Handler

**Modify `app/Messenger/ChatAction/Handlers/JobsDeleteHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $job = $context->getTargetJob();
    $jobName = $job->name;
    $jobId = $job->id;
    
    // Check confirmation requirement
    if ($this->requiresConfirmation($context, 'delete')) {
        return $this->requestConfirmation($context, 'delete', $job);
    }
    
    $job->delete();
    
    return ChatActionResult::success(
        "Job '{$jobName}' (ID: {$jobId}) deleted successfully"
    );
}
```

### 2.5 Runs List Active Handler

**Modify `app/Messenger/ChatAction/Handlers/RunsListActiveHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $user = $context->getAuthenticatedUser();
    
    $runs = AgentJobRun::whereHas('job', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereIn('status', ['running', 'pending'])
        ->with('job:id,name')
        ->orderBy('started_at', 'desc')
        ->limit(10)
        ->get(['id', 'job_id', 'status', 'started_at', 'progress']);
    
    return ChatActionResult::success(
        $this->formatRunsList($runs),
        ['runs' => $runs->toArray()]
    );
}
```

### 2.6 Runs Run Now Handler

**Modify `app/Messenger/ChatAction/Handlers/RunsRunNowHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $job = $context->getTargetJob();
    
    $run = AgentJobRun::create([
        'job_id' => $job->id,
        'status' => 'pending',
        'triggered_by' => 'chat_command',
        'triggered_at' => now(),
    ]);
    
    dispatch(new ExecuteAgentJob($run));
    
    return ChatActionResult::success(
        "Job '{$job->name}' queued for immediate execution (Run ID: {$run->id})",
        ['run' => $run->toArray()]
    );
}
```

### 2.7 Runs Stop Handler

**Modify `app/Messenger/ChatAction/Handlers/RunsStopHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $run = $context->getTargetRun();
    
    // Check confirmation requirement
    if ($this->requiresConfirmation($context, 'stop')) {
        return $this->requestConfirmation($context, 'stop', $run);
    }
    
    if (!in_array($run->status, ['running', 'pending'])) {
        return ChatActionResult::failure(
            "Run {$run->id} is not active (current status: {$run->status})"
        );
    }
    
    $run->update([
        'status' => 'stopped',
        'stopped_at' => now(),
        'stopped_by' => 'chat_command',
    ]);
    
    event(new AgentRunStopped($run));
    
    return ChatActionResult::success(
        "Run {$run->id} stopped successfully"
    );
}
```

### 2.8 Runs Retry Handler

**Modify `app/Messenger/ChatAction/Handlers/RunsRetryHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $run = $context->getTargetRun();
    
    if (!in_array($run->status, ['failed', 'stopped'])) {
        return ChatActionResult::failure(
            "Run {$run->id} cannot be retried (current status: {$run->status})"
        );
    }
    
    $newRun = AgentJobRun::create([
        'job_id' => $run->job_id,
        'status' => 'pending',
        'triggered_by' => 'chat_retry',
        'parent_run_id' => $run->id,
        'triggered_at' => now(),
    ]);
    
    dispatch(new ExecuteAgentJob($newRun));
    
    return ChatActionResult::success(
        "Retry queued for job '{$run->job->name}' (New Run ID: {$newRun->id})",
        ['run' => $newRun->toArray()]
    );
}
```

### 2.9 Runs Steer Handler

**Modify `app/Messenger/ChatAction/Handlers/RunsSteerHandler.php`:**

Replace placeholder at line 12:
```php
public function handle(ChatActionContext $context): ChatActionResult
{
    $run = $context->getTargetRun();
    $params = $context->getParameters();
    
    // Check confirmation requirement
    if ($this->requiresConfirmation($context, 'steer')) {
        return $this->requestConfirmation($context, 'steer', $run);
    }
    
    if ($run->status !== 'running') {
        return ChatActionResult::failure(
            "Run {$run->id} is not active and cannot be steered"
        );
    }
    
    // Filter out credential fields
    $mutableParams = $this->filterCredentialFields($params);
    
    $run->job->update($mutableParams);
    
    event(new AgentJobSteered($run, $mutableParams));
    
    return ChatActionResult::success(
        "Run {$run->id} parameters updated: " . implode(', ', array_keys($mutableParams)),
        ['updated_fields' => array_keys($mutableParams)]
    );
}

private function filterCredentialFields(array $params): array
{
    $credentialFields = ['token', 'secret', 'api_key', 'password', 'credentials'];
    return array_diff_key($params, array_flip($credentialFields));
}
```

### 2.10 Acceptance Verification

- Each handler executes real database operations
- All handlers return properly formatted ChatActionResult
- Confirmation flow triggers for configured destructive actions
- No placeholder comments or simulation code remains

---

## Section 3: Authorization Model Implementation

### 3.1 Implement Ownership Check for Jobs

**Modify `app/Messenger/Validation/ChatActionPolicyValidator.php` line 106:**

```php
protected function authorizeJobAction(ChatActionContext $context, AgentJob $job): bool
{
    $user = $context->getAuthenticatedUser();
    
    if ($job->user_id !== $user->id) {
        return false;
    }
    
    return true;
}
```

### 3.2 Implement Ownership Check for Runs

**Modify `app/Messenger/Validation/ChatActionPolicyValidator.php` line 144:**

```php
protected function authorizeRunAction(ChatActionContext $context, AgentJobRun $run): bool
{
    $user = $context->getAuthenticatedUser();
    
    if ($run->job->user_id !== $user->id) {
        return false;
    }
    
    return true;
}
```

### 3.3 Implement Ownership Check for Steer Operations

**Modify `app/Messenger/Validation/ChatActionPolicyValidator.php` line 189:**

```php
protected function authorizeSteerAction(ChatActionContext $context, AgentJobRun $run): bool
{
    $user = $context->getAuthenticatedUser();
    
    if ($run->job->user_id !== $user->id) {
        return false;
    }
    
    return true;
}
```

### 3.4 Generic Denial Response

**Add to ChatActionPolicyValidator:**

```php
public function denyWithGenericMessage(): ChatActionResult
{
    return ChatActionResult::failure('Permission denied');
}
```

### 3.5 Acceptance Verification

- Owner can perform all operations on their jobs/runs
- Non-owner receives "Permission denied" for any job/run action
- Response does not reveal whether resource exists
- No ownership bypass vulnerabilities exist

---

## Section 4: User Preference Models

### 4.1 Create UserNotificationSetting Model

**Create `app/Models/UserNotificationSetting.php`:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getChannelOptions(): array
    {
        return ['email', 'in_app', 'both'];
    }
}
```

### 4.2 Create UserNotificationSetting Migration

**Create `database/migrations/xxxx_xx_xx_create_user_notification_settings_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('channel', ['email', 'in_app', 'both'])->default('email');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
    }
};
```

### 4.3 Create UserChatPreference Model

**Create `app/Models/UserChatPreference.php`:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function requiresConfirmationFor(string $action): bool
    {
        return match ($action) {
            'delete' => $this->require_confirmation_for_delete,
            'stop' => $this->require_confirmation_for_stop,
            'steer' => $this->require_confirmation_for_steer,
            default => false,
        };
    }
}
```

### 4.4 Create UserChatPreference Migration

**Create `database/migrations/xxxx_xx_xx_create_user_chat_preferences_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_chat_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('require_confirmation_for_delete')->default(true);
            $table->boolean('require_confirmation_for_stop')->default(true);
            $table->boolean('require_confirmation_for_steer')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chat_preferences');
    }
};
```

### 4.5 Add User Model Relationships

**Modify `app/Models/User.php`:**

```php
public function notificationSetting(): HasOne
{
    return $this->hasOne(UserNotificationSetting::class);
}

public function chatPreference(): HasOne
{
    return $this->hasOne(UserChatPreference::class);
}

public function getNotificationChannel(): string
{
    return $this->notificationSetting?->channel ?? 'email';
}

public function requiresConfirmationFor(string $action): bool
{
    return $this->chatPreference?->requiresConfirmationFor($action) ?? true;
}
```

### 4.6 Settings UI Exposure

**Route Registration (if web settings exist):**
Add routes for preference management in existing user settings area.

**API Endpoints:**
```php
Route::prefix('user/settings')->group(function () {
    Route::get('/notifications', [UserSettingsController::class, 'getNotificationSettings']);
    Route::put('/notifications', [UserSettingsController::class, 'updateNotificationSettings']);
    Route::get('/chat-preferences', [UserSettingsController::class, 'getChatPreferences']);
    Route::put('/chat-preferences', [UserSettingsController::class, 'updateChatPreferences']);
});
```

### 4.7 Acceptance Verification

- User can view and modify notification channel preference
- User can view and modify confirmation requirements for delete/stop/steer
- Settings persist across sessions
- Default values apply for users without explicit preferences

---

## Section 5: Outbound Message Failure Handling

### 5.1 Configure Retry Policy

**Modify `app/Jobs/Messenger/SendOutboundMessage.php`:**

```php
public $tries = 3;

public $backoff = [30, 120, 480]; // 30 seconds, 2 minutes, 8 minutes

public function retryUntil(): DateTime
{
    return now()->addMinutes(15);
}
```

### 5.2 Integrate Dead-Letter Manager

**Modify `app/Jobs/Messenger/SendOutboundMessage.php` line 144:**

```php
public function failed(Throwable $exception): void
{
    // Store in dead-letter queue
    app(DeadLetterManager::class)->store(
        type: 'outbound_message',
        payload: [
            'message_id' => $this->message->id,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'content' => $this->content,
            'attempts' => $this->attempts(),
        ],
        reason: $exception->getMessage(),
        context: [
            'job_id' => $this->message->job_id ?? null,
            'run_id' => $this->message->run_id ?? null,
            'exception_class' => get_class($exception),
        ]
    );

    // Notify job owner
    $this->notifyOwner($exception);
}

private function notifyOwner(Throwable $exception): void
{
    $owner = $this->resolveOwner();
    
    if (!$owner) {
        return;
    }

    $notification = new OutboundMessageFailedNotification(
        message: $this->message,
        reason: $exception->getMessage()
    );

    $channel = $owner->getNotificationChannel();
    
    $owner->notify($notification->via($channel));
}

private function resolveOwner(): ?User
{
    if ($this->message->job_id) {
        return AgentJob::find($this->message->job_id)?->user;
    }
    
    return null;
}
```

### 5.3 Create Failure Notification

**Create `app/Notifications/OutboundMessageFailedNotification.php`:**

```php
<?php

namespace App\Notifications;

use App\Models\OutboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutboundMessageFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OutboundMessage $message,
        public string $reason
    ) {}

    public function via(string $channel): array
    {
        return match ($channel) {
            'email' => ['mail'],
            'in_app' => ['database'],
            'both' => ['mail', 'database'],
            default => ['mail'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Outbound Message Delivery Failed')
            ->line("A message failed to deliver after 3 attempts.")
            ->line("Reason: {$this->reason}")
            ->line("Message ID: {$this->message->id}")
            ->action('View Dead Letter Queue', url('/admin/dead-letters'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'outbound_message_failed',
            'message_id' => $this->message->id,
            'reason' => $this->reason,
        ];
    }
}
```

### 5.4 Acceptance Verification

- Failed message appears in dead-letter queue after 3 retries
- Owner receives notification via configured channel
- Retry backoff follows 30s, 2min, 8min schedule
- Dead-letter entry contains full context for debugging

---

## Section 6: Connector Test Endpoint Enhancement

### 6.1 Discord Test Implementation

**Modify `app/Http/Controllers/Api/V1/MessengerConnectorController.php` line 347:**

```php
private function testDiscord(array $credentials): array
{
    $results = [
        'provider' => 'discord',
        'valid' => true,
        'checks' => [],
    ];

    // Credential format validation
    $validator = app(DiscordCredentialValidator::class);
    $formatResult = $validator->validate($credentials);
    $results['checks']['credential_format'] = [
        'passed' => $formatResult->isValid(),
        'message' => $formatResult->getMessage(),
    ];

    if (!$formatResult->isValid()) {
        $results['valid'] = false;
        return $results;
    }

    // API probe
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $credentials['bot_token'],
        ])->get('https://discord.com/api/v10/users/@me');

        $results['checks']['api_probe'] = [
            'passed' => $response->successful(),
            'message' => $response->successful() 
                ? 'Bot token validated successfully' 
                : 'Invalid bot token',
            'bot_username' => $response->json('username'),
        ];

        if (!$response->successful()) {
            $results['valid'] = false;
            return $results;
        }
    } catch (Throwable $e) {
        $results['checks']['api_probe'] = [
            'passed' => false,
            'message' => 'API connection failed: ' . $e->getMessage(),
        ];
        $results['valid'] = false;
        return $results;
    }

    // Register slash commands on success
    $registrar = app(SlashCommandRegistrar::class);
    $registrationResult = $registrar->register($credentials);
    $results['checks']['slash_commands'] = [
        'passed' => $registrationResult->isSuccessful(),
        'message' => $registrationResult->getMessage(),
        'commands_registered' => $registrationResult->getCommandCount(),
    ];

    $results['setup_guidance'] = [
        'webhook_url' => $this->getWebhookUrl('discord'),
        'next_steps' => [
            'Add bot to your Discord server',
            'Configure webhook URL in Discord developer portal',
        ],
    ];

    return $results;
}
```

### 6.2 WhatsApp Test Implementation

**Add to `MessengerConnectorController.php`:**

```php
private function testWhatsApp(array $credentials): array
{
    $results = [
        'provider' => 'whatsapp',
        'valid' => true,
        'checks' => [],
    ];

    // Credential format validation
    $validator = app(WhatsAppCredentialValidator::class);
    $formatResult = $validator->validate($credentials);
    $results['checks']['credential_format'] = [
        'passed' => $formatResult->isValid(),
        'message' => $formatResult->getMessage(),
    ];

    if (!$formatResult->isValid()) {
        $results['valid'] = false;
        return $results;
    }

    // API probe
    try {
        $phoneNumberId = $credentials['phone_number_id'];
        $accessToken = $credentials['access_token'];

        $response = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/v18.0/{$phoneNumberId}");

        $results['checks']['api_probe'] = [
            'passed' => $response->successful(),
            'message' => $response->successful()
                ? 'Access token validated successfully'
                : 'Invalid access token or phone number ID',
            'display_phone_number' => $response->json('display_phone_number'),
        ];

        if (!$response->successful()) {
            $results['valid'] = false;
            return $results;
        }
    } catch (Throwable $e) {
        $results['checks']['api_probe'] = [
            'passed' => false,
            'message' => 'API connection failed: ' . $e->getMessage(),
        ];
        $results['valid'] = false;
        return $results;
    }

    $results['setup_guidance'] = [
        'webhook_url' => $this->getWebhookUrl('whatsapp'),
        'verify_token' => 'Configure this in your WhatsApp Business settings',
        'next_steps' => [
            'Set webhook URL in Meta Developer Console',
            'Configure verify token to match your settings',
            'Subscribe to messages webhook field',
        ],
    ];

    return $results;
}
```

### 6.3 Fix Discord Ingress Probe 401 Handling

**Modify `app/Messenger/Validation/IngressProbe.php` line 389:**

```php
protected function probeDiscord(array $credentials): ProbeResult
{
    try {
        $response = $this->sendProbeRequest('discord', $credentials);

        if ($response->status() === 401) {
            // 401 is expected for unsigned probe calls
            return ProbeResult::guidance(
                'Ingress endpoint responded with 401 (expected for unsigned requests). ' .
                'Webhook signature verification is working correctly.'
            );
        }

        if ($response->successful()) {
            return ProbeResult::success('Ingress endpoint is reachable and responding');
        }

        return ProbeResult::failure(
            "Ingress endpoint returned unexpected status: {$response->status()}"
        );
    } catch (Throwable $e) {
        return ProbeResult::failure('Ingress probe failed: ' . $e->getMessage());
    }
}
```

### 6.4 Acceptance Verification

- Discord test returns credential validation, API probe, and slash command registration results
- WhatsApp test returns credential validation and API probe results
- Discord 401 on ingress probe returns guidance, not failure
- Setup guidance includes correct webhook URLs and next steps

---

## Section 7: Discord Slash Command Registration

### 7.1 Create Slash Command Registrar Service

**Create `app/Services/Messenger/SlashCommandRegistrar.php`:**

```php
<?php

namespace App\Services\Messenger;

use Illuminate\Support\Facades\Http;

class SlashCommandRegistrar
{
    private array $commands = [
        [
            'name' => 'jobs',
            'description' => 'Manage agent jobs',
            'options' => [
                [
                    'name' => 'list',
                    'description' => 'List your jobs',
                    'type' => 1, // SUB_COMMAND
                ],
                [
                    'name' => 'create',
                    'description' => 'Create a new job',
                    'type' => 1,
                    'options' => [
                        [
                            'name' => 'name',
                            'description' => 'Job name',
                            'type' => 3, // STRING
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'name' => 'run',
                    'description' => 'Run a job immediately',
                    'type' => 1,
                    'options' => [
                        [
                            'name' => 'job_id',
                            'description' => 'Job ID to run',
                            'type' => 4, // INTEGER
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'runs',
            'description' => 'Manage job runs',
            'options' => [
                [
                    'name' => 'active',
                    'description' => 'List active runs',
                    'type' => 1,
                ],
                [
                    'name' => 'stop',
                    'description' => 'Stop a running job',
                    'type' => 1,
                    'options' => [
                        [
                            'name' => 'run_id',
                            'description' => 'Run ID to stop',
                            'type' => 4,
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function register(array $credentials): RegistrationResult
    {
        try {
            $applicationId = $credentials['application_id'];
            $botToken = $credentials['bot_token'];

            $response = Http::withHeaders([
                'Authorization' => 'Bot ' . $botToken,
            ])->put(
                "https://discord.com/api/v10/applications/{$applicationId}/commands",
                $this->commands
            );

            if ($response->successful()) {
                return RegistrationResult::success(
                    'Slash commands registered successfully',
                    count($this->commands)
                );
            }

            return RegistrationResult::failure(
                'Failed to register slash commands: ' . $response->body()
            );
        } catch (Throwable $e) {
            return RegistrationResult::failure(
                'Slash command registration failed: ' . $e->getMessage()
            );
        }
    }
}
```

### 7.2 Create Registration Result DTO

**Create `app/Services/Messenger/RegistrationResult.php`:**

```php
<?php

namespace App\Services\Messenger;

class RegistrationResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private int $commandCount = 0
    ) {}

    public static function success(string $message, int $commandCount): self
    {
        return new self(true, $message, $commandCount);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCommandCount(): int
    {
        return $this->commandCount;
    }
}
```

### 7.3 Acceptance Verification

- Slash commands register during Discord connector test success
- Registration failure does not block overall test success
- Commands appear in Discord server after successful registration

---

## Section 8: Installer Command Update

### 8.1 Update Help Text

**Modify `app/Console/Commands/AgentInstallCommand.php` line 26:**

```php
protected $signature = 'agent:install
    {--provider= : Messenger provider (slack, telegram, discord, whatsapp)}
    {--skip-validation : Skip credential validation}
    {--force : Overwrite existing configuration}';

protected $description = 'Install and configure agent messenger integrations for Slack, Telegram, Discord, or WhatsApp';
```

### 8.2 Acceptance Verification

- Help text displays all four providers
- Command accepts all four provider values

---

## Section 9: Test Coverage Implementation

### 9.1 Discord E2E Test Scenarios

**Extend `tests/Feature/Messenger/Webhooks/DiscordWebhookTest.php`:**

```php
/** @test */
public function it_verifies_discord_webhook_signature()
{
    // Test valid signature verification
}

/** @test */
public function it_rejects_invalid_discord_signature()
{
    // Test 401 response for invalid signature
}

/** @test */
public function it_routes_discord_slash_command_to_handler()
{
    // Test intent parsing and routing
}

/** @test */
public function it_denies_unauthorized_discord_job_action()
{
    // Test ownership denial with generic message
}

/** @test */
public function it_executes_discord_job_action_for_owner()
{
    // Test successful action execution
}

/** @test */
public function it_sends_discord_outbound_message_successfully()
{
    // Test outbound flow
}

/** @test */
public function it_dead_letters_discord_message_after_retries()
{
    // Test failure -> dead-letter -> notification flow
}

/** @test */
public function it_requests_confirmation_for_discord_destructive_action()
{
    // Test confirmation workflow
}
```

### 9.2 WhatsApp E2E Test Scenarios

**Extend `tests/Feature/Messenger/Webhooks/WhatsAppWebhookTest.php`:**

```php
/** @test */
public function it_handles_whatsapp_verification_challenge()
{
    $response = $this->get('/agent/api/v1/connectors/whatsapp/webhook', [
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'test_token',
        'hub_challenge' => 'challenge_string',
    ]);

    $response->assertStatus(200);
    $response->assertSee('challenge_string');
}

/** @test */
public function it_rejects_invalid_whatsapp_verify_token()
{
    $response = $this->get('/agent/api/v1/connectors/whatsapp/webhook', [
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong_token',
        'hub_challenge' => 'challenge_string',
    ]);

    $response->assertStatus(403);
}

/** @test */
public function it_processes_signed_whatsapp_event()
{
    // Test POST with valid signature
}

/** @test */
public function it_rejects_invalid_whatsapp_signature()
{
    // Test 401 response for invalid signature
}

/** @test */
public function it_parses_whatsapp_message_intent()
{
    // Test intent parsing
}

/** @test */
public function it_denies_unauthorized_whatsapp_job_action()
{
    // Test ownership denial
}

/** @test */
public function it_executes_whatsapp_job_action_for_owner()
{
    // Test successful action execution
}

/** @test */
public function it_sends_whatsapp_outbound_message()
{
    // Test outbound flow
}

/** @test */
public function it_dead_letters_whatsapp_message_after_retries()
{
    // Test failure flow
}
```

### 9.3 Slack E2E Test Coverage Verification

Verify existing tests cover:
- Webhook signature verification
- Event ingestion
- Intent parsing and routing
- Authorization checks
- Outbound message flow

### 9.4 Telegram E2E Test Coverage Verification

Verify existing tests cover:
- Webhook verification
- Update ingestion
- Command parsing
- Authorization checks
- Outbound message flow

### 9.5 Chat Orchestration Tests

**Extend `tests/Feature/Messenger/ChatOrchestrationTest.php`:**

```php
/** @test */
public function it_routes_job_list_command_to_handler()
{
    // Test routing
}

/** @test */
public function it_routes_job_create_command_to_handler()
{
    // Test routing
}

/** @test */
public function it_enforces_ownership_on_job_update()
{
    // Test authorization
}

/** @test */
public function it_enforces_ownership_on_run_stop()
{
    // Test authorization
}

/** @test */
public function it_respects_user_confirmation_preference()
{
    // Test preference check
}
```

### 9.6 Acceptance Verification

- All provider tests pass
- Coverage includes all 9 test scenarios per provider
- No existing test assertions modified
- Mocks used for all external API calls

---

## Section 10: Cleanup Verification

### 10.1 Placeholder Code Removal Checklist

Verify removal of placeholders in:
- [ ] `JobsListHandler.php:12`
- [ ] `JobsCreateHandler.php:12`
- [ ] `JobsUpdateHandler.php:12`
- [ ] `JobsDeleteHandler.php:12`
- [ ] `RunsListActiveHandler.php:12`
- [ ] `RunsRunNowHandler.php:12`
- [ ] `RunsStopHandler.php:12`
- [ ] `RunsRetryHandler.php:12`
- [ ] `RunsSteerHandler.php:12`
- [ ] `ChatActionPolicyValidator.php:106`
- [ ] `ChatActionPolicyValidator.php:144`
- [ ] `ChatActionPolicyValidator.php:189`
- [ ] `MessengerConnectorController.php:347`
- [ ] `SendOutboundMessage.php:144`

### 10.2 TODO Comment Removal

Search and remove all TODO comments in messenger subsystem files.

### 10.3 Simulation Code Removal

Verify no mock/simulation returns remain in production handlers.

### 10.4 Acceptance Verification

- `grep -r "TODO" app/Messenger/` returns no results
- `grep -r "placeholder" app/Messenger/` returns no results
- `grep -r "simulation" app/Messenger/` returns no results
- All handlers perform real operations

## Sections

- Webhook Architecture Unification
- Chat Action Handlers Implementation
- Authorization Model Implementation
- User Preference Models
- Outbound Message Failure Handling
- Connector Test Endpoint Enhancement
- Discord Slash Command Registration
- Installer Command Update
- Test Coverage Implementation
- Cleanup Verification


## Risks

- WhatsApp multi-account signature fallback may route messages to wrong account if multiple accounts share the same app secret—mitigation: document setup requirement for unique app secrets per account
- Discord slash command registration during connector test could fail silently if Discord API rate limits apply—mitigation: return clear guidance in test results and allow manual retry
- Removing legacy webhook controllers immediately may break existing integrations that haven't updated webhook URLs—mitigation: coordinate deployment with user communication about URL changes
- Dead-letter notification spam possible if multiple messages fail in succession—mitigation: implement notification debouncing or aggregation in future iteration
- User preference tables add database migration dependency—mitigation: ensure migrations are idempotent and include rollback support
- Steer command allowing mid-run parameter changes could cause unexpected job behavior—mitigation: document user responsibility in chat response and confirmation flow


## Assumptions

- Existing AgentJob and AgentJobRun models have user_id foreign key relationships already established
- DeadLetterManager::store() method signature accepts type, payload, reason, and context parameters as documented in codebase
- Discord API v10 endpoints for slash command registration are stable and available
- WhatsApp Cloud API v18.0 endpoints for phone number verification are accessible
- Laravel notification system is configured for both mail and database channels
- Existing credential validators (DiscordCredentialValidator, WhatsAppCredentialValidator) exist and return ValidationResult objects
- User model already implements Notifiable trait for Laravel notifications
- ExecuteAgentJob job class exists for dispatching job runs
- AgentRunStopped and AgentJobSteered events exist or will be created as part of implementation
- HTTP facade is available for external API calls in test and connector code

