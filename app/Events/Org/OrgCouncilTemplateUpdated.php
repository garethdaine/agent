<?php

declare(strict_types=1);

namespace App\Events\Org;

use App\Models\OrgCouncilTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgCouncilTemplateUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $before  State before update
     * @param  array<string, mixed>  $after  State after update
     */
    public function __construct(
        public readonly OrgCouncilTemplate $template,
        public readonly array $before,
        public readonly array $after,
        public readonly string $correlationId,
    ) {}
}
