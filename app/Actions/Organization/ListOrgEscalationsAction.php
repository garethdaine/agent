<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Support\Org\OrgEscalationService;
use Illuminate\Database\Eloquent\Collection;

class ListOrgEscalationsAction
{
    public function __construct(
        private readonly OrgEscalationService $escalationService,
    ) {}

    public function execute(int $userId): Collection
    {
        return $this->escalationService->getUserPendingEscalations($userId);
    }
}
