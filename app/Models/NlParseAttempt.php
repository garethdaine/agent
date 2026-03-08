<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NlParseAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

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
