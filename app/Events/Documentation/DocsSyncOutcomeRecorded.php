<?php

declare(strict_types=1);

namespace App\Events\Documentation;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocsSyncOutcomeRecorded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $source,
        public readonly bool $success,
        public readonly array $summary,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly string $occurredAt,
    ) {}
}
