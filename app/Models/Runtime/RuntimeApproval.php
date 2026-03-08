<?php

declare(strict_types=1);

namespace App\Models\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuntimeApproval extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'runtime_tool_call_id',
        'requested_by',
        'state',
        'decision_by',
        'decision_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => RuntimeApprovalState::class,
            'expires_at' => 'datetime',
        ];
    }

    public function toolCall(): BelongsTo
    {
        return $this->belongsTo(RuntimeToolCall::class, 'runtime_tool_call_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisionByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }
}
