<?php

namespace App\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgReportingEdge;
use App\Support\Org\Exceptions\CycleDetectedException;
use App\Support\Org\Exceptions\HierarchyDepthExceededException;
use Illuminate\Support\Collection;

class OrgReportingEdgeService
{
    private const MAX_DEPTH = 3;

    /**
     * Set a manager for an org agent profile.
     *
     * @throws CycleDetectedException
     * @throws HierarchyDepthExceededException
     */
    public function setManager(OrgAgentProfile $subordinate, OrgAgentProfile $manager): OrgReportingEdge
    {
        $this->detectCycle($subordinate, $manager);
        $this->validateHierarchyDepth($subordinate, $manager);

        return OrgReportingEdge::updateOrCreate(
            ['subordinate_agent_id' => $subordinate->id],
            [
                'manager_agent_id' => $manager->id,
                'user_id' => $subordinate->user_id,
            ]
        );
    }

    /**
     * Remove a manager relationship for an org agent.
     */
    public function removeManager(OrgAgentProfile $subordinate): void
    {
        OrgReportingEdge::where('subordinate_agent_id', $subordinate->id)->delete();
    }

    /**
     * Get the escalation path for an agent (ordered list of managers up the chain).
     */
    public function getEscalationPath(OrgAgentProfile $agent): Collection
    {
        $path = collect();
        $current = $agent;

        while ($edge = $current->reportingEdge) {
            $path->push($edge->manager);
            $current = $edge->manager;
        }

        return $path;
    }

    /**
     * Detect if setting a manager would create a cycle in the hierarchy.
     *
     * @throws CycleDetectedException
     */
    private function detectCycle(OrgAgentProfile $subordinate, OrgAgentProfile $manager): void
    {
        // Check if subordinate is anywhere in the manager's escalation path
        $current = $manager;

        while ($edge = $current->reportingEdge) {
            if ($edge->manager_agent_id === $subordinate->id) {
                throw new CycleDetectedException(
                    'Setting this manager would create a cycle in the reporting hierarchy'
                );
            }
            $current = $edge->manager;
        }
    }

    /**
     * Validate that setting this manager would not exceed maximum hierarchy depth.
     *
     * Maximum depth of 3 means: agent -> manager -> senior manager
     *
     * @throws HierarchyDepthExceededException
     */
    private function validateHierarchyDepth(OrgAgentProfile $subordinate, OrgAgentProfile $manager): void
    {
        // Calculate the depth above the manager
        $managerDepth = $this->getEscalationPath($manager)->count();

        // Calculate the depth below the subordinate
        $subordinateDepth = $this->getSubordinateDepth($subordinate);

        // Total depth would be: subordinates below + subordinate + manager + managers above
        // The hierarchy depth counts levels, so we check if the total chain exceeds MAX_DEPTH
        if ($managerDepth + $subordinateDepth + 2 > self::MAX_DEPTH) {
            throw new HierarchyDepthExceededException(
                'Maximum hierarchy depth of ' . self::MAX_DEPTH . ' levels exceeded',
                self::MAX_DEPTH
            );
        }
    }

    /**
     * Get the maximum depth of subordinates below an agent.
     */
    private function getSubordinateDepth(OrgAgentProfile $agent): int
    {
        $maxDepth = 0;
        $subordinates = OrgReportingEdge::where('manager_agent_id', $agent->id)->get();

        foreach ($subordinates as $edge) {
            $depth = 1 + $this->getSubordinateDepth($edge->subordinate);
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }
}
