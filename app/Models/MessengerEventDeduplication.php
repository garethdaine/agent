<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $connector_account_id
 * @property string $event_id
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\ConnectorAccount|null $connectorAccount
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class MessengerEventDeduplication extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'messenger_event_deduplication';

    protected $fillable = [
        'connector_account_id',
        'event_id',
        'expires_at',
    ];

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
