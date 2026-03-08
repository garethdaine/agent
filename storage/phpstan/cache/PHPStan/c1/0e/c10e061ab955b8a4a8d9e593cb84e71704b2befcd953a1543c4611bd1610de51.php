<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/Runtime/RuntimeApproval.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\Runtime\RuntimeApproval
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-688d0c9b3c6a81a86c815d7688b9c0ebe812e458ede61faed58b01bf79947ed2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\Runtime\\RuntimeApproval',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/Runtime/RuntimeApproval.php',
      ),
    ),
    'namespace' => 'App\\Models\\Runtime',
    'name' => 'App\\Models\\Runtime\\RuntimeApproval',
    'shortName' => 'RuntimeApproval',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 48,
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
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'runtime_tool_call_id\', \'requested_by\', \'state\', \'decision_by\', \'decision_reason\', \'expires_at\']',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 24,
            'startTokenPos' => 63,
            'startFilePos' => 408,
            'endTokenPos' => 83,
            'endFilePos' => 559,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 24,
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
        'startLine' => 26,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'aliasName' => NULL,
      ),
      'toolCall' => 
      array (
        'name' => 'toolCall',
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
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'aliasName' => NULL,
      ),
      'requestedByUser' => 
      array (
        'name' => 'requestedByUser',
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
        'startLine' => 39,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'aliasName' => NULL,
      ),
      'decisionByUser' => 
      array (
        'name' => 'decisionByUser',
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
        'startLine' => 44,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Runtime',
        'declaringClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'implementingClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
        'currentClassName' => 'App\\Models\\Runtime\\RuntimeApproval',
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