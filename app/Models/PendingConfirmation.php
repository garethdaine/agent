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
 * @property string $chat_action_id
 * @property string $chat_session_id
 * @property string $connector_account_id
 * @property string $confirmation_token
 * @property string|null $provider_message_id
 * @property array|null $callback_data
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $confirmed_at
 * @property \Carbon\CarbonInterface|null $cancelled_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\ChatAction|null $action
 * @property-read \App\Models\ChatSession|null $session
 * @property-read \App\Models\ConnectorAccount|null $connectorAccount
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PendingConfirmation extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'chat_action_id',
        'chat_session_id',
        'connector_account_id',
        'confirmation_token',
        'provider_message_id',
        'callback_data',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'callback_data' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ChatAction::class, 'chat_action_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now())
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at');
    }

    public function isPending(): bool
    {
        return $this->confirmed_at === null
            && $this->cancelled_at === null
            && ! $this->isExpired();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function confirm(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->update(['confirmed_at' => now()]);

        return true;
    }

    public function cancel(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->update(['cancelled_at' => now()]);

        return true;
    }

    protected static function booted(): void
    {
        static::creating(function (PendingConfirmation $confirmation) {
            if (! $confirmation->created_at) {
                $confirmation->created_at = now();
            }
        });
    }
}
