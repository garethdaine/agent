<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $interrogation_session_id
 * @property int $sequence
 * @property \App\Enums\TaskCategory $task_category
 * @property string $title
 * @property string|null $description
 * @property string|null $instructions_markdown
 * @property string $status
 * @property int $attempt_count
 * @property int|null $agent_job_run_id
 * @property string|null $last_error
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\InterrogationSession|null $session
 * @property-read \App\Models\AgentJobRun|null $run
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class InterrogationBuildTask extends Model
{
    protected $fillable = [
        'interrogation_session_id',
        'sequence',
        'title',
        'description',
        'instructions_markdown',
        'task_category',
        'status',
        'attempt_count',
        'agent_job_run_id',
        'last_error',
        'metadata_json',
        'started_at',
        'finished_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const ACTIVE_STATUSES = [
        self::STATUS_IN_PROGRESS,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'attempt_count' => 'integer',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'task_category' => TaskCategory::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterrogationSession::class, 'interrogation_session_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'agent_job_run_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('id');
    }

    /**
     * Get compliance-related metadata fields only.
     *
     * Returns only the fields relevant to workflow compliance from metadata_json,
     * excluding any other application-specific metadata.
     *
     * @return array<string, mixed>
     */
    public function getComplianceMetadata(): array
    {
        $metadata = $this->metadata_json ?? [];

        return array_intersect_key($metadata, array_flip([
            'workflow_policy_version',
            'complexity_classification',
            'plan_required',
            'plan_completed',
            'plan_evidence',
            'verification_required',
            'verification_completed',
            'verification_evidence',
            'compliance_block_reason',
        ]));
    }

    /**
     * Merge compliance-related data into metadata_json.
     *
     * Existing non-compliance fields in metadata_json are preserved.
     * Compliance fields are merged/overwritten.
     *
     * @param  array<string, mixed>  $complianceData
     */
    public function setComplianceMetadata(array $complianceData): void
    {
        $metadata = $this->metadata_json ?? [];
        $this->metadata_json = array_merge($metadata, $complianceData);
    }

    /**
     * Check if the task is blocked due to compliance reasons.
     *
     * Returns true only when both conditions are met:
     * 1. Task status is STATUS_BLOCKED
     * 2. metadata_json contains a compliance_block_reason
     */
    public function isComplianceBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED
            && isset($this->metadata_json['compliance_block_reason']);
    }
}
