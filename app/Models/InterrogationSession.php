<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Builder
 */
class InterrogationSession extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public const STATUS_SETUP = 'setup';

    public const STATUS_DISCOVERING = 'discovering';

    public const STATUS_INTERROGATING = 'interrogating';

    public const STATUS_SUMMARIZING = 'summarizing';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PAUSED = 'paused';

    public const ACTIVE_STATUSES = ['setup', 'discovering', 'interrogating', 'summarizing', 'planning'];

    public const TERMINAL_STATUSES = ['completed', 'failed'];

    public const RESUMABLE_STATUSES = ['paused', 'failed'];

    public const PHASE_SETUP = 0;

    public const PHASE_DISCOVERY = 1;

    public const PHASE_INTERROGATION = 2;

    public const PHASE_SUMMARY = 3;

    public const PHASE_PLANNING = 4;

    public const TYPE_FEATURE = 'feature';

    public const TYPE_GENERAL = 'general';

    protected function casts(): array
    {
        return [
            'phase' => 'integer',
            'summary_json' => 'array',
            'plan_json' => 'array',
            'annotations_json' => 'array',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InterrogationEvent::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeLatest(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }
}
