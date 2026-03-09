<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\Runtime\RuntimeToolCall;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class GetToolsStateAction
{
    /**
     * @return array{recent_calls: array<int, array<string, mixed>>, pending_approvals: int, unique_tools_24h: int, total_calls_24h: int}
     */
    public function execute(User $user): array
    {
        if (! Schema::hasTable('runtime_tool_calls')) {
            return [
                'recent_calls' => [],
                'pending_approvals' => 0,
                'unique_tools_24h' => 0,
                'total_calls_24h' => 0,
            ];
        }

        $recentCalls = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(15)
            ->get(['id', 'tool_name', 'status', 'duration_ms', 'requires_approval', 'created_at']);

        $pendingApprovals = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending_approval')
            ->count();

        $uniqueTools = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDay())
            ->distinct('tool_name')
            ->count('tool_name');

        $totalCalls = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'recent_calls' => $recentCalls->map(fn (RuntimeToolCall $tc) => [ // @phpstan-ignore argument.unresolvableType
                'id' => $tc->id,
                'tool_name' => $tc->tool_name,
                'status' => $tc->status->value ?? (string) $tc->status, // @phpstan-ignore cast.string
                'duration_ms' => $tc->duration_ms,
                'requires_approval' => $tc->requires_approval,
                'timestamp' => $tc->created_at?->toIso8601String(),
            ])->toArray(),
            'pending_approvals' => $pendingApprovals,
            'unique_tools_24h' => $uniqueTools,
            'total_calls_24h' => $totalCalls,
        ];
    }
}
