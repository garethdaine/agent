<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkflowInterrogationBatchQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_interrogation_batch_id',
        'position',
        'question_key',
        'prompt',
        'answer_type',
        'options_json',
        'is_required',
        'rationale',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'options_json' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WorkflowInterrogationBatch::class, 'workflow_interrogation_batch_id');
    }

    public function answer(): HasOne
    {
        return $this->hasOne(WorkflowInterrogationBatchAnswer::class, 'workflow_interrogation_batch_question_id');
    }
}
