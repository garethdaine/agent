<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInterrogationBatchAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_interrogation_batch_question_id',
        'answer_type',
        'answer_text',
        'selected_option',
        'selected_options_json',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_options_json' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(WorkflowInterrogationBatchQuestion::class, 'workflow_interrogation_batch_question_id');
    }
}
