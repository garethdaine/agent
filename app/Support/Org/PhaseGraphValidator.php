<?php

declare(strict_types=1);

namespace App\Support\Org;

use App\Support\Org\Exceptions\ConditionalBranchingNotAllowedException;
use App\Support\Org\Exceptions\InvalidPhaseGraphException;

class PhaseGraphValidator
{
    /**
     * Keys that indicate conditional branching (not allowed in phase graphs).
     */
    private const CONDITIONAL_KEYS = [
        'condition',
        'when',
        'unless',
        'if',
        'else',
        'else_if',
        'elif',
    ];

    /**
     * Validate a phase graph structure.
     *
     * Ensures:
     * 1. The graph is a valid DAG (no cycles) using Kahn's algorithm
     * 2. No conditional branching keys are present
     * 3. All dependency references are valid
     *
     * @param  array  $graph  Array of phase definitions with 'id' and 'depends_on' keys
     * @return bool True if valid
     *
     * @throws InvalidPhaseGraphException If the graph contains cycles or invalid references
     * @throws ConditionalBranchingNotAllowedException If conditional keys are present
     */
    public function validate(array $graph): bool
    {
        // Empty graphs are valid
        if (empty($graph)) {
            return true;
        }

        // Check for conditional branching first
        $this->rejectConditionalBranching($graph);

        // Build the graph structure and validate references
        $adjacencyList = $this->buildAdjacencyList($graph);

        // Validate using Kahn's algorithm for topological sort
        $this->validateAcyclic($adjacencyList);

        return true;
    }

    /**
     * Reject any phases that contain conditional branching keys.
     *
     * @throws ConditionalBranchingNotAllowedException
     */
    private function rejectConditionalBranching(array $graph): void
    {
        foreach ($graph as $phase) {
            foreach (self::CONDITIONAL_KEYS as $key) {
                if (array_key_exists($key, $phase)) {
                    throw new ConditionalBranchingNotAllowedException(
                        message: "Phase '{$phase['id']}' contains conditional key '{$key}' which is not allowed",
                        phaseId: $phase['id'] ?? null,
                        conditionalKey: $key
                    );
                }
            }
        }
    }

    /**
     * Build adjacency list from phase graph and validate all references.
     *
     * @return array<string, array<string>> Map of phase ID to dependent phase IDs
     *
     * @throws InvalidPhaseGraphException If a dependency references a non-existent phase
     */
    private function buildAdjacencyList(array $graph): array
    {
        // Extract all valid phase IDs
        $phaseIds = [];
        foreach ($graph as $phase) {
            if (! isset($phase['id'])) {
                throw new InvalidPhaseGraphException(
                    'Phase definition missing required "id" field',
                    ['phase' => $phase]
                );
            }
            $phaseIds[$phase['id']] = true;
        }

        // Build adjacency list (edges go from dependency TO dependent)
        // For Kahn's algorithm, we track: for each node, what nodes depend on it
        $adjacencyList = [];
        foreach ($graph as $phase) {
            $phaseId = $phase['id'];
            $dependsOn = $phase['depends_on'] ?? [];

            // Initialize this node in the adjacency list
            if (! isset($adjacencyList[$phaseId])) {
                $adjacencyList[$phaseId] = [];
            }

            // For each dependency, add an edge from dependency -> this phase
            foreach ($dependsOn as $dependency) {
                if (! isset($phaseIds[$dependency])) {
                    throw new InvalidPhaseGraphException(
                        "Phase '{$phaseId}' depends on non-existent phase '{$dependency}'",
                        ['phase_id' => $phaseId, 'missing_dependency' => $dependency]
                    );
                }

                // Initialize dependency node if needed
                if (! isset($adjacencyList[$dependency])) {
                    $adjacencyList[$dependency] = [];
                }

                // Add edge: dependency -> phaseId (this phase depends on dependency)
                $adjacencyList[$dependency][] = $phaseId;
            }
        }

        return $adjacencyList;
    }

    /**
     * Validate that the graph is acyclic using Kahn's algorithm.
     *
     * Kahn's algorithm for topological sorting:
     * 1. Find all nodes with no incoming edges (in-degree 0)
     * 2. Remove them from the graph, reducing in-degree of their neighbors
     * 3. Repeat until no nodes remain (success) or no zero-in-degree nodes exist (cycle)
     *
     * @param  array<string, array<string>>  $adjacencyList  Map of phase ID to outgoing edges
     *
     * @throws InvalidPhaseGraphException If the graph contains a cycle
     */
    private function validateAcyclic(array $adjacencyList): void
    {
        // Calculate in-degree for each node
        $inDegree = [];
        foreach (array_keys($adjacencyList) as $node) {
            $inDegree[$node] = 0;
        }

        // Count incoming edges for each node
        foreach ($adjacencyList as $edges) {
            foreach ($edges as $target) {
                $inDegree[$target]++;
            }
        }

        // Initialize queue with nodes that have no dependencies (in-degree 0)
        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $processedCount = 0;
        $totalNodes = count($adjacencyList);

        // Process nodes in topological order
        while (! empty($queue)) {
            $current = array_shift($queue);
            $processedCount++;

            // Reduce in-degree of all nodes that depend on current
            foreach ($adjacencyList[$current] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // If we couldn't process all nodes, there's a cycle
        if ($processedCount !== $totalNodes) {
            // Find nodes involved in the cycle for better error reporting
            $cycleNodes = [];
            foreach ($inDegree as $node => $degree) {
                if ($degree > 0) {
                    $cycleNodes[] = $node;
                }
            }

            throw new InvalidPhaseGraphException(
                'Phase graph contains a cycle involving phases: '.implode(', ', $cycleNodes),
                ['cycle_nodes' => $cycleNodes]
            );
        }
    }
}
