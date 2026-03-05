<?php

declare(strict_types=1);

namespace App\Jobs\Messenger;

use App\Models\ChatSession;
use App\Services\Messenger\CompactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompactionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $chatSessionId,
        public ?string $instructions = null,
    ) {
        $this->onQueue('messenger-default');
    }

    public function handle(CompactionService $compaction): void
    {
        $session = ChatSession::find($this->chatSessionId);
        if ($session === null) {
            return;
        }

        $compaction->compactIfNeeded($session, $this->instructions);
    }
}
