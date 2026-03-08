<?php

declare(strict_types=1);

namespace App\Events\Org;

use App\Models\OrgAgentProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgAgentProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $before  State before update
     * @param  array<string, mixed>  $after  State after update
     */
    public function __construct(
        public readonly OrgAgentProfile $profile,
        public readonly array $before,
        public readonly array $after,
        public readonly string $correlationId,
    ) {}
}
