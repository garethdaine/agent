<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatAction;

class UpdateChatActionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ChatAction $action, array $data): ChatAction
    {
        $action->update($data);

        return $action;
    }
}
