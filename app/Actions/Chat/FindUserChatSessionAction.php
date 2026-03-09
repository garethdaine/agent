<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatSession;

class FindUserChatSessionAction
{
    /**
     * @param  array<string>|null  $with
     */
    public function execute(int $userId, string $id, ?array $with = null, bool $withMessageCount = false): ChatSession
    {
        $query = ChatSession::query()->where('user_id', $userId);

        if ($with !== null) {
            $query->with($with);
        }

        if ($withMessageCount) {
            $query->withCount('messages');
        }

        return $query->findOrFail($id);
    }
}
