<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/ProviderUsageTracker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\ProviderUsageTracker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-6dab323d7989f244b9b765ebdd81341ed2a484cabf95a7d0e8b30a13790a4e7c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/ProviderUsageTracker.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory',
    'name' => 'App\\Support\\Memory\\ProviderUsageTracker',
    'shortName' => 'ProviderUsageTracker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Tracks provider API usage and estimates costs.
 *
 * Provides:
 * - Token usage recording per operation
 * - Cost estimation from hardcoded pricing tables
 * - Usage aggregation per user
 * - Sensitive data stripping for logging
 *
 * IMPORTANT: This class never logs API keys or other sensitive data.
 * All context passed for logging is sanitized via getLoggableContext().
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 284,
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
      'SENSITIVE_FIELDS_EXACT' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'name' => 'SENSITIVE_FIELDS_EXACT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'api_key\', \'apiKey\', \'api-key\', \'authorization\', \'secret\', \'password\', \'token\', \'bearer\', \'credential\', \'credentials\', \'access_token\', \'accessToken\', \'refresh_token\', \'refreshToken\', \'private_key\', \'privateKey\']',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 45,
            'startTokenPos' => 43,
            'startFilePos' => 720,
            'endTokenPos' => 93,
            'endFilePos' => 1066,
          ),
        ),
        'docComment' => '/**
 * Sensitive field patterns that should never be logged.
 * These are checked for exact match (case-insensitive).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'SENSITIVE_SUFFIXES' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'name' => 'SENSITIVE_SUFFIXES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'_key\', \'_secret\', \'_token\', \'_password\', \'_credential\']',
          'attributes' => 
          array (
            'startLine' => 51,
            'endLine' => 57,
            'startTokenPos' => 106,
            'startFilePos' => 1244,
            'endTokenPos' => 123,
            'endFilePos' => 1347,
          ),
        ),
        'docComment' => '/**
 * Sensitive field suffixes to check.
 * Fields ending with these (case-insensitive) are considered sensitive.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'record' => 
      array (
        'name' => 'record',
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
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 9,
            'endColumn' => 19,
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
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 9,
            'endColumn' => 21,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operation' => 
          array (
            'name' => 'operation',
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
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'inputTokens' => 
          array (
            'name' => 'inputTokens',
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
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
          'outputTokens' => 
          array (
            'name' => 'outputTokens',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 78,
                'endLine' => 78,
                'startTokenPos' => 167,
                'startFilePos' => 2194,
                'endTokenPos' => 167,
                'endFilePos' => 2197,
              ),
            ),
            'type' => 
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
                      'name' => 'int',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 9,
            'endColumn' => 33,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'runId' => 
          array (
            'name' => 'runId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 79,
                'endLine' => 79,
                'startTokenPos' => 177,
                'startFilePos' => 2222,
                'endTokenPos' => 177,
                'endFilePos' => 2225,
              ),
            ),
            'type' => 
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
                      'name' => 'int',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 9,
            'endColumn' => 26,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
          'metadata' => 
          array (
            'name' => 'metadata',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 80,
                'endLine' => 80,
                'startTokenPos' => 186,
                'startFilePos' => 2254,
                'endTokenPos' => 187,
                'endFilePos' => 2255,
              ),
            ),
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
            'startLine' => 80,
            'endLine' => 80,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 7,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\MemoryProviderUsage',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Record provider usage.
 *
 * @param  int  $userId  User ID
 * @param  string  $provider  Provider name (e.g., \'openai\')
 * @param  string  $model  Model name (e.g., \'gpt-4o-mini\')
 * @param  string  $operation  Operation type (extraction, summarization, embedding)
 * @param  int  $inputTokens  Number of input tokens
 * @param  int|null  $outputTokens  Number of output tokens (null for embeddings)
 * @param  int|null  $runId  Optional run ID for tracking
 * @param  array<string, mixed>  $metadata  Optional metadata (sensitive fields stripped)
 * @return MemoryProviderUsage Created usage record
 */',
        'startLine' => 72,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'aliasName' => NULL,
      ),
      'estimateCost' => 
      array (
        'name' => 'estimateCost',
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
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 9,
            'endColumn' => 21,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'inputTokens' => 
          array (
            'name' => 'inputTokens',
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
            'startLine' => 110,
            'endLine' => 110,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'outputTokens' => 
          array (
            'name' => 'outputTokens',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 111,
                'endLine' => 111,
                'startTokenPos' => 329,
                'startFilePos' => 3282,
                'endTokenPos' => 329,
                'endFilePos' => 3285,
              ),
            ),
            'type' => 
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
                      'name' => 'int',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 9,
            'endColumn' => 33,
            'parameterIndex' => 3,
            'isOptional' => true,
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
 * Estimate cost for a provider API call.
 *
 * @param  string  $provider  Provider name
 * @param  string  $model  Model name
 * @param  int  $inputTokens  Number of input tokens
 * @param  int|null  $outputTokens  Number of output tokens
 * @return float Estimated cost in USD
 */',
        'startLine' => 107,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'aliasName' => NULL,
      ),
      'getUsageStats' => 
      array (
        'name' => 'getUsageStats',
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'since' => 
          array (
            'name' => 'since',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 136,
                'endLine' => 136,
                'startTokenPos' => 468,
                'startFilePos' => 4204,
                'endTokenPos' => 468,
                'endFilePos' => 4207,
              ),
            ),
            'type' => 
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
                      'name' => 'DateTimeInterface',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 136,
            'endLine' => 136,
            'startColumn' => 9,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'until' => 
          array (
            'name' => 'until',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 137,
                'endLine' => 137,
                'startTokenPos' => 478,
                'startFilePos' => 4246,
                'endTokenPos' => 478,
                'endFilePos' => 4249,
              ),
            ),
            'type' => 
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
                      'name' => 'DateTimeInterface',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 9,
            'endColumn' => 40,
            'parameterIndex' => 2,
            'isOptional' => true,
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
 * Get usage statistics for a user.
 *
 * @param  int  $userId  User ID
 * @param  DateTimeInterface|null  $since  Optional start date filter
 * @param  DateTimeInterface|null  $until  Optional end date filter
 * @return array{total_tokens: int, total_cost: float, by_provider: array<string, array{tokens: int, cost: float}>, by_operation: array<string, array{tokens: int, cost: float}>}
 */',
        'startLine' => 134,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'aliasName' => NULL,
      ),
      'getTotalCost' => 
      array (
        'name' => 'getTotalCost',
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
            'startLine' => 192,
            'endLine' => 192,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'since' => 
          array (
            'name' => 'since',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 192,
                'endLine' => 192,
                'startTokenPos' => 874,
                'startFilePos' => 6031,
                'endTokenPos' => 874,
                'endFilePos' => 6034,
              ),
            ),
            'type' => 
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
                      'name' => 'DateTimeInterface',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 192,
            'endLine' => 192,
            'startColumn' => 47,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => true,
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
 * Get total cost for a user.
 *
 * @param  int  $userId  User ID
 * @param  DateTimeInterface|null  $since  Optional start date filter
 * @return float Total cost in USD
 */',
        'startLine' => 192,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'aliasName' => NULL,
      ),
      'getLoggableContext' => 
      array (
        'name' => 'getLoggableContext',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
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
            'startColumn' => 40,
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
 * Strip sensitive fields from context for logging.
 *
 * IMPORTANT: Always use this method before logging any provider-related context.
 * This ensures API keys and other sensitive data never appear in logs.
 *
 * @param  array<string, mixed>  $context  Context to sanitize
 * @return array<string, mixed> Sanitized context safe for logging
 */',
        'startLine' => 214,
        'endLine' => 237,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'aliasName' => NULL,
      ),
      'isSensitiveKey' => 
      array (
        'name' => 'isSensitiveKey',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 242,
            'endLine' => 242,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if a key name indicates sensitive data.
 */',
        'startLine' => 242,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'aliasName' => NULL,
      ),
      'looksLikeSensitiveValue' => 
      array (
        'name' => 'looksLikeSensitiveValue',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 266,
            'endLine' => 266,
            'startColumn' => 46,
            'endColumn' => 58,
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
 * Check if a value looks like sensitive data.
 */',
        'startLine' => 266,
        'endLine' => 283,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory',
        'declaringClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'implementingClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
        'currentClassName' => 'App\\Support\\Memory\\ProviderUsageTracker',
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