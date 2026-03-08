<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Gateway/ReconnectionStrategy.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Gateway\ReconnectionStrategy
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-ceec37e858d9bef44bad9b3754740edeb9ef77467ef61276d57e3e4fb34d1a5b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Gateway/ReconnectionStrategy.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Gateway',
    'name' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
    'shortName' => 'ReconnectionStrategy',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Implements exponential backoff with jitter for gateway worker reconnections.
 *
 * The strategy prevents thundering herd problems when multiple workers
 * attempt to reconnect simultaneously after a network partition.
 *
 * Delay calculation: base_delay * 2^attempt * (1 ± jitter)
 *
 * Configuration values from config(\'messenger.gateway.reconnect\'):
 * - initial_delay: Starting delay in seconds (default: 1)
 * - max_delay: Maximum delay cap in seconds (default: 300 = 5 minutes)
 * - jitter_percent: Percentage of randomization (default: 20 = ±20%)
 *
 * The attempt counter resets after a stable connection lasting over 60 seconds.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 202,
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
      'STABLE_CONNECTION_THRESHOLD_SECONDS' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'name' => 'STABLE_CONNECTION_THRESHOLD_SECONDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '60',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 64,
            'startFilePos' => 962,
            'endTokenPos' => 64,
            'endFilePos' => 963,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 59,
      ),
    ),
    'immediateProperties' => 
    array (
      'attemptCount' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'name' => 'attemptCount',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 41,
            'startFilePos' => 843,
            'endTokenPos' => 41,
            'endFilePos' => 843,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'connectionStartedAt' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'name' => 'connectionStartedAt',
        'modifiers' => 4,
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
                  'name' => 'Carbon\\CarbonInterface',
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 53,
            'startFilePos' => 899,
            'endTokenPos' => 53,
            'endFilePos' => 902,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 57,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'initialDelay' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'name' => 'initialDelay',
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
                  'name' => 'float',
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
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 9,
        'endColumn' => 52,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'maxDelay' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'name' => 'maxDelay',
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
                  'name' => 'float',
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
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 9,
        'endColumn' => 48,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'jitterPercent' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'name' => 'jitterPercent',
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
                  'name' => 'float',
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
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 9,
        'endColumn' => 53,
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
          'initialDelay' => 
          array (
            'name' => 'initialDelay',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 34,
                'endLine' => 34,
                'startTokenPos' => 85,
                'startFilePos' => 1048,
                'endTokenPos' => 85,
                'endFilePos' => 1051,
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
                      'name' => 'float',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 9,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'maxDelay' => 
          array (
            'name' => 'maxDelay',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 35,
                'endLine' => 35,
                'startTokenPos' => 99,
                'startFilePos' => 1098,
                'endTokenPos' => 99,
                'endFilePos' => 1101,
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
                      'name' => 'float',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 9,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'jitterPercent' => 
          array (
            'name' => 'jitterPercent',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 36,
                'endLine' => 36,
                'startTokenPos' => 113,
                'startFilePos' => 1153,
                'endTokenPos' => 113,
                'endFilePos' => 1156,
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
                      'name' => 'float',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 33,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'getNextDelay' => 
      array (
        'name' => 'getNextDelay',
        'parameters' => 
        array (
          'attempt' => 
          array (
            'name' => 'attempt',
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 34,
            'endColumn' => 45,
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
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Calculate the next delay for a given attempt number.
 *
 * @param  int  $attempt  The attempt number (0-indexed)
 * @return float Delay in seconds
 */',
        'startLine' => 45,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'recordAttempt' => 
      array (
        'name' => 'recordAttempt',
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
 * Record a reconnection attempt.
 */',
        'startLine' => 73,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'getAttemptCount' => 
      array (
        'name' => 'getAttemptCount',
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
 * Get the current attempt count.
 */',
        'startLine' => 86,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'resetAttempts' => 
      array (
        'name' => 'resetAttempts',
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
 * Reset the attempt counter.
 */',
        'startLine' => 94,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'recordConnectionStart' => 
      array (
        'name' => 'recordConnectionStart',
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
 * Record the start of a successful connection.
 */',
        'startLine' => 105,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'shouldResetAttempts' => 
      array (
        'name' => 'shouldResetAttempts',
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
 * Check if the connection has been stable long enough to reset attempts.
 */',
        'startLine' => 117,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'resetIfStable' => 
      array (
        'name' => 'resetIfStable',
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
 * Reset attempts if the connection has been stable.
 */',
        'startLine' => 131,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'nextDelay' => 
      array (
        'name' => 'nextDelay',
        'parameters' => 
        array (
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
 * Get the next delay based on internal attempt counter and increment it.
 */',
        'startLine' => 144,
        'endLine' => 150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'applyJitter' => 
      array (
        'name' => 'applyJitter',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 155,
            'endLine' => 155,
            'startColumn' => 34,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'jitterPercent' => 
          array (
            'name' => 'jitterPercent',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 155,
            'endLine' => 155,
            'startColumn' => 48,
            'endColumn' => 67,
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
 * Apply jitter to a delay value.
 */',
        'startLine' => 155,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'getInitialDelay' => 
      array (
        'name' => 'getInitialDelay',
        'parameters' => 
        array (
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
 * Get initial delay from config or constructor.
 */',
        'startLine' => 170,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'getMaxDelay' => 
      array (
        'name' => 'getMaxDelay',
        'parameters' => 
        array (
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
 * Get max delay from config or constructor.
 */',
        'startLine' => 182,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'aliasName' => NULL,
      ),
      'getJitterPercent' => 
      array (
        'name' => 'getJitterPercent',
        'parameters' => 
        array (
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
 * Get jitter percent from config or constructor.
 */',
        'startLine' => 194,
        'endLine' => 201,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'implementingClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
        'currentClassName' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
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