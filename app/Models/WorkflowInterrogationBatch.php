<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInterrogationBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_interrogation_session_id',
        'round',
        'is_active',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'is_active' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowInterrogationSession::class, 'workflow_interrogation_session_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(WorkflowInterrogationBatchQuestion::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
