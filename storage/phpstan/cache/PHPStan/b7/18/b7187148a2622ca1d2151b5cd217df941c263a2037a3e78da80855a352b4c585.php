<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Events/DelegationAttemptCompleted.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Events\DelegationAttemptCompleted
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-5c3b4f782043f5018f6f538e9afa37568654bfe099c6fc3f329c70da865e1601',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Events\\DelegationAttemptCompleted',
        'filename' => '/Users/garethdaine/Code/agent/app/Events/DelegationAttemptCompleted.php',
      ),
    ),
    'namespace' => 'App\\Events',
    'name' => 'App\\Events\\DelegationAttemptCompleted',
    'shortName' => 'DelegationAttemptCompleted',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Fired when a delegation attempt completes (succeeded or failed).
 *
 * This event is consumed by:
 * - DelegationCoordinator: handles successful attempts, triggers verification
 * - DelegationRecoveryHandler: handles failed attempts, implements retry/re-delegate/escalate chain
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 29,
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
      'attempt' => 
      array (
        'declaringClassName' => 'App\\Events\\DelegationAttemptCompleted',
        'implementingClassName' => 'App\\Events\\DelegationAttemptCompleted',
        'name' => 'attempt',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\DelegationAttempt',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 9,
        'endColumn' => 50,
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
          'attempt' => 
          array (
            'name' => 'attempt',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationAttempt',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 9,
            'endColumn' => 50,
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
 * @param  DelegationAttempt  $attempt  The attempt that completed
 */',
        'startLine' => 26,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Events',
        'declaringClassName' => 'App\\Events\\DelegationAttemptCompleted',
        'implementingClassName' => 'App\\Events\\DelegationAttemptCompleted',
        'currentClassName' => 'App\\Events\\DelegationAttemptCompleted',
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