<?php

declare(strict_types=1);

namespace App\Events\Documentation;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocsSearchUnavailableDetected
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly ?string $routeName,
        public readonly string $queryHash,
        public readonly string $errorClass,
        public readonly string $errorMessage,
        public readonly array $context,
        public readonly string $occurredAt,
    ) {}
}
