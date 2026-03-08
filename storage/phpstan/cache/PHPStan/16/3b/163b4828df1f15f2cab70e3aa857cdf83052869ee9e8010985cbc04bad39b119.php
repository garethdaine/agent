<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Http/Requests/Memory/UpdateCoreBlockRequest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Requests\Memory\UpdateCoreBlockRequest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-8b6b83205ce98f360084a2ce5ec98ec2bef5dd45b8d8eb4fb1a2c23d054e62cd',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'filename' => '/Users/garethdaine/Code/agent/app/Http/Requests/Memory/UpdateCoreBlockRequest.php',
      ),
    ),
    'namespace' => 'App\\Http\\Requests\\Memory',
    'name' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
    'shortName' => 'UpdateCoreBlockRequest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Form request for creating/updating core memory blocks.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 104,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Foundation\\Http\\FormRequest',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'VALID_BLOCK_KEYS' => 
      array (
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'name' => 'VALID_BLOCK_KEYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'agent_persona\', \'user_profile\', \'task_state\', \'tool_results_cache\', \'active_goals\']',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 27,
            'startTokenPos' => 52,
            'startFilePos' => 434,
            'endTokenPos' => 69,
            'endFilePos' => 565,
          ),
        ),
        'docComment' => '/**
 * Valid block keys that can be created/updated.
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'IDENTITY_BLOCK_KEYS' => 
      array (
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'name' => 'IDENTITY_BLOCK_KEYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'agent_persona\', \'user_profile\']',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 37,
            'startTokenPos' => 82,
            'startFilePos' => 703,
            'endTokenPos' => 90,
            'endFilePos' => 758,
          ),
        ),
        'docComment' => '/**
 * Identity block keys (user-only write).
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'OPERATIONAL_BLOCK_KEYS' => 
      array (
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'name' => 'OPERATIONAL_BLOCK_KEYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'task_state\', \'tool_results_cache\', \'active_goals\']',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 48,
            'startTokenPos' => 103,
            'startFilePos' => 901,
            'endTokenPos' => 114,
            'endFilePos' => 983,
          ),
        ),
        'docComment' => '/**
 * Operational block keys (agent-editable).
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'authorize' => 
      array (
        'name' => 'authorize',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 50,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'aliasName' => NULL,
      ),
      'rules' => 
      array (
        'name' => 'rules',
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
        'startLine' => 55,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'aliasName' => NULL,
      ),
      'withValidator' => 
      array (
        'name' => 'withValidator',
        'parameters' => 
        array (
          'validator' => 
          array (
            'name' => 'validator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 35,
            'endColumn' => 44,
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
 * Configure the validator to check block key validity.
 */',
        'startLine' => 71,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'aliasName' => NULL,
      ),
      'getBlockType' => 
      array (
        'name' => 'getBlockType',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine block type from key.
 */',
        'startLine' => 88,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'aliasName' => NULL,
      ),
      'isIdentityBlock' => 
      array (
        'name' => 'isIdentityBlock',
        'parameters' => 
        array (
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
 * Check if this is an identity block.
 */',
        'startLine' => 100,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateCoreBlockRequest',
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