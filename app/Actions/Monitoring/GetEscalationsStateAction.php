<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentJobRun;
use App\Models\OrgEscalation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class GetEscalationsStateAction
{
    /**
     * @return array{open_incidents: int, pending_org_escalations: int, recent_items: array<int, array<string, mixed>>}
     */
    public function execute(User $user): array
    {
        $pendingOrg = 0;
        $recentItems = [];

        if (Schema::hasTable('org_escalations')) {
            $pendingOrg = OrgEscalation::query()
                ->where('state', OrgEscalation::STATE_PENDING)
                ->count();

            $recentItems = OrgEscalation::query()
                ->with('escalatedToAgent:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (OrgEscalation $e) => [
                    'id' => $e->id,
                    'type' => $e->escalation_type,
                    'state' => $e->state,
                    'agent' => $e->escalatedToAgent?->name,
                    'timestamp' => $e->created_at?->toIso8601String(),
                ])->toArray();
        }

        $openIncidents = 0;
        if (Schema::hasTable('agent_projection.escalation_incidents')) {
            try {
                $openIncidents = \App\Models\EscalationIncident::query()
                    ->whereNull('resolved_at')
                    ->count();
            } catch (\Throwable) {
                $openIncidents = 0;
            }
        }

        $runEscalations = AgentJobRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [AgentJobRun::STATUS_RUNNING, AgentJobRun::STATUS_FAILED])
            ->with('job:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->filter(function (AgentJobRun $run) {
                $meta = is_array($run->metadata_json) ? $run->metadata_json : [];

                return ($meta['rate_limit_detected'] ?? false) === true
                    || ($meta['approval_required'] ?? false) === true
                    || ($meta['permission_blocker_detected'] ?? false) === true
                    || ($meta['clarification_required'] ?? false) === true;
            })
            ->take(5)
            ->map(function (AgentJobRun $run) {
                $meta = is_array($run->metadata_json) ? $run->metadata_json : [];
                $type = match (true) {
                    ($meta['rate_limit_detected'] ?? false) === true => 'rate_limit',
                    ($meta['approval_required'] ?? false) === true => 'approval_required',
                    ($meta['permission_blocker_detected'] ?? false) === true => 'permission_blocked',
                    ($meta['clarification_required'] ?? false) === true => 'clarification_required',
                    default => 'unknown',
                };

                return [
                    'id' => 'run-'.$run->id,
                    'type' => $type,
                    'state' => $run->status === AgentJobRun::STATUS_RUNNING ? 'active' : 'resolved',
                    'agent' => $run->job?->name,
                    'timestamp' => $run->updated_at?->toIso8601String(),
                    'run_id' => $run->id,
                ];
            })
            ->values()
            ->toArray();

        $allItems = array_merge($recentItems, $runEscalations);
        usort($allItems, fn ($a, $b) => ($b['timestamp'] ?? '') <=> ($a['timestamp'] ?? ''));
        $allItems = array_slice($allItems, 0, 10);

        $runIncidents = count(array_filter($runEscalations, fn ($e) => $e['state'] === 'active'));

        return [
            'open_incidents' => $openIncidents + $runIncidents,
            'pending_org_escalations' => $pendingOrg,
            'recent_items' => $allItems,
        ];
    }
}
