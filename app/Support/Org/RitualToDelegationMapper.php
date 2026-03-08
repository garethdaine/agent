<?php

declare(strict_types=1);

namespace App\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;

/**
 * Maps ritual template phase graphs to delegation task definitions.
 *
 * This class converts the phase_graph and phase_role_mappings from an
 * OrgRitualTemplate into the task format expected by DelegationGraphBuilder.
 *
 * Responsibilities:
 * - Convert phase definitions to delegation task format
 * - Resolve role_slug mappings to delegatee_profile_id
 * - Include context inputs in task contracts
 * - Preserve dependency relationships
 */
class RitualToDelegationMapper
{
    /**
     * Map ritual template phases to delegation task definitions.
     *
     * Produces an array suitable for DelegationGraphBuilder's DAG format:
     * [
     *   ['name' => 'phase_id', 'contract' => [...], 'depends_on' => [...]],
     *   ...
     * ]
     *
     * @param  OrgRitualTemplate  $template  The ritual template containing phase definitions
     * @param  OrgRitualRun  $run  The run instance (provides correlation_id)
     * @return array<int, array{id: string, name: string, depends_on: array, delegatee_profile_id: ?string, contract: array}>
     */
    public function mapPhasesToTasks(OrgRitualTemplate $template, OrgRitualRun $run): array
    {
        $phaseGraph = $template->phase_graph ?? [];
        $roleMappings = $template->phase_role_mappings ?? [];
        $contextInputs = $template->context_inputs;

        // Pre-fetch all org agents for this user by role_slug for efficient lookup
        $agentsByRole = $this->buildAgentRoleMap($template->user_id);

        $tasks = [];

        foreach ($phaseGraph as $phase) {
            $phaseId = $phase['id'];
            $phaseName = $phase['name'] ?? $phaseId;
            $dependsOn = $phase['depends_on'] ?? [];

            // Resolve the role mapping to a delegatee profile
            $roleSlug = $roleMappings[$phaseId] ?? null;
            $delegateeProfileId = $this->resolveDelegateeProfileId($roleSlug, $agentsByRole);

            $contract = $this->buildTaskContract($phase, $run, $contextInputs, $delegateeProfileId);

            $tasks[] = [
                'id' => $phaseId,
                'name' => $phaseName,
                'depends_on' => $dependsOn,
                'delegatee_profile_id' => $delegateeProfileId,
                'contract' => $contract,
            ];
        }

        return $tasks;
    }

    /**
     * Build a map of role_slug => OrgAgentProfile for efficient lookup.
     *
     * @param  int|string  $userId  The user ID to scope the query
     * @return array<string, OrgAgentProfile>
     */
    private function buildAgentRoleMap(int|string $userId): array
    {
        $agents = OrgAgentProfile::query()
            ->forUser($userId)
            ->active()
            ->get();

        $map = [];
        foreach ($agents as $agent) {
            // First agent with each role wins (could be enhanced with priority logic)
            if (! isset($map[$agent->role_slug])) {
                $map[$agent->role_slug] = $agent;
            }
        }

        return $map;
    }

    /**
     * Resolve a role_slug to its delegatee_profile_id.
     *
     * @param  ?string  $roleSlug  The role slug to look up
     * @param  array<string, OrgAgentProfile>  $agentsByRole  Pre-built role map
     * @return ?string The delegatee_profile_id or null if not found
     */
    private function resolveDelegateeProfileId(?string $roleSlug, array $agentsByRole): ?string
    {
        if ($roleSlug === null) {
            return null;
        }

        $agent = $agentsByRole[$roleSlug] ?? null;

        return $agent?->delegatee_profile_id;
    }

    /**
     * Build the task contract from phase definition and run context.
     *
     * @param  array  $phase  The phase definition from phase_graph
     * @param  OrgRitualRun  $run  The run instance
     * @param  ?array  $contextInputs  Context inputs from the template
     * @return array The task contract
     */
    private function buildTaskContract(
        array $phase,
        OrgRitualRun $run,
        ?array $contextInputs,
        ?string $delegateeProfileId = null,
    ): array {
        $contract = [
            'phase_id' => $phase['id'],
            'phase_name' => $phase['name'] ?? $phase['id'],
            'correlation_id' => $run->correlation_id,
            'ritual_run_id' => $run->id,
            'required_capability' => 'review',
        ];

        if ($delegateeProfileId !== null) {
            $contract['pre_assigned_delegatee_profile_id'] = (int) $delegateeProfileId;
        }

        if (! empty($contextInputs)) {
            $contract['context_inputs'] = $contextInputs;
        }

        $phaseConfig = $phase['config'] ?? [];
        if (isset($phase['instructions'])) {
            $phaseConfig['instructions'] = $phase['instructions'];
        }
        if (! empty($phaseConfig)) {
            $contract['phase_config'] = $phaseConfig;
        }

        return $contract;
    }
}
