<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ChatMessageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $chat_session_id
 * @property string $connector_account_id
 * @property string $direction
 * @property string $content
 * @property array|null $attachment_ids
 * @property string $idempotency_key
 * @property string|null $provider_event_id
 * @property string|null $provider_message_id
 * @property \Carbon\CarbonInterface|null $provider_timestamp
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\ChatSession|null $session
 * @property-read \App\Models\ConnectorAccount|null $connectorAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatAction> $actions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatAttachment> $attachments
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
#[ObservedBy(ChatMessageObserver::class)]
class ChatMessage extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'chat_session_id',
        'connector_account_id',
        'direction',
        'content',
        'attachment_ids',
        'idempotency_key',
        'provider_event_id',
        'provider_message_id',
        'provider_timestamp',
    ];

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    protected function casts(): array
    {
        return [
            'attachment_ids' => 'array',
            'provider_timestamp' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ChatAction::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class);
    }

    public function scopeInbound(Builder $query): void
    {
        $query->where('direction', self::DIRECTION_INBOUND);
    }

    public function scopeOutbound(Builder $query): void
    {
        $query->where('direction', self::DIRECTION_OUTBOUND);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $message) {
            if (! $message->created_at) {
                $message->created_at = now();
            }
        });
    }
}
