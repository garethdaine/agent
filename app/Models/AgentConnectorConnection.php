<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin Builder
 */
class AgentConnectorConnection extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'team_id',
        'connector_id',
        'status',
        'health_score',
        'config',
        'webhook_subscriptions',
        'last_health_check_at',
        'last_action_at',
        'action_count_24h',
        'error_count_24h',
        'connected_by',
        'connected_at',
        'disconnected_by',
        'disconnected_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'webhook_subscriptions' => 'array',
            'health_score' => 'decimal:2',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'last_action_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AgentConnector::class, 'connector_id');
    }

    public function credential(): HasOne
    {
        return $this->hasOne(AgentConnectorCredential::class, 'team_id', 'team_id')
            ->whereColumn('agent_connector_credentials.connector_id', 'agent_connector_connections.connector_id');
    }

    public function invocations(): HasMany
    {
        return $this->hasMany(AgentConnectorInvocation::class, 'connection_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(AgentConnectorWebhookEvent::class, 'connection_id');
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AgentConnectorApproval::class, 'connection_id');
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONNECTED);
    }

    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('health_score', '>=', 0.8);
    }

    public function scopeDegraded(Builder $query): Builder
    {
        return $query->whereBetween('health_score', [0.5, 0.8]);
    }
}
