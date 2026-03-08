<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/DelegateeAssigner.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\DelegateeAssigner
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-2179733fe701696e72a21a22fb9528d35d3fc7db401832b5c220547afd34213e',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\DelegateeAssigner',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/DelegateeAssigner.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation',
    'name' => 'App\\Support\\Delegation\\DelegateeAssigner',
    'shortName' => 'DelegateeAssigner',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Assigns delegatee profiles to delegation tasks based on capability matching
 * and performance metrics.
 *
 * Assignment Algorithm:
 * 1. Extract required_capability from task\'s contract_json
 * 2. Query active DelegateeProfile records for the graph owner with matching capability
 * 3. Rank by 24h success_rate (descending)
 * 4. Tiebreak by current load - count of running attempts (ascending)
 *
 * When no matching profile is found, returns null. The caller should transition
 * the task to STATUS_BLOCKED for later retry by the reconciler.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 104,
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
      'assign' => 
      array (
        'name' => 'assign',
        'parameters' => 
        array (
          'task' => 
          array (
            'name' => 'task',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationTask',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 28,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'App\\Support\\Delegation\\AssignmentResult',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Find and assign the best matching delegatee profile for a task.
 *
 * @param  DelegationTask  $task  The task requiring assignment
 * @return AssignmentResult|null The assignment result or null if no matching profile found
 */',
        'startLine' => 30,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegateeAssigner',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegateeAssigner',
        'currentClassName' => 'App\\Support\\Delegation\\DelegateeAssigner',
        'aliasName' => NULL,
      ),
      'currentLoad' => 
      array (
        'name' => 'currentLoad',
        'parameters' => 
        array (
          'profile' => 
          array (
            'name' => 'profile',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegateeProfile',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 97,
            'endLine' => 97,
            'startColumn' => 34,
            'endColumn' => 58,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Count currently running attempts for a delegatee profile.
 *
 * @param  DelegateeProfile  $profile  The profile to check
 * @return int Number of running attempts
 */',
        'startLine' => 97,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegateeAssigner',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegateeAssigner',
        'currentClassName' => 'App\\Support\\Delegation\\DelegateeAssigner',
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