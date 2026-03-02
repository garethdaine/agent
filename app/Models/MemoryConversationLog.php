<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable conversation log entry for Long-term Memory (Layer 3).
 *
 * Records agent run conversation history with role and sequence tracking.
 */
class MemoryConversationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The name of the "updated at" column.
     * This table only has created_at (immutable records).
     */
    public const UPDATED_AT = null;

    /**
     * Role constants.
     */
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    public const ROLE_TOOL = 'tool';

    /**
     * Event type constants.
     */
    public const EVENT_MESSAGE = 'message';

    public const EVENT_TOOL_CALL = 'tool_call';

    public const EVENT_TOOL_RESULT = 'tool_result';

    public const EVENT_THINKING = 'thinking';

    /**
     * Valid roles.
     *
     * @var array<string>
     */
    public static array $validRoles = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
        self::ROLE_SYSTEM,
        self::ROLE_TOOL,
    ];

    /**
     * Valid event types.
     *
     * @var array<string>
     */
    public static array $validEventTypes = [
        self::EVENT_MESSAGE,
        self::EVENT_TOOL_CALL,
        self::EVENT_TOOL_RESULT,
        self::EVENT_THINKING,
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'run_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class, 'job_id');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by run.
     */
    public function scopeForRun(Builder $query, int $runId): void
    {
        $query->where('run_id', $runId);
    }

    /**
     * Scope to filter by job.
     */
    public function scopeForJob(Builder $query, int $jobId): void
    {
        $query->where('job_id', $jobId);
    }

    /**
     * Scope to filter by role.
     */
    public function scopeWithRole(Builder $query, string $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Scope to filter by classification level(s).
     *
     * @param  string|array<string>  $classifications
     */
    public function scopeWithClassification(Builder $query, string|array $classifications): void
    {
        $query->whereIn('classification', (array) $classifications);
    }

    /**
     * Scope to order by sequence.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence');
    }

    /**
     * Validate the role.
     */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::$validRoles, true);
    }

    /**
     * Validate the event type.
     */
    public static function isValidEventType(string $eventType): bool
    {
        return in_array($eventType, self::$validEventTypes, true);
    }

    /**
     * Get the next sequence number for a run.
     */
    public static function getNextSequence(int $runId): int
    {
        return (int) static::query()
            ->where('run_id', $runId)
            ->max('sequence') + 1;
    }

    /**
     * Get conversation history for a run.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MemoryConversationLog>
     */
    public static function getConversationForRun(int $runId): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->forRun($runId)
            ->ordered()
            ->get();
    }
}
