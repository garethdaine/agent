<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/Adapters/OpenAIAdapter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\Adapters\OpenAIAdapter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-da8da87fbe2d44308ad1f21471b8230658de79de7e71fd0d2b30d74143313996',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/Adapters/OpenAIAdapter.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory\\Adapters',
    'name' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
    'shortName' => 'OpenAIAdapter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * OpenAI adapter implementing both embedding and extraction capabilities.
 *
 * Provides:
 * - Embeddings via text-embedding-3-small (1536 dimensions)
 * - Entity extraction via gpt-4o-mini with structured output
 * - Importance scoring via gpt-4o-mini
 * - Summarization via gpt-4.1-nano
 *
 * All API calls use Guzzle HTTP client with retry handling.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 362,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Memory\\Adapters\\GuzzleHttpAdapter',
    'implementsClassNames' => 
    array (
      0 => 'App\\Support\\Memory\\Contracts\\EmbeddingProvider',
      1 => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'EMBEDDING_DIMENSIONS' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'name' => 'EMBEDDING_DIMENSIONS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1536',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 59,
            'startFilePos' => 787,
            'endTokenPos' => 59,
            'endFilePos' => 790,
          ),
        ),
        'docComment' => '/**
 * Embedding dimensions for text-embedding-3-small.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 46,
      ),
    ),
    'immediateProperties' => 
    array (
      'embeddingsModel' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'name' => 'embeddingsModel',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Model for embeddings.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'extractionModel' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'name' => 'extractionModel',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Model for extraction operations.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'summarizationModel' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'name' => 'summarizationModel',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Model for summarization operations.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 39,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
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
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 9,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extractionModel' => 
          array (
            'name' => 'extractionModel',
            'default' => 
            array (
              'code' => '\'gpt-4o-mini\'',
              'attributes' => 
              array (
                'startLine' => 54,
                'endLine' => 54,
                'startTokenPos' => 109,
                'startFilePos' => 1550,
                'endTokenPos' => 109,
                'endFilePos' => 1562,
              ),
            ),
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
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 9,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'summarizationModel' => 
          array (
            'name' => 'summarizationModel',
            'default' => 
            array (
              'code' => '\'gpt-4.1-nano\'',
              'attributes' => 
              array (
                'startLine' => 55,
                'endLine' => 55,
                'startTokenPos' => 118,
                'startFilePos' => 1602,
                'endTokenPos' => 118,
                'endFilePos' => 1615,
              ),
            ),
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
            'startLine' => 55,
            'endLine' => 55,
            'startColumn' => 9,
            'endColumn' => 51,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'embeddingsModel' => 
          array (
            'name' => 'embeddingsModel',
            'default' => 
            array (
              'code' => '\'text-embedding-3-small\'',
              'attributes' => 
              array (
                'startLine' => 56,
                'endLine' => 56,
                'startTokenPos' => 127,
                'startFilePos' => 1652,
                'endTokenPos' => 127,
                'endFilePos' => 1675,
              ),
            ),
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
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 9,
            'endColumn' => 58,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new OpenAI adapter instance.
 *
 * @param  string  $apiKey  OpenAI API key
 * @param  string  $extractionModel  Model for extraction (default: gpt-4o-mini)
 * @param  string  $summarizationModel  Model for summarization (default: gpt-4.1-nano)
 * @param  string  $embeddingsModel  Model for embeddings (default: text-embedding-3-small)
 */',
        'startLine' => 52,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
      'getDefaultHeaders' => 
      array (
        'name' => 'getDefaultHeaders',
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
 * {@inheritdoc}
 */',
        'startLine' => 71,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
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
 * {@inheritdoc}
 */',
        'startLine' => 82,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
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
            'startLine' => 94,
            'endLine' => 94,
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
 * {@inheritdoc}
 */',
        'startLine' => 94,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
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
            'startLine' => 116,
            'endLine' => 116,
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
 * {@inheritdoc}
 */',
        'startLine' => 116,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
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
 * {@inheritdoc}
 */',
        'startLine' => 148,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
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
 * {@inheritdoc}
 */',
        'startLine' => 156,
        'endLine' => 159,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
      'extractEntities' => 
      array (
        'name' => 'extractEntities',
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
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 37,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * {@inheritdoc}
 */',
        'startLine' => 168,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
      'scoreImportance' => 
      array (
        'name' => 'scoreImportance',
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
            'startLine' => 214,
            'endLine' => 214,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entities' => 
          array (
            'name' => 'entities',
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
            'startLine' => 214,
            'endLine' => 214,
            'startColumn' => 51,
            'endColumn' => 65,
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
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * {@inheritdoc}
 */',
        'startLine' => 214,
        'endLine' => 266,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
      'summarize' => 
      array (
        'name' => 'summarize',
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
            'startLine' => 271,
            'endLine' => 271,
            'startColumn' => 31,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'maxTokens' => 
          array (
            'name' => 'maxTokens',
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
            'startLine' => 271,
            'endLine' => 271,
            'startColumn' => 45,
            'endColumn' => 58,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * {@inheritdoc}
 */',
        'startLine' => 271,
        'endLine' => 306,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
      'supportsTextGeneration' => 
      array (
        'name' => 'supportsTextGeneration',
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
 * {@inheritdoc}
 */',
        'startLine' => 311,
        'endLine' => 314,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'aliasName' => NULL,
      ),
      'parseJsonArray' => 
      array (
        'name' => 'parseJsonArray',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 326,
            'endLine' => 326,
            'startColumn' => 37,
            'endColumn' => 51,
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
 * Parse a JSON array from LLM response content.
 *
 * @param  string  $content  Raw response content
 * @return array<array<string, mixed>> Parsed array or empty on failure
 */',
        'startLine' => 326,
        'endLine' => 361,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\OpenAIAdapter',
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