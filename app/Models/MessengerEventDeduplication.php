<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class MessengerEventDeduplication extends Model
{
    public $timestamps = false;

    protected $table = 'messenger_event_deduplication';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<', now());
    }

    public function scopeForConnector(Builder $query, string $connectorAccountId): void
    {
        $query->where('connector_account_id', $connectorAccountId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected static function booted(): void
    {
        static::creating(function (MessengerEventDeduplication $record) {
            if (! $record->created_at) {
                $record->created_at = now();
            }
        });
    }
}
