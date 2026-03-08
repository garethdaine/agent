<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Exceptions\DelegationGraphCycleException;
use App\Exceptions\DelegationGraphTaskLimitException;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationTaskDependency;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds a DelegationGraph with tasks and dependencies from input definitions.
 *
 * Supports two input formats:
 * 1. Linear-chain: Array of tasks with implicit sequential dependencies
 *    Example: [['name' => 'task1', 'contract' => [...]], ['name' => 'task2', 'contract' => [...]]]
 *
 * 2. DAG (Directed Acyclic Graph): Tasks with explicit depends_on arrays
 *    Example: ['tasks' => [['name' => 'root', 'contract' => [...], 'depends_on' => []]]]
 *
 * Features:
 * - Cycle detection using Kahn's algorithm
 * - Maximum task limit enforcement (config: delegation.max_tasks_per_graph)
 * - Topological depth-based sequence_order assignment
 * - Root tasks (no dependencies) start as STATUS_READY
 * - Dependent tasks start as STATUS_PENDING
 */
class DelegationGraphBuilder
{
    /**
     * Build a new DelegationGraph from input task definitions.
     *
     * @param  User  $user  The owner of the graph
     * @param  array  $input  Task definitions (linear-chain or DAG format)
     * @return DelegationGraph The created graph with tasks loaded
     *
     * @throws DelegationGraphCycleException If the graph contains a dependency cycle
     * @throws DelegationGraphTaskLimitException If the graph exceeds the max task limit
     */
    public function build(User $user, array $input): DelegationGraph
    {
        $tasks = $this->normalizeInput($input);

        $this->validateTaskLimit($tasks);

        // Build adjacency structures for graph analysis
        $adjacencyList = $this->buildAdjacencyList($tasks);
        $inDegrees = $this->computeInDegrees($tasks, $adjacencyList);

        $this->detectCycles($tasks, $adjacencyList, $inDegrees);

        $sequenceOrders = $this->computeSequenceOrders($tasks, $adjacencyList);

        return DB::transaction(function () use ($user, $tasks, $sequenceOrders) {
            // Generate a default name from the first task or timestamp
            $graphName = ! empty($tasks)
                ? 'Graph: '.$tasks[0]['name']
                : 'Graph: '.now()->format('Y-m-d H:i:s');

            $graph = DelegationGraph::create([
                'user_id' => $user->id,
                'name' => $graphName,
                'status' => DelegationGraph::STATUS_DRAFT,
                'max_parallel_tasks' => config('delegation.default_max_parallel_tasks', 5),
            ]);

            $taskModels = [];

            // Create all task records
            foreach ($tasks as $taskData) {
                $isRoot = empty($taskData['depends_on'] ?? []);
                $taskModels[$taskData['name']] = DelegationTask::create([
                    'delegation_graph_id' => $graph->id,
                    'name' => $taskData['name'],
                    'contract_json' => $taskData['contract'] ?? [],
                    'sequence_order' => $sequenceOrders[$taskData['name']],
                    'status' => $isRoot ? DelegationTask::STATUS_READY : DelegationTask::STATUS_PENDING,
                ]);
            }

            // Create dependency records
            foreach ($tasks as $taskData) {
                foreach ($taskData['depends_on'] ?? [] as $depName) {
                    if (isset($taskModels[$depName])) {
                        DelegationTaskDependency::create([
                            'task_id' => $taskModels[$taskData['name']]->id,
                            'depends_on_task_id' => $taskModels[$depName]->id,
                        ]);
                    }
                }
            }

            return $graph->load('tasks');
        });
    }

    /**
     * Normalize input to a consistent format: [{name, contract, depends_on}, ...].
     *
     * @param  array  $input  Raw input (linear-chain or DAG format)
     * @return array Normalized task definitions
     */
    private function normalizeInput(array $input): array
    {
        // DAG format: has 'tasks' key with explicit dependencies
        if (isset($input['tasks'])) {
            return array_map(function ($task) {
                return [
                    'name' => $task['name'],
                    'contract' => $task['contract'] ?? [],
                    'depends_on' => $task['depends_on'] ?? [],
                ];
            }, $input['tasks']);
        }

        // Linear-chain format: sequential dependencies implied by order
        $normalized = [];
        $prevName = null;

        foreach ($input as $task) {
            $normalized[] = [
                'name' => $task['name'],
                'contract' => $task['contract'] ?? [],
                'depends_on' => $prevName !== null ? [$prevName] : [],
            ];
            $prevName = $task['name'];
        }

        return $normalized;
    }

    /**
     * Validate that the task count doesn't exceed the configured limit.
     *
     * @param  array  $tasks  Normalized task definitions
     *
     * @throws DelegationGraphTaskLimitException If limit exceeded
     */
    private function validateTaskLimit(array $tasks): void
    {
        $maxTasks = (int) config('delegation.max_tasks_per_graph', 25);
        $taskCount = count($tasks);

        if ($taskCount > $maxTasks) {
            throw DelegationGraphTaskLimitException::exceedsLimit($taskCount, $maxTasks);
        }
    }

    /**
     * Build adjacency list mapping each task name to its dependent task names.
     *
     * For a task T with depends_on = [A, B], this creates edges A -> T and B -> T.
     * The adjacency list maps: A => [T, ...], B => [T, ...].
     *
     * @param  array  $tasks  Normalized task definitions
     * @return array<string, string[]> Adjacency list (task name => dependents)
     */
    private function buildAdjacencyList(array $tasks): array
    {
        $adjacencyList = [];

        // Initialize empty adjacency lists for all tasks
        foreach ($tasks as $task) {
            $adjacencyList[$task['name']] = [];
        }

        // Build edges: dependency -> dependent
        foreach ($tasks as $task) {
            foreach ($task['depends_on'] ?? [] as $depName) {
                if (isset($adjacencyList[$depName])) {
                    $adjacencyList[$depName][] = $task['name'];
                }
            }
        }

        return $adjacencyList;
    }

    /**
     * Compute in-degrees (number of dependencies) for each task.
     *
     * @param  array  $tasks  Normalized task definitions
     * @param  array<string, string[]>  $adjacencyList  Adjacency list
     * @return array<string, int> In-degree map (task name => dependency count)
     */
    private function computeInDegrees(array $tasks, array $adjacencyList): array
    {
        $inDegrees = [];

        // Initialize all in-degrees to 0
        foreach ($tasks as $task) {
            $inDegrees[$task['name']] = 0;
        }

        // Count incoming edges from adjacency list
        foreach ($adjacencyList as $dependents) {
            foreach ($dependents as $dependent) {
                $inDegrees[$dependent]++;
            }
        }

        return $inDegrees;
    }

    /**
     * Detect cycles using Kahn's algorithm for topological sorting.
     *
     * Algorithm:
     * 1. Start with all nodes having in-degree 0 (roots)
     * 2. Process each root: remove it, decrement in-degrees of its dependents
     * 3. Add newly zero-degree nodes to processing queue
     * 4. If processed count < total nodes, a cycle exists
     *
     * @param  array  $tasks  Normalized task definitions
     * @param  array<string, string[]>  $adjacencyList  Adjacency list
     * @param  array<string, int>  $inDegrees  In-degree map
     *
     * @throws DelegationGraphCycleException If cycle detected
     */
    private function detectCycles(array $tasks, array $adjacencyList, array $inDegrees): void
    {
        $totalNodes = count($tasks);
        $processedCount = 0;

        // Queue of nodes with no remaining dependencies (ready to process)
        $queue = [];

        // Start with all root nodes (in-degree = 0)
        foreach ($inDegrees as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }

        // Process nodes in topological order
        while (! empty($queue)) {
            $current = array_shift($queue);
            $processedCount++;

            // For each dependent of the current node
            foreach ($adjacencyList[$current] ?? [] as $dependent) {
                $inDegrees[$dependent]--;

                // If all dependencies satisfied, add to queue
                if ($inDegrees[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // If we couldn't process all nodes, a cycle exists
        if ($processedCount < $totalNodes) {
            // Find nodes that are part of the cycle (still have non-zero in-degree)
            $cycleNodes = array_keys(array_filter($inDegrees, fn ($degree) => $degree > 0));

            throw new DelegationGraphCycleException(
                message: 'Delegation graph contains a dependency cycle involving: '.implode(', ', $cycleNodes),
                cyclePath: $cycleNodes,
            );
        }
    }

    /**
     * Compute sequence_order for each task based on topological depth.
     *
     * Uses BFS from root nodes to assign depth levels:
     * - Root nodes (no dependencies): depth 0
     * - Other nodes: max(depth of all dependencies) + 1
     *
     * @param  array  $tasks  Normalized task definitions
     * @param  array<string, string[]>  $adjacencyList  Adjacency list
     * @return array<string, int> Sequence order map (task name => depth)
     */
    private function computeSequenceOrders(array $tasks, array $adjacencyList): array
    {
        $orders = [];
        $dependencyMap = [];

        // Build reverse dependency map (task -> its dependencies)
        foreach ($tasks as $task) {
            $orders[$task['name']] = 0;
            $dependencyMap[$task['name']] = $task['depends_on'] ?? [];
        }

        // For each task, compute depth as max depth of dependencies + 1
        // We need to iterate until no changes (handles multi-level dependencies)
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($tasks as $task) {
                $name = $task['name'];
                $dependencies = $dependencyMap[$name];

                if (empty($dependencies)) {
                    continue;
                }

                // Depth is 1 + maximum depth of all dependencies
                $maxDepDepth = 0;
                foreach ($dependencies as $depName) {
                    if (isset($orders[$depName]) && $orders[$depName] >= $maxDepDepth) {
                        $maxDepDepth = $orders[$depName] + 1;
                    }
                }

                if ($maxDepDepth > $orders[$name]) {
                    $orders[$name] = $maxDepDepth;
                    $changed = true;
                }
            }
        }

        return $orders;
    }
}
