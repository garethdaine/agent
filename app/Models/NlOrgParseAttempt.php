<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NlOrgParseAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parsed_result' => 'array',
            'confidence' => 'float',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnapplied($query)
    {
        return $query->whereNull('applied_at');
    }

    public function scopeForIdempotency($query, string $userId, string $rawInput)
    {
        $window = config('agent.nl_parse.idempotency_window_seconds', 60);

        return $query
            ->where('user_id', $userId)
            ->where('raw_input', $rawInput)
            ->where('created_at', '>=', now()->subSeconds($window));
    }
}
