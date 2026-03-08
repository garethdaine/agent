<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Memory/RateLimiter/ProviderRateLimiter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Memory\RateLimiter\ProviderRateLimiter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-24218923cd4f2edf42b5f52ab240889fba3cc748ce211cdfcfe601664a4474c2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Memory/RateLimiter/ProviderRateLimiter.php',
      ),
    ),
    'namespace' => 'App\\Support\\Memory\\RateLimiter',
    'name' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
    'shortName' => 'ProviderRateLimiter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Token-bucket rate limiter for memory provider API calls.
 *
 * Implements proactive rate limiting per provider+model combination using Redis.
 * Supports:
 * - Requests per minute limits
 * - Tokens per minute limits
 * - Requests per day limits
 * - Reactive 429 handling with jitter backoff
 *
 * Redis keys:
 * - memory:ratelimit:{provider}:{model}:requests - Request bucket
 * - memory:ratelimit:{provider}:{model}:tokens - Token bucket
 * - memory:ratelimit:{provider}:{model}:daily - Daily request counter
 * - memory:ratelimit:{provider}:{model}:blocked - Blocked until timestamp
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 342,
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
      'DEFAULT_LIMITS' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'name' => 'DEFAULT_LIMITS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'requests_per_minute\' => 60, \'tokens_per_minute\' => 60000, \'requests_per_day\' => 5000]',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 34,
            'startTokenPos' => 38,
            'startFilePos' => 849,
            'endTokenPos' => 61,
            'endFilePos' => 966,
          ),
        ),
        'docComment' => '/**
 * Default rate limits when provider not configured.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'DEFAULT_BACKOFF_SECONDS' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'name' => 'DEFAULT_BACKOFF_SECONDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '60',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 74,
            'startFilePos' => 1080,
            'endTokenPos' => 74,
            'endFilePos' => 1081,
          ),
        ),
        'docComment' => '/**
 * Default backoff seconds for 429 responses.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'JITTER_PERCENT' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'name' => 'JITTER_PERCENT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0.1',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 44,
            'startTokenPos' => 87,
            'startFilePos' => 1182,
            'endTokenPos' => 87,
            'endFilePos' => 1185,
          ),
        ),
        'docComment' => '/**
 * Maximum jitter percentage for backoff.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
    ),
    'immediateProperties' => 
    array (
      'connection' => 
      array (
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'name' => 'connection',
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
 * Redis connection name.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 31,
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
          'connection' => 
          array (
            'name' => 'connection',
            'default' => 
            array (
              'code' => '\'memory\'',
              'attributes' => 
              array (
                'startLine' => 54,
                'endLine' => 54,
                'startTokenPos' => 113,
                'startFilePos' => 1380,
                'endTokenPos' => 113,
                'endFilePos' => 1387,
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
            'startColumn' => 33,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new rate limiter instance.
 */',
        'startLine' => 54,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'attempt' => 
      array (
        'name' => 'attempt',
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
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 29,
            'endColumn' => 44,
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
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 47,
            'endColumn' => 59,
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
            'name' => 'App\\Support\\Memory\\RateLimiter\\RateLimitResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attempt to make a request to the provider.
 *
 * @param  string  $provider  Provider name (e.g., \'openai\')
 * @param  string  $model  Model name (e.g., \'gpt-4o-mini\')
 * @return RateLimitResult Result indicating if request is allowed
 */',
        'startLine' => 66,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'recordRateLimitHit' => 
      array (
        'name' => 'recordRateLimitHit',
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
            'startLine' => 129,
            'endLine' => 129,
            'startColumn' => 40,
            'endColumn' => 55,
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
            'startLine' => 129,
            'endLine' => 129,
            'startColumn' => 58,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'retryAfterSeconds' => 
          array (
            'name' => 'retryAfterSeconds',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 129,
                'endLine' => 129,
                'startTokenPos' => 503,
                'startFilePos' => 3889,
                'endTokenPos' => 503,
                'endFilePos' => 3892,
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
            'startLine' => 129,
            'endLine' => 129,
            'startColumn' => 73,
            'endColumn' => 102,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Record a 429 rate limit hit from the provider.
 *
 * @param  string  $provider  Provider name
 * @param  string  $model  Model name
 * @param  int|null  $retryAfterSeconds  Retry-After header value (or null for default)
 */',
        'startLine' => 129,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'recordTokenUsage' => 
      array (
        'name' => 'recordTokenUsage',
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 38,
            'endColumn' => 53,
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 56,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'tokens' => 
          array (
            'name' => 'tokens',
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 71,
            'endColumn' => 81,
            'parameterIndex' => 2,
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
 * Record token usage for the rate limiter.
 *
 * @param  string  $provider  Provider name
 * @param  string  $model  Model name
 * @param  int  $tokens  Number of tokens consumed
 */',
        'startLine' => 151,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getRateLimitState' => 
      array (
        'name' => 'getRateLimitState',
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
            'startLine' => 174,
            'endLine' => 174,
            'startColumn' => 39,
            'endColumn' => 54,
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
            'startLine' => 174,
            'endLine' => 174,
            'startColumn' => 57,
            'endColumn' => 69,
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
 * Get current rate limit state for a provider+model.
 *
 * @param  string  $provider  Provider name
 * @param  string  $model  Model name
 * @return array{remaining: int, limit: int, reset_at: int, blocked_until: int|null, tokens_used: int}
 */',
        'startLine' => 174,
        'endLine' => 191,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'resetBucket' => 
      array (
        'name' => 'resetBucket',
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
            'startLine' => 199,
            'endLine' => 199,
            'startColumn' => 33,
            'endColumn' => 48,
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
            'startLine' => 199,
            'endLine' => 199,
            'startColumn' => 51,
            'endColumn' => 63,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reset the bucket for a provider+model (for testing).
 *
 * @param  string  $provider  Provider name
 * @param  string  $model  Model name
 */',
        'startLine' => 199,
        'endLine' => 208,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getLimits' => 
      array (
        'name' => 'getLimits',
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
            'startLine' => 215,
            'endLine' => 215,
            'startColumn' => 32,
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
 * Get rate limits for a provider.
 *
 * @return array{requests_per_minute: int, tokens_per_minute: int, requests_per_day: int}
 */',
        'startLine' => 215,
        'endLine' => 228,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getKeyPrefix' => 
      array (
        'name' => 'getKeyPrefix',
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
            'startLine' => 233,
            'endLine' => 233,
            'startColumn' => 35,
            'endColumn' => 50,
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
            'startLine' => 233,
            'endLine' => 233,
            'startColumn' => 53,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get Redis key prefix for a provider+model.
 */',
        'startLine' => 233,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'consumeFromBucket' => 
      array (
        'name' => 'consumeFromBucket',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 247,
            'endLine' => 247,
            'startColumn' => 40,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'limit' => 
          array (
            'name' => 'limit',
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
            'startLine' => 247,
            'endLine' => 247,
            'startColumn' => 56,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Consume one request from the bucket.
 *
 * @return int Remaining requests (-1 if limit exceeded)
 */',
        'startLine' => 247,
        'endLine' => 275,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getBlockedUntil' => 
      array (
        'name' => 'getBlockedUntil',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 280,
            'endLine' => 280,
            'startColumn' => 38,
            'endColumn' => 51,
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get blocked until timestamp.
 */',
        'startLine' => 280,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getTokensUsed' => 
      array (
        'name' => 'getTokensUsed',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 291,
            'endLine' => 291,
            'startColumn' => 36,
            'endColumn' => 49,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get tokens used in current minute window.
 */',
        'startLine' => 291,
        'endLine' => 296,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getDailyRequests' => 
      array (
        'name' => 'getDailyRequests',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 301,
            'endLine' => 301,
            'startColumn' => 39,
            'endColumn' => 52,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get daily requests count.
 */',
        'startLine' => 301,
        'endLine' => 306,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'incrementDailyRequests' => 
      array (
        'name' => 'incrementDailyRequests',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 311,
            'endLine' => 311,
            'startColumn' => 45,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Increment daily request counter.
 */',
        'startLine' => 311,
        'endLine' => 322,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getSecondsUntilMinuteReset' => 
      array (
        'name' => 'getSecondsUntilMinuteReset',
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
 * Get seconds until the next minute boundary.
 */',
        'startLine' => 327,
        'endLine' => 330,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'aliasName' => NULL,
      ),
      'getSecondsUntilDayReset' => 
      array (
        'name' => 'getSecondsUntilDayReset',
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
 * Get seconds until midnight (next day reset).
 */',
        'startLine' => 335,
        'endLine' => 341,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Memory\\RateLimiter',
        'declaringClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'implementingClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
        'currentClassName' => 'App\\Support\\Memory\\RateLimiter\\ProviderRateLimiter',
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