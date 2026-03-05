<?php

namespace App\Models\Runtime;

use App\Enums\Runtime\RuntimeTurnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuntimeTurn extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'runtime_session_id',
        'sequence',
        'input_message_id',
        'output_message_id',
        'status',
        'summary',
        'input_tokens',
        'output_tokens',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'status' => RuntimeTurnStatus::class,
            'sequence' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RuntimeSession::class, 'runtime_session_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(RuntimeToolCall::class);
    }
}
