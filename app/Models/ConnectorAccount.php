<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Messenger\ApprovalMode;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Builder
 */
class ConnectorAccount extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'name',
        'credentials',
        'webhook_secret',
        'connection_mode',
        'status',
        'runtime_state',
        'last_health_check_at',
        'runtime_error_message',
        'config',
        'account_key',
    ];

    protected $hidden = [
        'credentials',
        'webhook_secret',
    ];

    public const PROVIDER_SLACK = 'slack';

    public const PROVIDER_TELEGRAM = 'telegram';

    public const PROVIDER_DISCORD = 'discord';

    public const PROVIDER_WHATSAPP = 'whatsapp';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    public const MODE_LOCAL = 'local';

    public const MODE_WEBHOOK = 'webhook';

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'config' => 'array',
            'runtime_state' => WorkerHealthStatus::class,
            'last_health_check_at' => 'datetime',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function identityLinks(): HasMany
    {
        return $this->hasMany(MessengerIdentityLink::class);
    }

    public function pendingConfirmations(): HasMany
    {
        return $this->hasMany(PendingConfirmation::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_CONNECTED);
    }

    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function isLocalMode(): bool
    {
        return $this->connection_mode === self::MODE_LOCAL;
    }

    public function isWebhookMode(): bool
    {
        return $this->connection_mode === self::MODE_WEBHOOK;
    }

    /**
     * Update the runtime state for this connector.
     *
     * Called by MessengerGatewayManager during health checks.
     */
    public function updateRuntimeState(WorkerHealthStatus $status, ?string $error = null): void
    {
        $this->update([
            'runtime_state' => $status,
            'last_health_check_at' => now(),
            'runtime_error_message' => $error,
        ]);
    }

    /**
     * Scope to filter local-mode connectors.
     */
    public function scopeLocalMode(Builder $query): void
    {
        $query->where('connection_mode', self::MODE_LOCAL);
    }

    /**
     * Scope to filter webhook-mode connectors.
     */
    public function scopeWebhookMode(Builder $query): void
    {
        $query->where('connection_mode', self::MODE_WEBHOOK);
    }

    /**
     * Get the DM access policy for this connector.
     *
     * @return string One of: pairing, allowlist, open, disabled
     */
    public function getDmPolicy(): string
    {
        return $this->config['dm_policy'] ?? 'open';
    }

    /**
     * Set the DM access policy.
     */
    public function setDmPolicy(string $policy): void
    {
        $valid = ['pairing', 'allowlist', 'open', 'disabled'];
        if (! in_array($policy, $valid, true)) {
            throw new \InvalidArgumentException("Invalid DM policy: {$policy}. Valid: ".implode(', ', $valid));
        }

        $config = $this->config ?? [];
        $config['dm_policy'] = $policy;
        $this->update(['config' => $config]);
    }

    /**
     * Get group policy settings.
     *
     * @return array{require_mention: bool, allowed_groups: array<string>}
     */
    public function getGroupPolicy(): array
    {
        $gp = $this->config['group_policy'] ?? [];

        return [
            'require_mention' => (bool) ($gp['require_mention'] ?? false),
            'allowed_groups' => (array) ($gp['allowed_groups'] ?? []),
        ];
    }

    /**
     * Update group policy settings.
     */
    public function setGroupPolicy(bool $requireMention, array $allowedGroups = []): void
    {
        $config = $this->config ?? [];
        $config['group_policy'] = [
            'require_mention' => $requireMention,
            'allowed_groups' => array_values(array_filter($allowedGroups)),
        ];
        $this->update(['config' => $config]);
    }

    /**
     * Get the DM session scope for this connector.
     *
     * @return string One of: main, per_peer
     */
    public function getDmSessionScope(): string
    {
        return $this->config['dm_session_scope'] ?? 'per_peer';
    }

    /**
     * Set the DM session scope.
     */
    public function setDmSessionScope(string $scope): void
    {
        $valid = ['main', 'per_peer'];
        if (! in_array($scope, $valid, true)) {
            throw new \InvalidArgumentException("Invalid DM session scope: {$scope}. Valid: ".implode(', ', $valid));
        }

        $config = $this->config ?? [];
        $config['dm_session_scope'] = $scope;
        $this->update(['config' => $config]);
    }

    public function getStreamingOverrides(): array
    {
        return $this->config['streaming'] ?? [];
    }

    public function setStreamingOverrides(array $overrides): void
    {
        $config = $this->config ?? [];
        $config['streaming'] = array_intersect_key($overrides, array_flip([
            'max_message_chars', 'throttle_ms', 'min_initial_chars', 'cursor', 'supports_native_streaming',
        ]));
        $this->update(['config' => $config]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicConfig(): array
    {
        $config = $this->config ?? [];

        unset(
            $config['signature_verification'],
            $config['configured_at'],
        );

        return $config;
    }

    public function getApprovalMode(): ApprovalMode
    {
        $value = $this->config['approval_mode'] ?? null;

        return ApprovalMode::tryFrom((string) $value) ?? ApprovalMode::Autonomous;
    }

    public function setApprovalMode(ApprovalMode $mode): void
    {
        $config = $this->config ?? [];
        $config['approval_mode'] = $mode->value;
        $this->update(['config' => $config]);
    }

    /**
     * Get system notification settings for this connector.
     *
     * @return array{enabled: bool, channel_id: string|null, thread_id: string|null, notification_level: string, event_types: array<string>}
     */
    public function getSystemNotifications(): array
    {
        $sn = $this->config['system_notifications'] ?? [];

        return [
            'enabled' => (bool) ($sn['enabled'] ?? false),
            'channel_id' => $sn['channel_id'] ?? null,
            'thread_id' => $sn['thread_id'] ?? null,
            'notification_level' => $sn['notification_level'] ?? 'lifecycle',
            'event_types' => (array) ($sn['event_types'] ?? []),
        ];
    }

    /**
     * Update system notification settings.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setSystemNotifications(array $settings): void
    {
        $config = $this->config ?? [];
        $config['system_notifications'] = array_intersect_key($settings, array_flip([
            'enabled', 'channel_id', 'thread_id', 'notification_level', 'event_types',
        ]));
        $this->update(['config' => $config]);
    }

    /**
     * Get the agent soul configuration.
     *
     * @return array{name: string|null, personality: string|null, system_prompt: string|null, user_context: string|null}
     */
    public function getSoul(): array
    {
        $soul = $this->config['soul'] ?? [];

        return [
            'name' => $soul['name'] ?? null,
            'personality' => $soul['personality'] ?? null,
            'system_prompt' => $soul['system_prompt'] ?? null,
            'user_context' => $soul['user_context'] ?? null,
        ];
    }

    /**
     * Update the agent soul configuration.
     *
     * @param  array<string, string|null>  $soul
     */
    public function setSoul(array $soul): void
    {
        $config = $this->config ?? [];
        $config['soul'] = array_filter([
            'name' => $soul['name'] ?? null,
            'personality' => $soul['personality'] ?? null,
            'system_prompt' => $soul['system_prompt'] ?? null,
            'user_context' => $soul['user_context'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $this->update(['config' => $config]);
    }
}
