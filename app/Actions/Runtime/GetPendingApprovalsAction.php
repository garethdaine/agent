<?php

declare(strict_types=1);

namespace App\Actions\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeSession;
use Illuminate\Database\Eloquent\Collection;

class GetPendingApprovalsAction
{
    /**
     * @return Collection<int, RuntimeApproval>
     */
    public function execute(RuntimeSession $session): Collection
    {
        return RuntimeApproval::whereHas('toolCall.turn', function ($q) use ($session) {
            $q->where('runtime_session_id', $session->id);
        })->where('state', RuntimeApprovalState::Pending)->get();
    }
}
