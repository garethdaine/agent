<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AgentAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

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
