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
 * @property string $id
 * @property int $user_id
 * @property string $connector_account_id
 * @property string $provider
 * @property string $channel_id
 * @property string|null $thread_id
 * @property string $status
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property string|null $compaction_summary
 * @property int $compaction_message_count
 * @property \Carbon\CarbonInterface|null $compaction_at
 * @property string|null $compaction_boundary_message_id
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\ConnectorAccount|null $connectorAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatMessage> $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PendingConfirmation> $pendingConfirmations
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ChatSession extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'connector_account_id',
        'provider',
        'channel_id',
        'thread_id',
        'status',
        'compaction_summary',
        'compaction_message_count',
        'compaction_at',
        'compaction_boundary_message_id',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'compaction_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function pendingConfirmations(): HasMany
    {
        return $this->hasMany(PendingConfirmation::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeForConnector(Builder $query, string $connectorAccountId): void
    {
        $query->where('connector_account_id', $connectorAccountId);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
