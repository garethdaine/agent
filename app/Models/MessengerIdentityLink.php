<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $connector_account_id
 * @property string $provider_user_id
 * @property string|null $provider_username
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property string $status
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\ConnectorAccount|null $connectorAccount
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class MessengerIdentityLink extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'connector_account_id',
        'provider_user_id',
        'provider_username',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function approve(): void
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    public function revoke(): void
    {
        $this->update(['status' => self::STATUS_REVOKED]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeForConnector(Builder $query, string $connectorAccountId): void
    {
        $query->where('connector_account_id', $connectorAccountId);
    }

    public function scopeForProviderUser(Builder $query, string $providerUserId): void
    {
        $query->where('provider_user_id', $providerUserId);
    }

    public function scopeValid(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeStatusFilter(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }
}
