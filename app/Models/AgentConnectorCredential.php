<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class AgentConnectorCredential extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'team_id',
        'connector_id',
        'auth_type',
        'encrypted_data',
        'encryption_key_id',
        'scopes_granted',
        'token_expires_at',
        'refresh_token_expires_at',
        'status',
        'last_refreshed_at',
        'last_used_at',
        'refresh_failure_count',
        'created_by',
        'updated_by',
        'revoked_by',
        'revoked_at',
        'rotation_count',
    ];

    protected function casts(): array
    {
        return [
            'scopes_granted' => 'array',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentConnectorCredentialEvent::class, 'credential_id');
    }
}
