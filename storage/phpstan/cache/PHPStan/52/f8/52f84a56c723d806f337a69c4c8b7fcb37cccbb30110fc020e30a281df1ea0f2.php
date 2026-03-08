<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/DelegationGraphBuilder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\DelegationGraphBuilder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-e17f4d8dba9f650dcddb4020faaf1c7d25dac3bc2f4a45e28236158d7e3d67a0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/DelegationGraphBuilder.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation',
    'name' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
    'shortName' => 'DelegationGraphBuilder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Builds a DelegationGraph with tasks and dependencies from input definitions.
 *
 * Supports two input formats:
 * 1. Linear-chain: Array of tasks with implicit sequential dependencies
 *    Example: [[\'name\' => \'task1\', \'contract\' => [...]], [\'name\' => \'task2\', \'contract\' => [...]]]
 *
 * 2. DAG (Directed Acyclic Graph): Tasks with explicit depends_on arrays
 *    Example: [\'tasks\' => [[\'name\' => \'root\', \'contract\' => [...], \'depends_on\' => []]]]
 *
 * Features:
 * - Cycle detection using Kahn\'s algorithm
 * - Maximum task limit enforcement (config: delegation.max_tasks_per_graph)
 * - Topological depth-based sequence_order assignment
 * - Root tasks (no dependencies) start as STATUS_READY
 * - Dependent tasks start as STATUS_PENDING
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 317,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'build' => 
      array (
        'name' => 'build',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\User',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 27,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'input' => 
          array (
            'name' => 'input',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 39,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\DelegationGraph',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build a new DelegationGraph from input task definitions.
 *
 * @param  User  $user  The owner of the graph
 * @param  array  $input  Task definitions (linear-chain or DAG format)
 * @return DelegationGraph The created graph with tasks loaded
 *
 * @throws DelegationGraphCycleException If the graph contains a dependency cycle
 * @throws DelegationGraphTaskLimitException If the graph exceeds the max task limit
 */',
        'startLine' => 42,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
      'normalizeInput' => 
      array (
        'name' => 'normalizeInput',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Normalize input to a consistent format: [{name, contract, depends_on}, ...].
 *
 * @param  array  $input  Raw input (linear-chain or DAG format)
 * @return array Normalized task definitions
 */',
        'startLine' => 105,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
      'validateTaskLimit' => 
      array (
        'name' => 'validateTaskLimit',
        'parameters' => 
        array (
          'tasks' => 
          array (
            'name' => 'tasks',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 40,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate that the task count doesn\'t exceed the configured limit.
 *
 * @param  array  $tasks  Normalized task definitions
 *
 * @throws DelegationGraphTaskLimitException If limit exceeded
 */',
        'startLine' => 141,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
      'buildAdjacencyList' => 
      array (
        'name' => 'buildAdjacencyList',
        'parameters' => 
        array (
          'tasks' => 
          array (
            'name' => 'tasks',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 160,
            'endLine' => 160,
            'startColumn' => 41,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build adjacency list mapping each task name to its dependent task names.
 *
 * For a task T with depends_on = [A, B], this creates edges A -> T and B -> T.
 * The adjacency list maps: A => [T, ...], B => [T, ...].
 *
 * @param  array  $tasks  Normalized task definitions
 * @return array<string, string[]> Adjacency list (task name => dependents)
 */',
        'startLine' => 160,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
      'computeInDegrees' => 
      array (
        'name' => 'computeInDegrees',
        'parameters' => 
        array (
          'tasks' => 
          array (
            'name' => 'tasks',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 188,
            'endLine' => 188,
            'startColumn' => 39,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'adjacencyList' => 
          array (
            'name' => 'adjacencyList',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 188,
            'endLine' => 188,
            'startColumn' => 53,
            'endColumn' => 72,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compute in-degrees (number of dependencies) for each task.
 *
 * @param  array  $tasks  Normalized task definitions
 * @param  array<string, string[]>  $adjacencyList  Adjacency list
 * @return array<string, int> In-degree map (task name => dependency count)
 */',
        'startLine' => 188,
        'endLine' => 205,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
      'detectCycles' => 
      array (
        'name' => 'detectCycles',
        'parameters' => 
        array (
          'tasks' => 
          array (
            'name' => 'tasks',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 222,
            'endLine' => 222,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'adjacencyList' => 
          array (
            'name' => 'adjacencyList',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 222,
            'endLine' => 222,
            'startColumn' => 49,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'inDegrees' => 
          array (
            'name' => 'inDegrees',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 222,
            'endLine' => 222,
            'startColumn' => 71,
            'endColumn' => 86,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Detect cycles using Kahn\'s algorithm for topological sorting.
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
 */',
        'startLine' => 222,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
      'computeSequenceOrders' => 
      array (
        'name' => 'computeSequenceOrders',
        'parameters' => 
        array (
          'tasks' => 
          array (
            'name' => 'tasks',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 276,
            'endLine' => 276,
            'startColumn' => 44,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'adjacencyList' => 
          array (
            'name' => 'adjacencyList',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 276,
            'endLine' => 276,
            'startColumn' => 58,
            'endColumn' => 77,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compute sequence_order for each task based on topological depth.
 *
 * Uses BFS from root nodes to assign depth levels:
 * - Root nodes (no dependencies): depth 0
 * - Other nodes: max(depth of all dependencies) + 1
 *
 * @param  array  $tasks  Normalized task definitions
 * @param  array<string, string[]>  $adjacencyList  Adjacency list
 * @return array<string, int> Sequence order map (task name => depth)
 */',
        'startLine' => 276,
        'endLine' => 316,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationGraphBuilder',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));