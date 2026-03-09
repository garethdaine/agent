<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\MemoryConversationLog;
use App\Models\User;

class GetMemoryStateAction
{
    /**
     * @return array{total_entries: int, recent_formations: int}
     */
    public function execute(User $user): array
    {
        $totalEntries = MemoryConversationLog::query()
            ->where('user_id', $user->id)
            ->count();

        $recentFormations = MemoryConversationLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return [
            'total_entries' => $totalEntries,
            'recent_formations' => $recentFormations,
        ];
    }
}
