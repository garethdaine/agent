<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/MemoryCapabilityResolver.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\MemoryCapabilityResolver
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-b78da9b002c2ab6af504c7a02c889ea3d6bbf372d20dbf89f35a41f5f03f348c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/MemoryCapabilityResolver.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory',
    'name' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
    'shortName' => 'MemoryCapabilityResolver',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Capability Resolver.
 *
 * Determines the operating mode and available capabilities based on:
 * - Memory system enabled state (via FeatureFlagManager)
 * - API features enabled state (via FeatureFlagManager)
 * - Configured provider API keys
 * - Infrastructure availability (pgvector, Neo4j)
 *
 * Operating Modes:
 * - \'no-api\': No provider keys or API disabled. Core Memory + Working Memory + BM25 only.
 * - \'api\': OpenAI key configured. Full pipeline with embeddings, extraction, graph.
 * - \'degraded\': Anthropic only. Text extraction enabled but no embeddings (BM25 + graph).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 255,
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
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'name' => 'EMBEDDING_PROVIDERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'openai\']',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 38,
            'startFilePos' => 883,
            'endTokenPos' => 40,
            'endFilePos' => 892,
          ),
        ),
        'docComment' => '/**
 * Providers that support embedding generation.
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
      'EXTRACTION_PROVIDERS' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'name' => 'EXTRACTION_PROVIDERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'openai\', \'anthropic\']',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 53,
            'startFilePos' => 1044,
            'endTokenPos' => 58,
            'endFilePos' => 1066,
          ),
        ),
        'docComment' => '/**
 * Providers that support text extraction/generation.
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 65,
      ),
    ),
    'immediateProperties' => 
    array (
      'settingsService' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
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
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 9,
        'endColumn' => 54,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'featureFlags' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'name' => 'featureFlags',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Agent\\FeatureFlagManager',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 48,
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
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 9,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'featureFlags' => 
          array (
            'name' => 'featureFlags',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Agent\\FeatureFlagManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 9,
            'endColumn' => 48,
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
        'startLine' => 39,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'getOperatingMode' => 
      array (
        'name' => 'getOperatingMode',
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
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 38,
            'endColumn' => 48,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the operating mode for a user.
 *
 * @param  int  $userId  User ID to check
 * @return string Operating mode: \'no-api\', \'api\', or \'degraded\'
 */',
        'startLine' => 50,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'getCapabilities' => 
      array (
        'name' => 'getCapabilities',
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
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 37,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get full capability set for a user.
 *
 * @param  int  $userId  User ID to check
 * @return array<string, mixed> Capabilities map
 */',
        'startLine' => 85,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'hasEmbeddingCapability' => 
      array (
        'name' => 'hasEmbeddingCapability',
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
            'startLine' => 120,
            'endLine' => 120,
            'startColumn' => 44,
            'endColumn' => 54,
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
 * Check if user has embedding capability.
 *
 * @param  int  $userId  User ID to check
 * @return bool True if embeddings are available
 */',
        'startLine' => 120,
        'endLine' => 128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'hasExtractionCapability' => 
      array (
        'name' => 'hasExtractionCapability',
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
            'startLine' => 136,
            'endLine' => 136,
            'startColumn' => 45,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if user has extraction capability.
 *
 * @param  int  $userId  User ID to check
 * @return bool True if extraction is available
 */',
        'startLine' => 136,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'getAvailableProviders' => 
      array (
        'name' => 'getAvailableProviders',
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
            'startColumn' => 43,
            'endColumn' => 53,
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
 * Get all configured providers for a user.
 *
 * @param  int  $userId  User ID to check
 * @return array<string> List of configured provider names
 */',
        'startLine' => 152,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'getEmbeddingProviders' => 
      array (
        'name' => 'getEmbeddingProviders',
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
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 43,
            'endColumn' => 53,
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
 * Get providers that support embeddings.
 *
 * @param  int  $userId  User ID to check
 * @return array<string> List of embedding-capable providers
 */',
        'startLine' => 163,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'getExtractionProviders' => 
      array (
        'name' => 'getExtractionProviders',
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
            'startLine' => 176,
            'endLine' => 176,
            'startColumn' => 44,
            'endColumn' => 54,
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
 * Get providers that support text extraction.
 *
 * @param  int  $userId  User ID to check
 * @return array<string> List of extraction-capable providers
 */',
        'startLine' => 176,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'isPgVectorAvailable' => 
      array (
        'name' => 'isPgVectorAvailable',
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
 * Check if pgvector extension is available.
 *
 * Queries PostgreSQL to check for the vector extension.
 * Result is cached for the request lifecycle.
 *
 * @return bool True if pgvector is available
 */',
        'startLine' => 191,
        'endLine' => 207,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'isNeo4jAvailable' => 
      array (
        'name' => 'isNeo4jAvailable',
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
 * Check if Neo4j is available.
 *
 * Attempts a lightweight connection test.
 * Result is cached for the request lifecycle.
 *
 * @return bool True if Neo4j is reachable
 */',
        'startLine' => 217,
        'endLine' => 243,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'aliasName' => NULL,
      ),
      'resetCache' => 
      array (
        'name' => 'resetCache',
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
 * Reset cached infrastructure checks.
 *
 * Useful for testing or after configuration changes.
 */',
        'startLine' => 250,
        'endLine' => 254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'implementingClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
        'currentClassName' => 'App\\Support\\Memory\\MemoryCapabilityResolver',
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