<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $input_text
 * @property string $timezone
 * @property string $parser_path
 * @property float|null $confidence
 * @property string|null $cron_result
 * @property array|null $active_hours_result
 * @property string $status
 * @property bool $user_confirmed
 * @property string|null $error_message
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Carbon\CarbonInterface|null $completed_at
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class NlParseAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'input_text',
        'timezone',
        'parser_path',
        'confidence',
        'cron_result',
        'active_hours_result',
        'status',
        'user_confirmed',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'active_hours_result' => 'array',
            'confidence' => 'float',
            'user_confirmed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForIdempotency($query, string $userId, string $inputText, string $timezone)
    {
        $window = config('agent.nl_parse.idempotency_window_seconds', 60);

        return $query
            ->where('user_id', $userId)
            ->where('input_text', $inputText)
            ->where('timezone', $timezone)
            ->where('created_at', '>=', now()->subSeconds($window));
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['queued', 'running']);
    }
}
