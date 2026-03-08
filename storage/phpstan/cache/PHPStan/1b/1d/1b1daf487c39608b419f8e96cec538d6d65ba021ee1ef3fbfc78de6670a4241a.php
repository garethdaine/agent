<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/Runtime/RuntimeToolCall.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\Runtime\RuntimeToolCall
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-26e59107e775580be16c80bae4a2cb9598497662080a1be452b9976dc0f438b0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/Runtime/RuntimeToolCall.php',
      ),
    ),
    'namespace' => 'App\\Models\\Runtime',
    'name' => 'App\\Models\\Runtime\\RuntimeToolCall',
    'shortName' => 'RuntimeToolCall',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 58,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
      1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'runtime_turn_id\', \'tool_name\', \'arguments_json\', \'result_json\', \'status\', \'duration_ms\', \'requires_approval\', \'approved_at\', \'content_trust_level\', \'injection_score\', \'injection_action\', \'content_sanitized\']',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 31,
            'startTokenPos' => 68,
            'startFilePos' => 481,
            'endTokenPos' => 106,
            'endFilePos' => 792,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 6,
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
        'startLine' => 33,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'aliasName' => NULL,
      ),
      'turn' => 
      array (
        'name' => 'turn',
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
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'aliasName' => NULL,
      ),
      'approval' => 
      array (
        'name' => 'approval',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasOne',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 54,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeToolCall',
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