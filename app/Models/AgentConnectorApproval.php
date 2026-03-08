<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class AgentConnectorApproval extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CLARIFIED = 'clarified';

    public const TYPE_APPROVAL = 'approval';

    public const TYPE_CLARIFICATION = 'clarification';

    protected $fillable = [
        'connection_id',
        'connector_id',
        'action_name',
        'type',
        'status',
        'requested_by_run_id',
        'requested_at',
        'expires_at',
        'resolved_by',
        'resolved_at',
        'resolved_via',
        'resolution_payload',
        'request_context',
    ];

    protected function casts(): array
    {
        return [
            'resolution_payload' => 'array',
            'request_context' => 'array',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AgentConnectorConnection::class, 'connection_id');
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AgentConnector::class, 'connector_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
