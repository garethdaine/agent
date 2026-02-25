<?php

namespace App\Models;

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

    protected $guarded = [];

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
}
