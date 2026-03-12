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
 * @property string $action_name
 * @property string|null $run_attempt_id
 * @property string|null $delegatee_id
 * @property string|null $workflow_key
 * @property string $http_method
 * @property int|null $http_status
 * @property int|null $duration_ms
 * @property int|null $request_size_bytes
 * @property int|null $response_size_bytes
 * @property int|null $token_usage
 * @property int $retry_count
 * @property string $outcome
 * @property string|null $error_message
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\AgentConnectorConnection|null $connection
 * @property-read \App\Models\AgentConnector|null $connector
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentConnectorInvocation extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_TIMEOUT = 'timeout';

    public const OUTCOME_RATE_LIMITED = 'rate_limited';

    public const OUTCOME_AUTH_FAILED = 'auth_failed';

    protected $fillable = [
        'connection_id',
        'connector_id',
        'action_name',
        'run_attempt_id',
        'delegatee_id',
        'workflow_key',
        'http_method',
        'http_status',
        'duration_ms',
        'request_size_bytes',
        'response_size_bytes',
        'token_usage',
        'retry_count',
        'outcome',
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

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AgentConnector::class, 'connector_id');
    }
}
