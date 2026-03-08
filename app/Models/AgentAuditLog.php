<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AgentAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'actor_type',
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'changed_fields_json',
        'before_json',
        'after_json',
        'request_id',
        'ip_address',
        'user_agent',
        'hostname',
        'outcome',
        'error_code',
        'error_message',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Agent audit logs are immutable and cannot be updated.');
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'changed_fields_json' => 'array',
            'before_json' => 'array',
            'after_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
