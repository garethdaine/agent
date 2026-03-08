<?php

declare(strict_types=1);

namespace App\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgEscalation;
use App\Models\OrgRitualRun;
use Illuminate\Support\Collection;

class OrgEscalationService
{
    public function __construct(
        private readonly OrgReportingEdgeService $reportingEdgeService,
    ) {}

    /**
     * Create a new escalation for a ritual run.
     */
    public function createEscalation(
        OrgRitualRun $run,
        string $type,
        OrgAgentProfile $escalatedTo,
        int $timeoutSeconds = 3600,
    ): OrgEscalation {
        return OrgEscalation::create([
            'ritual_run_id' => $run->id,
            'escalation_type' => $type,
            'escalated_to_agent_id' => $escalatedTo->id,
            'state' => OrgEscalation::STATE_PENDING,
            'timeout_at' => now()->addSeconds($timeoutSeconds),
        ]);
    }

    /**
     * Resolve an escalation.
     */
    public function resolve(
        OrgEscalation $escalation,
        string $resolution,
        string $resolvedBy,
        ?string $notes = null,
    ): OrgEscalation {
        $escalation->update([
            'state' => $resolution,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        return $escalation->fresh();
    }

    /**
     * Check for and process timed-out escalations.
     * Returns a collection of escalations that were marked as timed out.
     */
    public function checkTimeouts(): Collection
    {
        $timedOut = collect();

        OrgEscalation::expired()->each(function (OrgEscalation $escalation) use ($timedOut) {
            $escalation->update([
                'state' => OrgEscalation::STATE_TIMED_OUT,
            ]);
            $timedOut->push($escalation);
        });

        return $timedOut;
    }

    /**
     * Get the escalation target for an agent based on reporting edge chain.
     * Returns the direct manager of the agent, or null if no manager exists.
     */
    public function getEscalationTarget(OrgAgentProfile $agent): ?OrgAgentProfile
    {
        $edge = $agent->reportingEdge;

        return $edge?->manager;
    }

    /**
     * Get all pending escalations for a ritual run.
     */
    public function getPendingEscalations(OrgRitualRun $run): Collection
    {
        return OrgEscalation::forRun($run->id)->pending()->get();
    }

    /**
     * Get all escalations for a ritual run.
     */
    public function getEscalationsForRun(OrgRitualRun $run): Collection
    {
        return OrgEscalation::forRun($run->id)->get();
    }

    /**
     * Approve an escalation.
     */
    public function approve(
        OrgEscalation $escalation,
        string $resolvedBy,
        ?string $notes = null,
    ): OrgEscalation {
        return $this->resolve($escalation, OrgEscalation::STATE_APPROVED, $resolvedBy, $notes);
    }

    /**
     * Reject an escalation.
     */
    public function reject(
        OrgEscalation $escalation,
        string $resolvedBy,
        ?string $notes = null,
    ): OrgEscalation {
        return $this->resolve($escalation, OrgEscalation::STATE_REJECTED, $resolvedBy, $notes);
    }

    /**
     * Check if a ritual run has any pending escalations.
     */
    public function hasPendingEscalations(OrgRitualRun $run): bool
    {
        return OrgEscalation::forRun($run->id)->pending()->exists();
    }

    /**
     * Get all pending escalations for a user (across all their ritual runs).
     */
    public function getUserPendingEscalations(int|string $userId): Collection
    {
        return OrgEscalation::pending()
            ->whereHas('ritualRun', fn ($query) => $query->where('user_id', $userId))
            ->with(['ritualRun', 'escalatedToAgent'])
            ->get();
    }
}
