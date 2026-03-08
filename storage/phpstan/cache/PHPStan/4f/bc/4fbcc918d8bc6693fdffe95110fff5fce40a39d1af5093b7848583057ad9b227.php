<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/MemoryAdapterFactory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\MemoryAdapterFactory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-376a3a87202de2107201aa009906a237bd5337d740a8bb167c7df6a9452ef8bb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/MemoryAdapterFactory.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory',
    'name' => 'App\\Support\\Memory\\MemoryAdapterFactory',
    'shortName' => 'MemoryAdapterFactory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Adapter Factory.
 *
 * Resolves and caches provider adapters based on user settings.
 *
 * Features:
 * - Auto-detection of configured providers
 * - Failover chain for text extraction (OpenAI <-> Anthropic)
 * - Embeddings only via embedding-capable providers
 * - Request-scoped caching of resolved adapters
 *
 * Usage:
 *   $factory->makeEmbeddingProvider($userId);  // Returns OpenAIAdapter or null
 *   $factory->makeExtractionProvider($userId); // Returns configured provider
 *   $factory->getFailoverProvider($userId, \'openai\'); // Returns Anthropic
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 337,
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
      'EMBEDDING_PROVIDERS' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'name' => 'EMBEDDING_PROVIDERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'openai\']',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 53,
            'startFilePos' => 981,
            'endTokenPos' => 55,
            'endFilePos' => 990,
          ),
        ),
        'docComment' => '/**
 * Providers that support embedding generation.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
      'EXTRACTION_PROVIDERS' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'name' => 'EXTRACTION_PROVIDERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'openai\', \'anthropic\']',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 68,
            'startFilePos' => 1109,
            'endTokenPos' => 73,
            'endFilePos' => 1131,
          ),
        ),
        'docComment' => '/**
 * Providers that support text extraction/generation.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 65,
      ),
    ),
    'immediateProperties' => 
    array (
      'cache' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'name' => 'cache',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 86,
            'startFilePos' => 1296,
            'endTokenPos' => 87,
            'endFilePos' => 1297,
          ),
        ),
        'docComment' => '/**
 * Cached adapter instances per user.
 *
 * @var array<string, EmbeddingProvider|ExtractionProvider|null>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'settingsService' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'name' => 'settingsService',
        'modifiers' => 4,
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
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 9,
        'endColumn' => 54,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'capabilityResolver' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'name' => 'capabilityResolver',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 9,
        'endColumn' => 60,
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 9,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'capabilityResolver' => 
          array (
            'name' => 'capabilityResolver',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 9,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'makeEmbeddingProvider' => 
      array (
        'name' => 'makeEmbeddingProvider',
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
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 43,
            'endColumn' => 53,
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
                  'name' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
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
 * Create or retrieve an embedding provider for a user.
 *
 * Returns null if no embedding-capable provider is configured.
 *
 * @param  int  $userId  User ID
 * @return EmbeddingProvider|null Embedding provider or null
 */',
        'startLine' => 60,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'makeExtractionProvider' => 
      array (
        'name' => 'makeExtractionProvider',
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
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 44,
            'endColumn' => 54,
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
                  'name' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
 * Create or retrieve an extraction provider for a user.
 *
 * Uses the user\'s configured extraction_provider setting,
 * falling back to the first available provider.
 *
 * @param  int  $userId  User ID
 * @return ExtractionProvider|null Extraction provider or null
 */',
        'startLine' => 100,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'makeSummarizationProvider' => 
      array (
        'name' => 'makeSummarizationProvider',
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
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 47,
            'endColumn' => 57,
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
                  'name' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
 * Create or retrieve a summarization provider for a user.
 *
 * Uses the user\'s configured summarization_provider setting,
 * falling back to extraction provider.
 *
 * @param  int  $userId  User ID
 * @return ExtractionProvider|null Summarization provider or null
 */',
        'startLine' => 152,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'getFailoverProvider' => 
      array (
        'name' => 'getFailoverProvider',
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
            'startLine' => 195,
            'endLine' => 195,
            'startColumn' => 41,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'excludeProvider' => 
          array (
            'name' => 'excludeProvider',
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
            'startLine' => 195,
            'endLine' => 195,
            'startColumn' => 54,
            'endColumn' => 76,
            'parameterIndex' => 1,
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
                  'name' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
 * Get a failover provider for text extraction.
 *
 * Returns an alternative provider when the primary fails.
 * Only works between extraction-capable providers.
 *
 * @param  int  $userId  User ID
 * @param  string  $excludeProvider  Provider that failed (to exclude)
 * @return ExtractionProvider|null Failover provider or null
 */',
        'startLine' => 195,
        'endLine' => 217,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'clearCache' => 
      array (
        'name' => 'clearCache',
        'parameters' => 
        array (
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
 * Clear the adapter cache.
 *
 * Call this when user settings change.
 */',
        'startLine' => 224,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'providerSupportsEmbeddings' => 
      array (
        'name' => 'providerSupportsEmbeddings',
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
            'startLine' => 235,
            'endLine' => 235,
            'startColumn' => 48,
            'endColumn' => 63,
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
 * Check if a provider supports embeddings.
 *
 * @param  string  $provider  Provider name
 * @return bool True if provider supports embeddings
 */',
        'startLine' => 235,
        'endLine' => 238,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'providerSupportsExtraction' => 
      array (
        'name' => 'providerSupportsExtraction',
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
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 48,
            'endColumn' => 63,
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
 * Check if a provider supports text extraction.
 *
 * @param  string  $provider  Provider name
 * @return bool True if provider supports extraction
 */',
        'startLine' => 246,
        'endLine' => 249,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'createAdapter' => 
      array (
        'name' => 'createAdapter',
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
            'startLine' => 260,
            'endLine' => 260,
            'startColumn' => 36,
            'endColumn' => 46,
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
            'startLine' => 260,
            'endLine' => 260,
            'startColumn' => 49,
            'endColumn' => 64,
            'parameterIndex' => 1,
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
                  'name' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
                  'isIdentifier' => false,
                ),
              ),
              2 => 
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
 * Create an adapter instance for a provider.
 *
 * API keys are resolved from the credential manager via MemorySettingsService.
 *
 * @param  int  $userId  User ID for settings lookup
 * @param  string  $provider  Provider name
 * @return OpenAIAdapter|AnthropicAdapter|null Adapter instance or null
 */',
        'startLine' => 260,
        'endLine' => 273,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'createOpenAIAdapter' => 
      array (
        'name' => 'createOpenAIAdapter',
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
            'startLine' => 282,
            'endLine' => 282,
            'startColumn' => 42,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'apiKey' => 
          array (
            'name' => 'apiKey',
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
            'startLine' => 282,
            'endLine' => 282,
            'startColumn' => 55,
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
            'name' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create an OpenAI adapter with user\'s model settings.
 *
 * @param  int  $userId  User ID for settings lookup
 * @param  string  $apiKey  OpenAI API key
 * @return OpenAIAdapter Configured adapter
 */',
        'startLine' => 282,
        'endLine' => 308,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'aliasName' => NULL,
      ),
      'createAnthropicAdapter' => 
      array (
        'name' => 'createAnthropicAdapter',
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
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 45,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'apiKey' => 
          array (
            'name' => 'apiKey',
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
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 58,
            'endColumn' => 71,
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
            'name' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create an Anthropic adapter with user\'s model settings.
 *
 * @param  int  $userId  User ID for settings lookup
 * @param  string  $apiKey  Anthropic API key
 * @return AnthropicAdapter Configured adapter
 */',
        'startLine' => 317,
        'endLine' => 336,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
        'currentClassName' => 'App\\Support\\Memory\\MemoryAdapterFactory',
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