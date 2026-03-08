<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin Builder
 */
class ChatAction extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'chat_message_id',
        'action_type',
        'parameters',
        'status',
        'result',
        'error',
        'requires_confirmation',
        'confirmed_at',
        'executed_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_TIMEOUT = 'timeout';

    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled', 'timeout'];

    // Legacy aliases preserved for backward compatibility.
    public const PENDING = self::STATUS_PENDING;

    public const EXECUTING = self::STATUS_EXECUTING;

    public const COMPLETED = self::STATUS_COMPLETED;

    public const FAILED = self::STATUS_FAILED;

    public const CANCELLED = self::STATUS_CANCELLED;

    public const TIMEOUT = self::STATUS_TIMEOUT;

    public const ACTION_JOBS_CREATE = 'jobs.create';

    public const ACTION_JOBS_UPDATE = 'jobs.update';

    public const ACTION_JOBS_DELETE = 'jobs.delete';

    public const ACTION_JOBS_LIST = 'jobs.list';

    public const ACTION_RUNS_LIST_ACTIVE = 'runs.list_active';

    public const ACTION_RUNS_STOP = 'runs.stop';

    public const ACTION_RUNS_RETRY = 'runs.retry';

    public const ACTION_RUNS_RUN_NOW = 'runs.run_now';

    public const ACTION_RUNS_STEER = 'runs.steer';

    public const DESTRUCTIVE_ACTIONS = ['runs.stop', 'jobs.update', 'jobs.delete'];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'result' => 'array',
            'requires_confirmation' => 'boolean',
            'confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function pendingConfirmation(): HasOne
    {
        return $this->hasOne(PendingConfirmation::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExecuting(Builder $query): void
    {
        $query->where('status', self::STATUS_EXECUTING);
    }

    public function scopeTerminal(Builder $query): void
    {
        $query->whereIn('status', self::TERMINAL_STATUSES);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExecuting(): bool
    {
        return $this->status === self::STATUS_EXECUTING;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isDestructive(): bool
    {
        return in_array($this->action_type, self::DESTRUCTIVE_ACTIONS, true);
    }

    public function requiresConfirmation(): bool
    {
        return $this->requires_confirmation && ! $this->confirmed_at;
    }

    protected static function booted(): void
    {
        static::creating(function (ChatAction $action) {
            if (! $action->created_at) {
                $action->created_at = now();
            }
        });
    }
}
