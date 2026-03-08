<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/DelegationTask.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\DelegationTask
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-b03c89c6269e39888367a460349457e239ea0d53a3c079af0f63a70102adb4e2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\DelegationTask',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/DelegationTask.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\DelegationTask',
    'shortName' => 'DelegationTask',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin Builder
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 96,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
    ),
    'immediateConstants' => 
    array (
      'STATUS_PENDING' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'pending\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 72,
            'startFilePos' => 480,
            'endTokenPos' => 72,
            'endFilePos' => 488,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'STATUS_BLOCKED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_BLOCKED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'blocked\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 83,
            'startFilePos' => 526,
            'endTokenPos' => 83,
            'endFilePos' => 534,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'STATUS_READY' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_READY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ready\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 94,
            'startFilePos' => 570,
            'endTokenPos' => 94,
            'endFilePos' => 576,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'STATUS_ASSIGNED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_ASSIGNED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'assigned\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 105,
            'startFilePos' => 615,
            'endTokenPos' => 105,
            'endFilePos' => 624,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 46,
      ),
      'STATUS_RUNNING' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_RUNNING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'running\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 116,
            'startFilePos' => 662,
            'endTokenPos' => 116,
            'endFilePos' => 670,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'STATUS_VERIFYING' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_VERIFYING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'verifying\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 127,
            'startFilePos' => 710,
            'endTokenPos' => 127,
            'endFilePos' => 720,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'STATUS_SUCCEEDED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_SUCCEEDED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'succeeded\'',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 138,
            'startFilePos' => 760,
            'endTokenPos' => 138,
            'endFilePos' => 770,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'STATUS_FAILED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_FAILED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'failed\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 149,
            'startFilePos' => 807,
            'endTokenPos' => 149,
            'endFilePos' => 814,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'STATUS_CANCELLED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'STATUS_CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'cancelled\'',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 160,
            'startFilePos' => 854,
            'endTokenPos' => 160,
            'endFilePos' => 864,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
    ),
    'immediateProperties' => 
    array (
      'guarded' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'name' => 'guarded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 60,
            'startFilePos' => 441,
            'endTokenPos' => 61,
            'endFilePos' => 442,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'casts' => 
      array (
        'name' => 'casts',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 39,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
        'aliasName' => NULL,
      ),
      'graph' => 
      array (
        'name' => 'graph',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
        'aliasName' => NULL,
      ),
      'attempts' => 
      array (
        'name' => 'attempts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
        'aliasName' => NULL,
      ),
      'verificationResults' => 
      array (
        'name' => 'verificationResults',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
        'aliasName' => NULL,
      ),
      'assignedProfile' => 
      array (
        'name' => 'assignedProfile',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 66,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
        'aliasName' => NULL,
      ),
      'dependencies' => 
      array (
        'name' => 'dependencies',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Tasks that this task depends on (must complete before this task can start).
 */',
        'startLine' => 74,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
        'aliasName' => NULL,
      ),
      'dependents' => 
      array (
        'name' => 'dependents',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Tasks that depend on this task (cannot start until this task completes).
 */',
        'startLine' => 87,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationTask',
        'implementingClassName' => 'App\\Models\\DelegationTask',
        'currentClassName' => 'App\\Models\\DelegationTask',
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