<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/Contracts/EmbeddingProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\Contracts\EmbeddingProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-03d6af8d08c13f35ca88bc23cd601bbbff39ddc08ac738781e22f8454b577f07',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/Contracts/EmbeddingProvider.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory\\Contracts',
    'name' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
    'shortName' => 'EmbeddingProvider',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Contract for embedding vector generation providers.
 *
 * Embeddings convert text into high-dimensional vectors for semantic search.
 * Currently only OpenAI supports this capability (text-embedding-3-small, 1536d).
 *
 * Implementations should:
 * - Handle rate limiting gracefully with retries
 * - Return null on API errors rather than throwing
 * - Track token usage for cost estimation
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 64,
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
      'embed' => 
      array (
        'name' => 'embed',
        'parameters' => 
        array (
          'text' => 
          array (
            'name' => 'text',
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 27,
            'endColumn' => 38,
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
                  'name' => 'array',
                  'isIdentifier' => true,
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
 * Generate an embedding vector for a single text.
 *
 * @param  string  $text  The text to embed
 * @return array<float>|null Array of floats (1536d for v1) or null on error
 */',
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'aliasName' => NULL,
      ),
      'embedBatch' => 
      array (
        'name' => 'embedBatch',
        'parameters' => 
        array (
          'texts' => 
          array (
            'name' => 'texts',
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 32,
            'endColumn' => 43,
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
 * Generate embeddings for multiple texts in a single API call.
 *
 * More efficient than calling embed() multiple times.
 * Returns empty array for any text that fails.
 *
 * @param  array<string>  $texts  Array of texts to embed
 * @return array<array<float>> Array of embedding vectors (same order as input)
 */',
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'aliasName' => NULL,
      ),
      'getDimensions' => 
      array (
        'name' => 'getDimensions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the dimension count of embeddings produced by this provider.
 *
 * v1: Fixed 1536 dimensions (text-embedding-3-small)
 *
 * @return int Number of dimensions
 */',
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 41,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'aliasName' => NULL,
      ),
      'getProviderName' => 
      array (
        'name' => 'getProviderName',
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
 * Get the provider name for logging and tracking.
 *
 * @return string Provider identifier (e.g., \'openai\')
 */',
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'aliasName' => NULL,
      ),
      'supportsEmbeddings' => 
      array (
        'name' => 'supportsEmbeddings',
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
 * Check if this provider supports embedding generation.
 *
 * Always returns true for EmbeddingProvider implementations,
 * but useful for duck-typing checks.
 *
 * @return bool True if embeddings are supported
 */',
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
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