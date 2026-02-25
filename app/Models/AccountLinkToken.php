<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class AccountLinkToken extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'token_hash';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    public function scopeConsumed(Builder $query): void
    {
        $query->whereNotNull('consumed_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed();
    }

    /**
     * Atomically consume this token.
     *
     * Returns true if consumption was successful, false if already consumed or expired.
     */
    public function consume(): bool
    {
        $affected = static::where('token_hash', $this->token_hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        return $affected === 1;
    }

    /**
     * Create a token hash from a raw token string.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
