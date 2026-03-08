<?php

declare(strict_types=1);

namespace App\Models\Runtime;

use App\Enums\Runtime\PolicySnapshotReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuntimePolicySnapshot extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'runtime_session_id',
        'snapshot_reason',
        'policy_json',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_reason' => PolicySnapshotReason::class,
            'policy_json' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RuntimeSession::class, 'runtime_session_id');
    }
}
