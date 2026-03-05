<?php

namespace App\Models\Runtime;

use App\Enums\Runtime\RuntimeArtifactType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuntimeArtifact extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'runtime_session_id',
        'type',
        'path',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'type' => RuntimeArtifactType::class,
            'metadata_json' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RuntimeSession::class, 'runtime_session_id');
    }
}
