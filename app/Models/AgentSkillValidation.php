<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $skill_name
 * @property int $team_id
 * @property array $validation_result
 * @property float $risk_score
 * @property bool $overall_pass
 * @property string $source
 * @property int|null $validated_by
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\Team|null $team
 * @property-read \App\Models\User|null $validatedBy
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentSkillValidation extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'skill_name',
        'team_id',
        'validation_result',
        'risk_score',
        'overall_pass',
        'source',
        'validated_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_result' => 'array',
            'risk_score' => 'decimal:3',
            'overall_pass' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
