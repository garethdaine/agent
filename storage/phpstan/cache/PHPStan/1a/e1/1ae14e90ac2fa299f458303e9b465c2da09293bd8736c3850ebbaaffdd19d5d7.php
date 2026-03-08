<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Reliability/CircuitBreaker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Reliability\CircuitBreaker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-f96f03ccd6fc471baecc3d50d256e8711c9c3ddeb13f475136735b1d82d62856',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Reliability/CircuitBreaker.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Reliability',
    'name' => 'App\\Messenger\\Reliability\\CircuitBreaker',
    'shortName' => 'CircuitBreaker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Per-connector circuit breaker with configurable thresholds.
 *
 * Implements a three-state circuit breaker pattern:
 * - Closed: Normal operation, requests allowed
 * - Open: Circuit tripped after failures, requests rejected
 * - Half-Open: Testing recovery after cooldown, limited requests allowed
 *
 * State transitions:
 * - Closed → Open: After failure_threshold consecutive failures
 * - Open → Half-Open: After cooldown_seconds elapsed
 * - Half-Open → Closed: After half_open_requests successful requests
 * - Half-Open → Open: On any failure during half-open
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 292,
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
      'STATE_CLOSED' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'STATE_CLOSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'closed\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 38,
            'startFilePos' => 813,
            'endTokenPos' => 38,
            'endFilePos' => 820,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'STATE_OPEN' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'STATE_OPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'open\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 49,
            'startFilePos' => 854,
            'endTokenPos' => 49,
            'endFilePos' => 859,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'STATE_HALF_OPEN' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'STATE_HALF_OPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'half_open\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 60,
            'startFilePos' => 898,
            'endTokenPos' => 60,
            'endFilePos' => 908,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'CACHE_KEY_PREFIX' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'CACHE_KEY_PREFIX',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'circuit_breaker:\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 71,
            'startFilePos' => 949,
            'endTokenPos' => 71,
            'endFilePos' => 966,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
      'CACHE_TTL_SECONDS' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'CACHE_TTL_SECONDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '600',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 82,
            'startFilePos' => 1008,
            'endTokenPos' => 82,
            'endFilePos' => 1010,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
    ),
    'immediateProperties' => 
    array (
      'connectorId' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'connectorId',
        'modifiers' => 132,
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 9,
        'endColumn' => 48,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'failureThreshold' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'failureThreshold',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 9,
        'endColumn' => 50,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cooldownSeconds' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'cooldownSeconds',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 9,
        'endColumn' => 50,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'halfOpenRequests' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'name' => 'halfOpenRequests',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 9,
        'endColumn' => 50,
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
          'connectorId' => 
          array (
            'name' => 'connectorId',
            'default' => NULL,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 9,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'failureThreshold' => 
          array (
            'name' => 'failureThreshold',
            'default' => 
            array (
              'code' => '5',
              'attributes' => 
              array (
                'startLine' => 37,
                'endLine' => 37,
                'startTokenPos' => 115,
                'startFilePos' => 1160,
                'endTokenPos' => 115,
                'endFilePos' => 1160,
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'cooldownSeconds' => 
          array (
            'name' => 'cooldownSeconds',
            'default' => 
            array (
              'code' => '60',
              'attributes' => 
              array (
                'startLine' => 38,
                'endLine' => 38,
                'startTokenPos' => 128,
                'startFilePos' => 1211,
                'endTokenPos' => 128,
                'endFilePos' => 1212,
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'halfOpenRequests' => 
          array (
            'name' => 'halfOpenRequests',
            'default' => 
            array (
              'code' => '3',
              'attributes' => 
              array (
                'startLine' => 39,
                'endLine' => 39,
                'startTokenPos' => 141,
                'startFilePos' => 1264,
                'endTokenPos' => 141,
                'endFilePos' => 1264,
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 35,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'forConnector' => 
      array (
        'name' => 'forConnector',
        'parameters' => 
        array (
          'connectorId' => 
          array (
            'name' => 'connectorId',
            'default' => NULL,
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
                      'name' => 'string',
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 41,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a circuit breaker for a connector using config defaults.
 */',
        'startLine' => 45,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'canRequest' => 
      array (
        'name' => 'canRequest',
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
 * Check if a request can be made through this circuit.
 *
 * @throws CircuitOpenException When circuit is open and cooldown not expired
 */',
        'startLine' => 60,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'recordSuccess' => 
      array (
        'name' => 'recordSuccess',
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
 * Record a successful request.
 */',
        'startLine' => 98,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'recordFailure' => 
      array (
        'name' => 'recordFailure',
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
 * Record a failed request.
 */',
        'startLine' => 116,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'getState' => 
      array (
        'name' => 'getState',
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
 * Get the current circuit state.
 */',
        'startLine' => 134,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'getFailureCount' => 
      array (
        'name' => 'getFailureCount',
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
 * Get the current failure count.
 */',
        'startLine' => 144,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'getHalfOpenSuccessCount' => 
      array (
        'name' => 'getHalfOpenSuccessCount',
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
 * Get the count of successful half-open requests.
 */',
        'startLine' => 154,
        'endLine' => 159,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'getCooldownExpiresAt' => 
      array (
        'name' => 'getCooldownExpiresAt',
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
 * Get when the cooldown expires (for monitoring).
 */',
        'startLine' => 164,
        'endLine' => 173,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'getHalfOpenRequestCount' => 
      array (
        'name' => 'getHalfOpenRequestCount',
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
 * Get the count of requests made during half-open state.
 */',
        'startLine' => 178,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'incrementHalfOpenRequestCount' => 
      array (
        'name' => 'incrementHalfOpenRequestCount',
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
 * Increment the half-open request count.
 */',
        'startLine' => 188,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'cooldownExpired' => 
      array (
        'name' => 'cooldownExpired',
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
 * Check if the cooldown period has expired.
 */',
        'startLine' => 198,
        'endLine' => 204,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'transitionTo' => 
      array (
        'name' => 'transitionTo',
        'parameters' => 
        array (
          'newState' => 
          array (
            'name' => 'newState',
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 35,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Transition to a new state.
 */',
        'startLine' => 209,
        'endLine' => 232,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'incrementFailures' => 
      array (
        'name' => 'incrementFailures',
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
 * Increment the failure count.
 */',
        'startLine' => 237,
        'endLine' => 243,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'incrementHalfOpenSuccess' => 
      array (
        'name' => 'incrementHalfOpenSuccess',
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
 * Increment the half-open success count.
 */',
        'startLine' => 248,
        'endLine' => 253,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'resetFailures' => 
      array (
        'name' => 'resetFailures',
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
 * Reset the failure count.
 */',
        'startLine' => 258,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'cacheKey' => 
      array (
        'name' => 'cacheKey',
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
 * Get the cache key for this circuit breaker.
 */',
        'startLine' => 268,
        'endLine' => 271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'getStateData' => 
      array (
        'name' => 'getStateData',
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
 * Get the current state data from cache.
 *
 * @return array<string, mixed>
 */',
        'startLine' => 278,
        'endLine' => 281,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'saveStateData' => 
      array (
        'name' => 'saveStateData',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 288,
            'endLine' => 288,
            'startColumn' => 36,
            'endColumn' => 46,
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
 * Save state data to cache.
 *
 * @param  array<string, mixed>  $data
 */',
        'startLine' => 288,
        'endLine' => 291,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'implementingClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
        'currentClassName' => 'App\\Messenger\\Reliability\\CircuitBreaker',
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