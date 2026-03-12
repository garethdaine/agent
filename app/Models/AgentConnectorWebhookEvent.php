<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $connection_id
 * @property string $connector_id
 * @property string $event_type
 * @property string|null $external_event_id
 * @property string $payload_hash
 * @property string $processing_status
 * @property string|null $routed_to_workflow
 * @property string|null $routed_to_job_id
 * @property int|null $processing_duration_ms
 * @property int $retry_count
 * @property string|null $error_message
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\AgentConnectorConnection|null $connection
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentConnectorWebhookEvent extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ROUTED = 'routed';

    public const STATUS_DEAD_LETTER = 'dead_letter';

    protected $fillable = [
        'connection_id',
        'connector_id',
        'event_type',
        'external_event_id',
        'payload_hash',
        'processing_status',
        'routed_to_workflow',
        'routed_to_job_id',
        'processing_duration_ms',
        'retry_count',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AgentConnectorConnection::class, 'connection_id');
    }
}
