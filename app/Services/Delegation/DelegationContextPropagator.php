<?php

declare(strict_types=1);

namespace App\Services\Delegation;

use App\Models\DelegationContext;
use App\Models\DelegationTask;

class DelegationContextPropagator
{
    /**
     * Propagate eligible context records from the parent task down to the child task.
     * Only contexts with direction "down" or "bidirectional" that have not expired are propagated.
     */
    public function propagateDown(DelegationTask $parent, DelegationTask $child): void
    {
        $contexts = DelegationContext::where('delegation_task_id', $parent->id)
            ->whereIn('propagation_direction', ['down', 'bidirectional'])
            ->get();

        foreach ($contexts as $context) {
            if ($context->isExpired()) {
                continue;
            }

            DelegationContext::create([
                'delegation_task_id' => $child->id,
                'context_json' => $context->context_json,
                'propagation_direction' => $context->propagation_direction,
                'ttl_seconds' => $context->ttl_seconds,
                'created_by_agent_id' => $context->created_by_agent_id,
            ]);
        }
    }

    /**
     * Merge context from a child task up to the parent task.
     * Only contexts with direction "up" or "bidirectional" that have not expired are merged.
     */
    public function mergeUp(DelegationTask $child, DelegationTask $parent): void
    {
        $contexts = DelegationContext::where('delegation_task_id', $child->id)
            ->whereIn('propagation_direction', ['up', 'bidirectional'])
            ->get();

        foreach ($contexts as $context) {
            if ($context->isExpired()) {
                continue;
            }

            DelegationContext::create([
                'delegation_task_id' => $parent->id,
                'context_json' => $context->context_json,
                'propagation_direction' => 'up',
                'ttl_seconds' => $context->ttl_seconds,
                'created_by_agent_id' => $context->created_by_agent_id,
            ]);
        }
    }
}
