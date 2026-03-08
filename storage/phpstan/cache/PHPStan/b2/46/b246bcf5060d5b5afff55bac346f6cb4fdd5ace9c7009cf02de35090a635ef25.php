<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Org/PhaseGraphValidator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Org\PhaseGraphValidator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-d0e46e4bb95d2027c14e1c1eaf8796454cb77d976cd82c55b021d3dd29108a4e',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Org\\PhaseGraphValidator',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Org/PhaseGraphValidator.php',
      ),
    ),
    'namespace' => 'App\\Support\\Org',
    'name' => 'App\\Support\\Org\\PhaseGraphValidator',
    'shortName' => 'PhaseGraphValidator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 199,
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
      'CONDITIONAL_KEYS' => 
      array (
        'declaringClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'implementingClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'name' => 'CONDITIONAL_KEYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'condition\', \'when\', \'unless\', \'if\', \'else\', \'else_if\', \'elif\']',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 21,
            'startTokenPos' => 33,
            'startFilePos' => 327,
            'endTokenPos' => 56,
            'endFilePos' => 453,
          ),
        ),
        'docComment' => '/**
 * Keys that indicate conditional branching (not allowed in phase graphs).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'validate' => 
      array (
        'name' => 'validate',
        'parameters' => 
        array (
          'graph' => 
          array (
            'name' => 'graph',
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 30,
            'endColumn' => 41,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate a phase graph structure.
 *
 * Ensures:
 * 1. The graph is a valid DAG (no cycles) using Kahn\'s algorithm
 * 2. No conditional branching keys are present
 * 3. All dependency references are valid
 *
 * @param  array  $graph  Array of phase definitions with \'id\' and \'depends_on\' keys
 * @return bool True if valid
 *
 * @throws InvalidPhaseGraphException If the graph contains cycles or invalid references
 * @throws ConditionalBranchingNotAllowedException If conditional keys are present
 */',
        'startLine' => 37,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'implementingClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'currentClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'aliasName' => NULL,
      ),
      'rejectConditionalBranching' => 
      array (
        'name' => 'rejectConditionalBranching',
        'parameters' => 
        array (
          'graph' => 
          array (
            'name' => 'graph',
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
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 49,
            'endColumn' => 60,
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
 * Reject any phases that contain conditional branching keys.
 *
 * @throws ConditionalBranchingNotAllowedException
 */',
        'startLine' => 61,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'implementingClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'currentClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'aliasName' => NULL,
      ),
      'buildAdjacencyList' => 
      array (
        'name' => 'buildAdjacencyList',
        'parameters' => 
        array (
          'graph' => 
          array (
            'name' => 'graph',
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
            'startLine' => 83,
            'endLine' => 83,
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
 * Build adjacency list from phase graph and validate all references.
 *
 * @return array<string, array<string>> Map of phase ID to dependent phase IDs
 *
 * @throws InvalidPhaseGraphException If a dependency references a non-existent phase
 */',
        'startLine' => 83,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'implementingClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'currentClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'aliasName' => NULL,
      ),
      'validateAcyclic' => 
      array (
        'name' => 'validateAcyclic',
        'parameters' => 
        array (
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
            'startLine' => 143,
            'endLine' => 143,
            'startColumn' => 38,
            'endColumn' => 57,
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
 * Validate that the graph is acyclic using Kahn\'s algorithm.
 *
 * Kahn\'s algorithm for topological sorting:
 * 1. Find all nodes with no incoming edges (in-degree 0)
 * 2. Remove them from the graph, reducing in-degree of their neighbors
 * 3. Repeat until no nodes remain (success) or no zero-in-degree nodes exist (cycle)
 *
 * @param  array<string, array<string>>  $adjacencyList  Map of phase ID to outgoing edges
 *
 * @throws InvalidPhaseGraphException If the graph contains a cycle
 */',
        'startLine' => 143,
        'endLine' => 198,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'implementingClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
        'currentClassName' => 'App\\Support\\Org\\PhaseGraphValidator',
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