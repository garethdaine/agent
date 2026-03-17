<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInterrogationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_interrogation_session_id',
        'filename',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'extracted_text',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowInterrogationSession::class, 'workflow_interrogation_session_id');
    }
}
