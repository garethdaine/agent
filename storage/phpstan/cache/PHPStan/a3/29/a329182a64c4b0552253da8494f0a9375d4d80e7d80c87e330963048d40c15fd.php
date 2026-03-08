<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Gateway/MessengerGatewayManager.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Gateway\MessengerGatewayManager
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-7b44b8521ae947739248be5fff905a686cedd93448daa301a82c1e638431300f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Gateway/MessengerGatewayManager.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Gateway',
    'name' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
    'shortName' => 'MessengerGatewayManager',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Supervisor service managing all gateway workers within the messenger gateway process.
 *
 * Responsibilities:
 * - Boot and manage workers for all local-mode connectors
 * - Periodic health checks and database state updates
 * - Credential change detection and graceful restarts
 * - Graceful shutdown with drain timeout
 *
 * This service is designed to run within a single long-lived PHP process
 * alongside Horizon, using ReactPHP for async operations.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 32,
    'endLine' => 493,
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
      'workers' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'name' => 'workers',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 93,
            'startFilePos' => 1212,
            'endTokenPos' => 94,
            'endFilePos' => 1213,
          ),
        ),
        'docComment' => '/**
 * Active workers keyed by connector_account_id.
 *
 * @var array<string, GatewayWorkerInterface>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'credentialHashes' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'name' => 'credentialHashes',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 107,
            'startFilePos' => 1359,
            'endTokenPos' => 108,
            'endFilePos' => 1360,
          ),
        ),
        'docComment' => '/**
 * Credential hashes for change detection.
 *
 * @var array<string, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'workerFactory' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'name' => 'workerFactory',
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
                  'name' => 'Closure',
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
            'startLine' => 51,
            'endLine' => 51,
            'startTokenPos' => 122,
            'startFilePos' => 1480,
            'endTokenPos' => 122,
            'endFilePos' => 1483,
          ),
        ),
        'docComment' => '/**
 * Factory for creating workers (injectable for testing).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shuttingDown' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'name' => 'shuttingDown',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 56,
            'endLine' => 56,
            'startTokenPos' => 135,
            'startFilePos' => 1585,
            'endTokenPos' => 135,
            'endFilePos' => 1589,
          ),
        ),
        'docComment' => '/**
 * Whether the manager is in shutdown state.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timers' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'name' => 'timers',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 63,
            'endLine' => 63,
            'startTokenPos' => 148,
            'startFilePos' => 1726,
            'endTokenPos' => 149,
            'endFilePos' => 1727,
          ),
        ),
        'docComment' => '/**
 * Periodic timers for cleanup on shutdown.
 *
 * @var array<TimerInterface>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'loop' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'name' => 'loop',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'React\\EventLoop\\LoopInterface',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 66,
        'endLine' => 66,
        'startColumn' => 9,
        'endColumn' => 44,
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
          'loop' => 
          array (
            'name' => 'loop',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'React\\EventLoop\\LoopInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 9,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 65,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'bootWorkers' => 
      array (
        'name' => 'bootWorkers',
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
 * Boot all workers for local-mode connectors.
 */',
        'startLine' => 72,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'addWorker' => 
      array (
        'name' => 'addWorker',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 98,
            'endLine' => 98,
            'startColumn' => 31,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a worker for a connector account.
 */',
        'startLine' => 98,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'removeWorker' => 
      array (
        'name' => 'removeWorker',
        'parameters' => 
        array (
          'accountId' => 
          array (
            'name' => 'accountId',
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
            'startLine' => 136,
            'endLine' => 136,
            'startColumn' => 34,
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
 * Remove a worker by connector account ID.
 */',
        'startLine' => 136,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'restartWorker' => 
      array (
        'name' => 'restartWorker',
        'parameters' => 
        array (
          'accountId' => 
          array (
            'name' => 'accountId',
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
            'startLine' => 169,
            'endLine' => 169,
            'startColumn' => 35,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Restart a worker with fresh credentials.
 *
 * Performs: drain → stop → start with fresh model data.
 */',
        'startLine' => 169,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'shutdown' => 
      array (
        'name' => 'shutdown',
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
 * Graceful shutdown of all workers.
 *
 * - Stop accepting new events
 * - Drain in-flight messages (max timeout from config)
 * - Stop all workers
 * - Stop the event loop
 */',
        'startLine' => 216,
        'endLine' => 255,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'hasWorker' => 
      array (
        'name' => 'hasWorker',
        'parameters' => 
        array (
          'accountId' => 
          array (
            'name' => 'accountId',
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
            'startLine' => 260,
            'endLine' => 260,
            'startColumn' => 31,
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
 * Check if a worker exists for the given connector.
 */',
        'startLine' => 260,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'getWorkerCount' => 
      array (
        'name' => 'getWorkerCount',
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
 * Get the count of active workers.
 */',
        'startLine' => 268,
        'endLine' => 271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'isShutdown' => 
      array (
        'name' => 'isShutdown',
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
 * Check if the manager is shutting down.
 */',
        'startLine' => 276,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'getHealthSummary' => 
      array (
        'name' => 'getHealthSummary',
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
 * Get health summary for all workers.
 *
 * Returns an array of rows suitable for console table output.
 *
 * @return array<int, array{connector: string, provider: string, status: string, last_event: string}>
 */',
        'startLine' => 288,
        'endLine' => 310,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'getCredentialHash' => 
      array (
        'name' => 'getCredentialHash',
        'parameters' => 
        array (
          'accountId' => 
          array (
            'name' => 'accountId',
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
            'startLine' => 315,
            'endLine' => 315,
            'startColumn' => 39,
            'endColumn' => 55,
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
 * Get the credential hash for a connector.
 */',
        'startLine' => 315,
        'endLine' => 324,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'checkCredentialChanges' => 
      array (
        'name' => 'checkCredentialChanges',
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
 * Check for credential changes on all workers and restart if needed.
 */',
        'startLine' => 329,
        'endLine' => 370,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'performHealthCheck' => 
      array (
        'name' => 'performHealthCheck',
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
 * Perform health check on all workers and update database state.
 */',
        'startLine' => 375,
        'endLine' => 395,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'setWorkerFactory' => 
      array (
        'name' => 'setWorkerFactory',
        'parameters' => 
        array (
          'factory' => 
          array (
            'name' => 'factory',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 400,
            'endLine' => 400,
            'startColumn' => 38,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set a custom worker factory (for testing).
 */',
        'startLine' => 400,
        'endLine' => 403,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'syncDiscordSlashCommandsIfNeeded' => 
      array (
        'name' => 'syncDiscordSlashCommandsIfNeeded',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 408,
            'endLine' => 408,
            'startColumn' => 55,
            'endColumn' => 79,
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
 * Create a worker for the given connector account.
 */',
        'startLine' => 408,
        'endLine' => 434,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'createWorker' => 
      array (
        'name' => 'createWorker',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 436,
            'endLine' => 436,
            'startColumn' => 35,
            'endColumn' => 59,
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
            'name' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 436,
        'endLine' => 463,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'setupPeriodicTimers' => 
      array (
        'name' => 'setupPeriodicTimers',
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
 * Set up periodic timers for health checks and credential polling.
 */',
        'startLine' => 468,
        'endLine' => 482,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'aliasName' => NULL,
      ),
      'hashCredentials' => 
      array (
        'name' => 'hashCredentials',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 38,
            'endColumn' => 62,
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
 * Generate a hash of connector credentials for change detection.
 */',
        'startLine' => 487,
        'endLine' => 492,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway',
        'declaringClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'implementingClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
        'currentClassName' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
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