<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/Adapters/AnthropicAdapter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\Adapters\AnthropicAdapter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-3c3885fbbd9c365f81faab154065ef1b1d3b6ce4425ccdee51ffbd40295ec84a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/Adapters/AnthropicAdapter.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory\\Adapters',
    'name' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
    'shortName' => 'AnthropicAdapter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Anthropic adapter implementing extraction capability only.
 *
 * Note: Anthropic does not provide an embeddings API, so this adapter
 * only implements ExtractionProvider. When only Anthropic is configured,
 * the system operates in "degraded" mode with BM25 keyword search only.
 *
 * Provides:
 * - Entity extraction via Claude with structured output
 * - Importance scoring via Claude
 * - Summarization via Claude
 *
 * All API calls use Guzzle HTTP client with retry handling.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 301,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Memory\\Adapters\\GuzzleHttpAdapter',
    'implementsClassNames' => 
    array (
      0 => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'API_VERSION' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'name' => 'API_VERSION',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'2023-06-01\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 51,
            'startFilePos' => 822,
            'endTokenPos' => 51,
            'endFilePos' => 833,
          ),
        ),
        'docComment' => '/**
 * Anthropic API version header.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 45,
      ),
    ),
    'immediateProperties' => 
    array (
      'extractionModel' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
        'startLine' => 34,
        'endLine' => 34,
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
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
        'startLine' => 39,
        'endLine' => 39,
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
            'startLine' => 49,
            'endLine' => 49,
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
              'code' => '\'claude-3-haiku-20240307\'',
              'attributes' => 
              array (
                'startLine' => 50,
                'endLine' => 50,
                'startTokenPos' => 92,
                'startFilePos' => 1443,
                'endTokenPos' => 92,
                'endFilePos' => 1467,
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
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 9,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'summarizationModel' => 
          array (
            'name' => 'summarizationModel',
            'default' => 
            array (
              'code' => '\'claude-3-haiku-20240307\'',
              'attributes' => 
              array (
                'startLine' => 51,
                'endLine' => 51,
                'startTokenPos' => 101,
                'startFilePos' => 1507,
                'endTokenPos' => 101,
                'endFilePos' => 1531,
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
            'startLine' => 51,
            'endLine' => 51,
            'startColumn' => 9,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new Anthropic adapter instance.
 *
 * @param  string  $apiKey  Anthropic API key
 * @param  string  $extractionModel  Model for extraction (default: claude-3-haiku-20240307)
 * @param  string  $summarizationModel  Model for summarization (default: claude-3-haiku-20240307)
 */',
        'startLine' => 48,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
        'startLine' => 65,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
        'startLine' => 77,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
            'startLine' => 85,
            'endLine' => 85,
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
        'startLine' => 85,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
            'startLine' => 131,
            'endLine' => 131,
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
            'startLine' => 131,
            'endLine' => 131,
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
        'startLine' => 131,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
            'startLine' => 188,
            'endLine' => 188,
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
            'startLine' => 188,
            'endLine' => 188,
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
        'startLine' => 188,
        'endLine' => 223,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
        'startLine' => 228,
        'endLine' => 231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'aliasName' => NULL,
      ),
      'extractTextContent' => 
      array (
        'name' => 'extractTextContent',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
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
            'startLine' => 245,
            'endLine' => 245,
            'startColumn' => 41,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Extract text content from Anthropic API response.
 *
 * Anthropic returns content as an array of content blocks.
 *
 * @param  array<string, mixed>  $response  API response
 * @return string Extracted text content
 */',
        'startLine' => 245,
        'endLine' => 257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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
            'startLine' => 265,
            'endLine' => 265,
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
        'startLine' => 265,
        'endLine' => 300,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\Adapters',
        'declaringClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'implementingClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
        'currentClassName' => 'App\\Support\\Memory\\Adapters\\AnthropicAdapter',
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