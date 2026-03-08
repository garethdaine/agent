<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/GraphStateTransitionService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\GraphStateTransitionService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-1f380c1f784d670d30875ab19d615be7f975e8d6ccace66dcb9a89889a4ad242',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\GraphStateTransitionService',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/GraphStateTransitionService.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation',
    'name' => 'App\\Support\\Delegation\\GraphStateTransitionService',
    'shortName' => 'GraphStateTransitionService',
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
    'endLine' => 41,
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
      'transition' => 
      array (
        'name' => 'transition',
        'parameters' => 
        array (
          'graphId' => 
          array (
            'name' => 'graphId',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 32,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fromStatuses' => 
          array (
            'name' => 'fromStatuses',
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 46,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'toStatus' => 
          array (
            'name' => 'toStatus',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 67,
            'endColumn' => 82,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 23,
                'endLine' => 23,
                'startTokenPos' => 52,
                'startFilePos' => 972,
                'endTokenPos' => 53,
                'endFilePos' => 973,
              ),
            ),
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 85,
            'endColumn' => 106,
            'parameterIndex' => 3,
            'isOptional' => true,
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
 * Atomically transition a graph from one of the allowed statuses to a new status.
 *
 * Uses a single UPDATE query with WHERE conditions to ensure atomic state transitions.
 * Only succeeds if the graph exists, is not soft-deleted, and its current status
 * matches one of the allowed from statuses.
 *
 * @param  int  $graphId  The ID of the graph to transition
 * @param  array<int, string>  $fromStatuses  Allowed current statuses (must be non-empty)
 * @param  string  $toStatus  Target status to transition to
 * @param  array<string, mixed>  $attributes  Additional attributes to update
 * @return bool True if exactly one row was updated (transition succeeded)
 */',
        'startLine' => 23,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\GraphStateTransitionService',
        'implementingClassName' => 'App\\Support\\Delegation\\GraphStateTransitionService',
        'currentClassName' => 'App\\Support\\Delegation\\GraphStateTransitionService',
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