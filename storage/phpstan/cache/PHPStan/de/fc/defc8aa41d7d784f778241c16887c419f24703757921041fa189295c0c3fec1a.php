<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/WorkingMemorySummarizer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\WorkingMemorySummarizer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-d0e06a6648cba465304c8e71dbcd9a65ff0c60115acb949d365c221aebb53ad5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/WorkingMemorySummarizer.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory',
    'name' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
    'shortName' => 'WorkingMemorySummarizer',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Working Memory Summarizer for API mode eviction.
 *
 * In API mode, when the working memory buffer exceeds max_messages,
 * older entries are summarized using an LLM before eviction.
 * This preserves context while reducing token usage.
 *
 * This is a stub implementation for v1. Full LLM summarization
 * will be implemented when API mode is enabled.
 *
 * In No-API mode, the WorkingMemoryBuffer uses oldest-first
 * truncation (ZREMRANGEBYRANK) which doesn\'t require summarization.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 53,
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
      'summarize' => 
      array (
        'name' => 'summarize',
        'parameters' => 
        array (
          'entries' => 
          array (
            'name' => 'entries',
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
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 31,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'maxTokens' => 
          array (
            'name' => 'maxTokens',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 29,
                'endLine' => 29,
                'startTokenPos' => 42,
                'startFilePos' => 1005,
                'endTokenPos' => 42,
                'endFilePos' => 1007,
              ),
            ),
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
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 47,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'string',
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
 * Summarize a batch of working memory entries.
 *
 * @param  array<int, array{role: string, content: string, metadata: array<string, mixed>, timestamp: float}>  $entries
 * @param  int  $maxTokens  Maximum tokens for the summary
 * @return string|null The summary text, or null if summarization is not available
 */',
        'startLine' => 29,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'implementingClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'currentClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'aliasName' => NULL,
      ),
      'isAvailable' => 
      array (
        'name' => 'isAvailable',
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
 * Check if summarization is available (API mode with provider keys).
 */',
        'startLine' => 39,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'implementingClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'currentClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'aliasName' => NULL,
      ),
      'getModel' => 
      array (
        'name' => 'getModel',
        'parameters' => 
        array (
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
                  'name' => 'string',
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
 * Get the summarization model being used.
 */',
        'startLine' => 48,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'implementingClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
        'currentClassName' => 'App\\Support\\Memory\\WorkingMemorySummarizer',
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