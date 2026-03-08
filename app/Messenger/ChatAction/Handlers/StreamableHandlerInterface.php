<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;

interface StreamableHandlerInterface extends ChatActionHandlerInterface
{
    /**
     * Handle the action with streaming output.
     *
     * @param  callable(string): void  $onChunk  Called with each chunk of output as it becomes available
     */
    public function handleStreaming(ChatActionContext $context, callable $onChunk): ChatActionResult;
}
