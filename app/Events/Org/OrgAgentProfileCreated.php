<?php

declare(strict_types=1);

namespace App\Events\Org;

use App\Models\OrgAgentProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgAgentProfileCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly OrgAgentProfile $profile,
        public readonly string $correlationId,
    ) {}
}
