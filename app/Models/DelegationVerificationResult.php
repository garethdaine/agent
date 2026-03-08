<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegation_task_id
 * @property int|null $delegation_attempt_id
 * @property string $step_type
 * @property int $step_order
 * @property string $verdict
 * @property array|null $evidence_json
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationTask|null $task
 * @property-read \App\Models\DelegationAttempt|null $attempt
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationVerificationResult extends Model
{
    use HasFactory;

    /**
     * Step types for verification.
     */
    public const string STEP_TYPE_AUTOMATED_CHECK = 'automated_check';

    public const string STEP_TYPE_AI_CRITIC = 'ai_critic';

    public const string STEP_TYPE_HUMAN_APPROVAL = 'human_approval';

    /**
     * Verdicts for verification results.
     */
    public const string VERDICT_PASSED = 'passed';

    public const string VERDICT_FAILED = 'failed';

    public const string VERDICT_SKIPPED = 'skipped';

    public const string VERDICT_PENDING = 'pending';

    /**
     * All possible step types.
     */
    public const array STEP_TYPES = [
        self::STEP_TYPE_AUTOMATED_CHECK,
        self::STEP_TYPE_AI_CRITIC,
        self::STEP_TYPE_HUMAN_APPROVAL,
    ];

    /**
     * All possible verdicts.
     */
    public const array VERDICTS = [
        self::VERDICT_PASSED,
        self::VERDICT_FAILED,
        self::VERDICT_SKIPPED,
        self::VERDICT_PENDING,
    ];

    /**
     * Terminal verdicts (no longer pending).
     */
    public const array TERMINAL_VERDICTS = [
        self::VERDICT_PASSED,
        self::VERDICT_FAILED,
        self::VERDICT_SKIPPED,
    ];

    protected $fillable = [
        'delegation_task_id',
        'delegation_attempt_id',
        'step_type',
        'step_order',
        'verdict',
        'evidence_json',
        'started_at',
        'finished_at',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'evidence_json' => 'array',
        'step_order' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the task this verification result belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'delegation_task_id');
    }

    /**
     * Get the attempt this verification result belongs to.
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(DelegationAttempt::class, 'delegation_attempt_id');
    }

    /**
     * Check if the verdict is passed.
     */
    public function isPassed(): bool
    {
        return $this->verdict === self::VERDICT_PASSED;
    }

    /**
     * Check if the verdict is failed.
     */
    public function isFailed(): bool
    {
        return $this->verdict === self::VERDICT_FAILED;
    }

    /**
     * Check if the verdict is pending.
     */
    public function isPending(): bool
    {
        return $this->verdict === self::VERDICT_PENDING;
    }

    /**
     * Check if the verdict is terminal (no longer pending).
     */
    public function isTerminal(): bool
    {
        return in_array($this->verdict, self::TERMINAL_VERDICTS, true);
    }

    /**
     * Check if this result has expired (for human approval steps).
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Scope to filter by pending verdicts.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('verdict', self::VERDICT_PENDING);
    }

    /**
     * Scope to filter by expired human approval results.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
