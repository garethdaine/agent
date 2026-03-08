<?php

declare(strict_types=1);

namespace App\Events\Org;

use App\Models\OrgCouncilTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgCouncilTemplateCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly OrgCouncilTemplate $template,
        public readonly string $correlationId,
    ) {}
}
