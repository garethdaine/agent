<?php

namespace App\Models\Runtime;

use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Enums\Security\ContentTrustLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RuntimeToolCall extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'runtime_turn_id',
        'tool_name',
        'arguments_json',
        'result_json',
        'status',
        'duration_ms',
        'requires_approval',
        'approved_at',
        'content_trust_level',
        'injection_score',
        'injection_action',
        'content_sanitized',
    ];

    protected function casts(): array
    {
        return [
            'status' => RuntimeToolCallStatus::class,
            'arguments_json' => 'array',
            'result_json' => 'array',
            'duration_ms' => 'integer',
            'requires_approval' => 'boolean',
            'approved_at' => 'datetime',
            'content_trust_level' => ContentTrustLevel::class,
            'injection_score' => 'float',
            'injection_action' => 'string',
            'content_sanitized' => 'boolean',
        ];
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(RuntimeTurn::class, 'runtime_turn_id');
    }

    public function approval(): HasOne
    {
        return $this->hasOne(RuntimeApproval::class);
    }
}
