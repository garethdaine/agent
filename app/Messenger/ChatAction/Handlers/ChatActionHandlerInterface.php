<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;

interface ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult;
}
