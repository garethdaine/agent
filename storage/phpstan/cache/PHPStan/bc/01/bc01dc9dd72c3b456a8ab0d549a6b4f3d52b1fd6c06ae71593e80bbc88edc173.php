<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Http/Requests/Memory/UpdateMemorySettingsRequest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Requests\Memory\UpdateMemorySettingsRequest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-0b74d403a20fe00595b37c7e7a21f2c8d6acc7f923c1a8370b3a5ab3ac0d2b48',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'filename' => '/Users/garethdaine/Code/agent/app/Http/Requests/Memory/UpdateMemorySettingsRequest.php',
      ),
    ),
    'namespace' => 'App\\Http\\Requests\\Memory',
    'name' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
    'shortName' => 'UpdateMemorySettingsRequest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Form request for batch updating memory settings.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 87,
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
      'VALID_SETTING_KEYS' => 
      array (
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'name' => 'VALID_SETTING_KEYS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'memory_enabled\', \'api_features_enabled\', \'extraction_provider\', \'extraction_model\', \'summarization_provider\', \'summarization_model\', \'embeddings_provider\', \'embeddings_model\', \'embedding_dimensions\', \'context_budget_percent\', \'context_floor_tokens\', \'context_ceiling_tokens\', \'context_margin_percent\', \'embedding_decay_threshold\', \'graph_importance_threshold\', \'graph_retention_days\', \'working_memory_max_messages\', \'working_memory_ttl_seconds\', \'openai_rpm\', \'openai_tpm\', \'anthropic_rpm\', \'anthropic_tpm\', \'provider_key_openai\', \'provider_key_anthropic\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 44,
            'startTokenPos' => 42,
            'startFilePos' => 365,
            'endTokenPos' => 116,
            'endFilePos' => 1121,
          ),
        ),
        'docComment' => '/**
 * Valid setting keys that can be updated.
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 44,
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
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
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
        'startLine' => 51,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
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
            'startLine' => 62,
            'endLine' => 62,
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
 * Configure the validator to check setting keys.
 */',
        'startLine' => 62,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'aliasName' => NULL,
      ),
      'getSettings' => 
      array (
        'name' => 'getSettings',
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
        'docComment' => '/**
 * Get the validated settings array.
 *
 * @return array<string, mixed>
 */',
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Memory',
        'declaringClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
        'currentClassName' => 'App\\Http\\Requests\\Memory\\UpdateMemorySettingsRequest',
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