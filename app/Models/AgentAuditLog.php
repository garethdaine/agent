<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string $target_type
 * @property string $target_id
 * @property array|null $changed_fields_json
 * @property array|null $before_json
 * @property array|null $after_json
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $hostname
 * @property string $outcome
 * @property string|null $error_code
 * @property string|null $error_message
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
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
