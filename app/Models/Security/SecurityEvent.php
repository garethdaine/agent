<?php

declare(strict_types=1);

namespace App\Models\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'runtime_session_id',
        'runtime_tool_call_id',
        'event_type',
        'severity',
        'details_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SecurityEventType::class,
            'details_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function runtimeSession(): BelongsTo
    {
        return $this->belongsTo(RuntimeSession::class);
    }

    public function runtimeToolCall(): BelongsTo
    {
        return $this->belongsTo(RuntimeToolCall::class);
    }

    public function scopeForSession(Builder $query, string $sessionId): void
    {
        $query->where('runtime_session_id', $sessionId);
    }

    public function scopeOfType(Builder $query, SecurityEventType $type): void
    {
        $query->where('event_type', $type);
    }
}
