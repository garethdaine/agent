<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Runtime\RuntimeSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $run_id
 * @property int|null $job_id
 * @property string $role
 * @property string $content
 * @property int $sequence
 * @property string $event_type
 * @property string $classification
 * @property \Carbon\CarbonInterface|null $created_at
 * @property string|null $runtime_session_id
 * @property string|null $source_type
 * @property string|null $source_id
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\AgentJobRun|null $run
 * @property-read \App\Models\AgentJob|null $job
 * @property-read \App\Models\Runtime\RuntimeSession|null $runtimeSession
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class MemoryConversationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'run_id',
        'job_id',
        'runtime_session_id',
        'source_type',
        'source_id',
        'role',
        'content',
        'sequence',
        'event_type',
        'classification',
        'created_at',
    ];

    /**
     * Source type constants for generic source identification.
     */
    public const SOURCE_INTERROGATION = 'interrogation_session';

    public const SOURCE_REPO_ANALYSIS = 'repo_analysis_session';

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

    public function runtimeSession(): BelongsTo
    {
        return $this->belongsTo(RuntimeSession::class, 'runtime_session_id');
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
     * Scope to filter by runtime session.
     */
    public function scopeForRuntimeSession(Builder $query, string $runtimeSessionId): void
    {
        $query->where('runtime_session_id', $runtimeSessionId);
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
     * Get the next sequence number for a runtime session.
     */
    public static function getNextSequenceForRuntimeSession(string $runtimeSessionId): int
    {
        return (int) static::query()
            ->where('runtime_session_id', $runtimeSessionId)
            ->max('sequence') + 1;
    }

    /**
     * Get conversation history for a run.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MemoryConversationLog>
     */
    public static function getConversationForRun(int $runId): \Illuminate\Database\Eloquent\Collection
    {
        return static::query() // @phpstan-ignore return.type
            ->forRun($runId)
            ->ordered()
            ->get();
    }

    /**
     * Scope to filter by generic source type and source id.
     */
    public function scopeForSource(Builder $query, string $sourceType, string $sourceId): void
    {
        $query->where('source_type', $sourceType)->where('source_id', $sourceId);
    }

    /**
     * Get the next sequence number for a generic source.
     */
    public static function getNextSequenceForSource(string $sourceType, string $sourceId): int
    {
        return (int) static::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->max('sequence') + 1;
    }
}
