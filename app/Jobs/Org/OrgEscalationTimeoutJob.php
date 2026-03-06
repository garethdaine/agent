<?php

namespace App\Jobs\Org;

use App\Events\Org\OrgRitualEscalationTimedOut;
use App\Models\OrgEscalation;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Org\OrgEscalationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job that processes timed-out escalations.
 *
 * This job runs on the scheduler (every minute or as configured) and marks
 * any pending escalations that have exceeded their timeout as timed_out.
 * Timeout results in auto-fail, not auto-approve.
 */
class OrgEscalationTimeoutJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('org-rituals');
    }

    public function handle(OrgEscalationService $escalationService): void
    {
        // Skip if org layer is disabled
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::ORG_ENABLED)) {
            return;
        }

        $timedOut = $escalationService->checkTimeouts();

        foreach ($timedOut as $escalation) {
            $this->handleTimedOutEscalation($escalation);
        }

        if ($timedOut->isNotEmpty()) {
            Log::info('OrgEscalationTimeoutJob: Processed timed out escalations', [
                'count' => $timedOut->count(),
                'escalation_ids' => $timedOut->pluck('id')->toArray(),
            ]);
        }
    }

    /**
     * Handle a single timed-out escalation.
     */
    protected function handleTimedOutEscalation(OrgEscalation $escalation): void
    {
        // Emit event for the timed out escalation
        if (class_exists(OrgRitualEscalationTimedOut::class)) {
            event(new OrgRitualEscalationTimedOut($escalation));
        }

        Log::warning('Escalation timed out', [
            'escalation_id' => $escalation->id,
            'ritual_run_id' => $escalation->ritual_run_id,
            'type' => $escalation->escalation_type,
            'escalated_to_agent_id' => $escalation->escalated_to_agent_id,
            'timeout_at' => $escalation->timeout_at?->toIso8601String(),
        ]);
    }
}
