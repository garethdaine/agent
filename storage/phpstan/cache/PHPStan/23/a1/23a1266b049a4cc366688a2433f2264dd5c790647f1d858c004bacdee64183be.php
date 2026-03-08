<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/Contracts/ExtractionProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\Contracts\ExtractionProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-037eb078ec16f777f41f4b812d3dd8009069fc7c9022cd8e63c0b2985b41ec98',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/Contracts/ExtractionProvider.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory\\Contracts',
    'name' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
    'shortName' => 'ExtractionProvider',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Contract for LLM text extraction and summarization providers.
 *
 * Provides entity extraction, importance scoring, and text summarization
 * for memory formation. Both OpenAI and Anthropic support this capability.
 *
 * Entity types extracted:
 * - Standard NER: Person, Organization, Location, Date, Concept
 * - Technical: File, Function, Class, API, Error, Dependency
 *
 * Implementations should:
 * - Handle rate limiting gracefully with retries
 * - Return empty results on API errors rather than throwing
 * - Track token usage for cost estimation
 * - Use structured output for reliable entity extraction
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 84,
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
            'startLine' => 37,
            'endLine' => 37,
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
 * Extract named entities from text.
 *
 * Returns array of entities with type and value:
 * [
 *     [\'type\' => \'Person\', \'name\' => \'John Doe\', \'confidence\' => 0.95],
 *     [\'type\' => \'API\', \'name\' => \'/api/users\', \'confidence\' => 0.88],
 * ]
 *
 * @param  string  $text  The text to extract entities from
 * @return array<array{type: string, name: string, confidence?: float}> Extracted entities
 */',
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 57,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
            'startLine' => 54,
            'endLine' => 54,
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
            'startLine' => 54,
            'endLine' => 54,
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
 * Score the importance of text with extracted entities.
 *
 * Returns a float between 0.0 and 1.0 indicating how important
 * this content is for long-term memory formation.
 *
 * Factors considered:
 * - Entity density and novelty
 * - Actionable information
 * - Reference potential
 *
 * @param  string  $text  The text to score
 * @param  array<array{type: string, name: string}>  $entities  Previously extracted entities
 * @return float Importance score between 0.0 and 1.0
 */',
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 74,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
            'startLine' => 66,
            'endLine' => 66,
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
            'startLine' => 66,
            'endLine' => 66,
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
 * Summarize text within token budget.
 *
 * Used for Working Memory eviction - summarizes older turns
 * to preserve information while reducing token count.
 *
 * @param  string  $text  The text to summarize
 * @param  int  $maxTokens  Maximum tokens for summary output
 * @return string Summarized text
 */',
        'startLine' => 66,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 68,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
 * @return string Provider identifier (e.g., \'openai\', \'anthropic\')
 */',
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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
 * Check if this provider supports text generation.
 *
 * Always returns true for ExtractionProvider implementations,
 * but useful for duck-typing checks.
 *
 * @return bool True if text generation is supported
 */',
        'startLine' => 83,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\Contracts',
        'declaringClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'implementingClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
        'currentClassName' => 'App\\Support\\Memory\\Contracts\\ExtractionProvider',
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