<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ChatAction;

class GetRecentErrorRateAction
{
    public function execute(): float
    {
        $recentActions = ChatAction::query()
            ->where('created_at', '>=', now()->subHour())
            ->whereIn('status', [ChatAction::STATUS_COMPLETED, ChatAction::STATUS_FAILED])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $completed = $recentActions->get(ChatAction::STATUS_COMPLETED, 0);
        $failed = $recentActions->get(ChatAction::STATUS_FAILED, 0);
        $total = $completed + $failed;

        if ($total === 0) {
            return 0.0;
        }

        return round(($failed / $total) * 100, 2);
    }
}
