<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/Memory/MemoryModelsController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\Api\V1\Memory\MemoryModelsController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-c81cf32d8c405a36b16d8ce2b81f94198247d68f4af49c0a158e1c9cab96f6f1',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'filename' => '/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/Memory/MemoryModelsController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
    'name' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
    'shortName' => 'MemoryModelsController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory models API controller.
 *
 * Returns available models per provider for the memory system.
 * Fetches from provider APIs when keys are configured, with
 * config-based fallbacks when APIs are unreachable.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 190,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\Controller',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OPENAI_EXTRACTION_PREFIXES' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'name' => 'OPENAI_EXTRACTION_PREFIXES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'gpt-4\', \'gpt-3.5\', \'o1\', \'o3\', \'o4\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 72,
            'startFilePos' => 731,
            'endTokenPos' => 86,
            'endFilePos' => 768,
          ),
        ),
        'docComment' => '/**
 * OpenAI model prefixes relevant to each capability.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 86,
      ),
      'OPENAI_SUMMARIZATION_PREFIXES' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'name' => 'OPENAI_SUMMARIZATION_PREFIXES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'gpt-4\', \'gpt-3.5\', \'o1\', \'o3\', \'o4\']',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 97,
            'startFilePos' => 822,
            'endTokenPos' => 111,
            'endFilePos' => 859,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 89,
      ),
      'OPENAI_EMBEDDING_PREFIXES' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'name' => 'OPENAI_EMBEDDING_PREFIXES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'text-embedding\']',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 122,
            'startFilePos' => 909,
            'endTokenPos' => 124,
            'endFilePos' => 926,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 65,
      ),
      'OPENAI_EXCLUDE_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'name' => 'OPENAI_EXCLUDE_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'instruct\', \'realtime\', \'audio\', \'tts\', \'whisper\', \'dall-e\', \'davinci\', \'babbage\', \'search\', \':ft-\', \'ft:\']',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 48,
            'startTokenPos' => 137,
            'startFilePos' => 1057,
            'endTokenPos' => 172,
            'endFilePos' => 1259,
          ),
        ),
        'docComment' => '/**
 * Models to exclude (deprecated, internal, fine-tuned, etc.).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'CACHE_TTL_SECONDS' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'name' => 'CACHE_TTL_SECONDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '300',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 183,
            'startFilePos' => 1301,
            'endTokenPos' => 183,
            'endFilePos' => 1303,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
    ),
    'immediateProperties' => 
    array (
      'settingsService' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'name' => 'settingsService',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Memory\\MemorySettingsService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 9,
        'endColumn' => 63,
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
          'settingsService' => 
          array (
            'name' => 'settingsService',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Memory\\MemorySettingsService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 9,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 52,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      '__invoke' => 
      array (
        'name' => '__invoke',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
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
            'startColumn' => 30,
            'endColumn' => 45,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * GET /memory/models
 *
 * Returns available models grouped by provider and capability.
 */',
        'startLine' => 61,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      'fetchModelsForProvider' => 
      array (
        'name' => 'fetchModelsForProvider',
        'parameters' => 
        array (
          'userId' => 
          array (
            'name' => 'userId',
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
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 45,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'provider' => 
          array (
            'name' => 'provider',
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
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 58,
            'endColumn' => 73,
            'parameterIndex' => 1,
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
 * Fetch available models from a provider API.
 *
 * Falls back to config-defined known models on failure.
 */',
        'startLine' => 87,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      'fetchOpenAIModels' => 
      array (
        'name' => 'fetchOpenAIModels',
        'parameters' => 
        array (
          'userId' => 
          array (
            'name' => 'userId',
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 40,
            'endColumn' => 50,
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
 * Fetch models from OpenAI /v1/models endpoint.
 */',
        'startLine' => 99,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      'getAnthropicModels' => 
      array (
        'name' => 'getAnthropicModels',
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
 * Get Anthropic models from config.
 *
 * Anthropic doesn\'t have a public /models list endpoint,
 * so we use a curated list from config.
 */',
        'startLine' => 147,
        'endLine' => 150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      'getFallbackModels' => 
      array (
        'name' => 'getFallbackModels',
        'parameters' => 
        array (
          'provider' => 
          array (
            'name' => 'provider',
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
            'startLine' => 155,
            'endLine' => 155,
            'startColumn' => 40,
            'endColumn' => 55,
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
 * Get fallback models from config.
 */',
        'startLine' => 155,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      'filterByPrefixes' => 
      array (
        'name' => 'filterByPrefixes',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
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
            'startLine' => 167,
            'endLine' => 167,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prefixes' => 
          array (
            'name' => 'prefixes',
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
            'startLine' => 167,
            'endLine' => 167,
            'startColumn' => 54,
            'endColumn' => 68,
            'parameterIndex' => 1,
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
 * Filter model IDs by matching prefixes.
 */',
        'startLine' => 167,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'aliasName' => NULL,
      ),
      'isExcludedModel' => 
      array (
        'name' => 'isExcludedModel',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
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
            'startLine' => 180,
            'endLine' => 180,
            'startColumn' => 38,
            'endColumn' => 47,
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
 * Check if a model should be excluded.
 */',
        'startLine' => 180,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryModelsController',
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