<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Events/DelegationGraphStarted.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Events\DelegationGraphStarted
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-ce6636f0376786773eba8a1e042c03e38890d9587c25cc4d136f898cb61624e1',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Events\\DelegationGraphStarted',
        'filename' => '/Users/garethdaine/Code/agent/app/Events/DelegationGraphStarted.php',
      ),
    ),
    'namespace' => 'App\\Events',
    'name' => 'App\\Events\\DelegationGraphStarted',
    'shortName' => 'DelegationGraphStarted',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Fired when a delegation graph transitions to the running state.
 *
 * This event triggers the DelegationCoordinator to:
 * - Find all root tasks (ready tasks with no dependencies)
 * - Assign delegatees and spawn execution attempts
 * - Respect max_parallel_tasks limit
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 30,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Foundation\\Events\\Dispatchable',
      1 => 'Illuminate\\Broadcasting\\InteractsWithSockets',
      2 => 'Illuminate\\Queue\\SerializesModels',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'graph' => 
      array (
        'declaringClassName' => 'App\\Events\\DelegationGraphStarted',
        'implementingClassName' => 'App\\Events\\DelegationGraphStarted',
        'name' => 'graph',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\DelegationGraph',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 9,
        'endColumn' => 46,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
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
                'name' => 'App\\Models\\DelegationGraph',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 28,
            'endLine' => 28,
            'startColumn' => 9,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new event instance.
 *
 * @param  DelegationGraph  $graph  The graph that was started
 */',
        'startLine' => 27,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Events',
        'declaringClassName' => 'App\\Events\\DelegationGraphStarted',
        'implementingClassName' => 'App\\Events\\DelegationGraphStarted',
        'currentClassName' => 'App\\Events\\DelegationGraphStarted',
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