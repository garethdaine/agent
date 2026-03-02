<?php

namespace App\Events\Org;

use App\Models\OrgCouncilTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgCouncilTemplateRestored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly OrgCouncilTemplate $template,
        public readonly string $correlationId,
    ) {}
}
