<?php

declare(strict_types=1);

namespace App\Models\Runtime;

use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Enums\Security\ContentTrustLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $runtime_turn_id
 * @property string $tool_name
 * @property array|null $arguments_json
 * @property array|null $result_json
 * @property RuntimeToolCallStatus $status
 * @property int|null $duration_ms
 * @property bool $requires_approval
 * @property \Carbon\CarbonInterface|null $approved_at
 * @property ContentTrustLevel|null $content_trust_level
 * @property float|null $injection_score
 * @property string|null $injection_action
 * @property bool|null $content_sanitized
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read RuntimeTurn|null $turn
 * @property-read RuntimeApproval|null $approval
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
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

    /** @return BelongsTo<RuntimeTurn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(RuntimeTurn::class, 'runtime_turn_id');
    }

    public function approval(): HasOne
    {
        return $this->hasOne(RuntimeApproval::class);
    }
}
