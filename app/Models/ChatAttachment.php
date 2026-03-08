<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class ChatAttachment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'chat_message_id',
        'filename',
        'mime_type',
        'size_bytes',
        'storage_path',
        'provider_file_id',
        'scan_status',
        'expires_at',
        'created_at',
    ];

    public const SCAN_STATUS_PENDING = 'pending';

    public const SCAN_STATUS_CLEAN = 'clean';

    public const SCAN_STATUS_INFECTED = 'infected';

    public const SCAN_STATUS_SKIPPED = 'skipped';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('scan_status', self::SCAN_STATUS_PENDING);
    }

    public function scopeClean(Builder $query): void
    {
        $query->where('scan_status', self::SCAN_STATUS_CLEAN);
    }

    public function scopeInfected(Builder $query): void
    {
        $query->where('scan_status', self::SCAN_STATUS_INFECTED);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<', now());
    }

    public function scopeNotExpired(Builder $query): void
    {
        $query->where('expires_at', '>=', now());
    }

    public function isPending(): bool
    {
        return $this->scan_status === self::SCAN_STATUS_PENDING;
    }

    public function hasCleanScan(): bool
    {
        return $this->scan_status === self::SCAN_STATUS_CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->scan_status === self::SCAN_STATUS_INFECTED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected static function booted(): void
    {
        static::creating(function (ChatAttachment $attachment) {
            if (! $attachment->created_at) {
                $attachment->created_at = now();
            }
        });
    }
}
