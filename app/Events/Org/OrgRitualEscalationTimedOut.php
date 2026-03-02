<?php

namespace App\Events\Org;

use App\Models\OrgEscalation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgRitualEscalationTimedOut
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly OrgEscalation $escalation,
    ) {}
}
