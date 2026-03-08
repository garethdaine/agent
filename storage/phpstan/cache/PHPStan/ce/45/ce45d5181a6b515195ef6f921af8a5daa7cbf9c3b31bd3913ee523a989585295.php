<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Gateway/Workers/SlackSocketWorker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Gateway\Workers\SlackSocketWorker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-7831272207570800c933f058b869bc0a0e33e11a74668882a2cfc1ef879a572f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Gateway/Workers/SlackSocketWorker.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Gateway\\Workers',
    'name' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
    'shortName' => 'SlackSocketWorker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Gateway worker for Slack Socket Mode API v2.
 *
 * Socket Mode allows the application to receive events from Slack over a WebSocket
 * connection instead of requiring a public webhook URL. This is useful for local
 * development or environments without public internet access.
 *
 * Connection Flow:
 * 1. Call apps.connections.open with app-level token to get WebSocket URL
 * 2. Connect to the returned wss:// URL
 * 3. Receive \'hello\' event confirming connection
 * 4. Process events and acknowledge with envelope_id within 3 seconds
 * 5. Respond to \'disconnect\' events by reconnecting
 *
 * Token Requirements:
 * - App token (xapp-*): For Socket Mode WebSocket connection
 * - Bot token (xoxb-*): For API calls (sending messages, etc.)
 *
 * @see https://api.slack.com/apis/connections/socket
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 41,
    'endLine' => 589,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'SLACK_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'SLACK_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://slack.com/api\'',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 100,
            'startFilePos' => 1500,
            'endTokenPos' => 100,
            'endFilePos' => 1522,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 59,
      ),
    ),
    'immediateProperties' => 
    array (
      'connection' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'connection',
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
                  'name' => 'Ratchet\\Client\\WebSocket',
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
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 112,
            'startFilePos' => 1563,
            'endTokenPos' => 112,
            'endFilePos' => 1566,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'status' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'status',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Messenger\\Gateway\\Enums\\WorkerHealthStatus',
            'isIdentifier' => false,
          ),
        ),
        'default' => 
        array (
          'code' => '\\App\\Messenger\\Gateway\\Enums\\WorkerHealthStatus::Disconnected',
          'attributes' => 
          array (
            'startLine' => 47,
            'endLine' => 47,
            'startTokenPos' => 123,
            'startFilePos' => 1611,
            'endTokenPos' => 125,
            'endFilePos' => 1642,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 74,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'connectedAt' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'connectedAt',
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
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 137,
            'startFilePos' => 1690,
            'endTokenPos' => 137,
            'endFilePos' => 1693,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lastEventAt' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'lastEventAt',
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
            'startLine' => 51,
            'endLine' => 51,
            'startTokenPos' => 149,
            'startFilePos' => 1741,
            'endTokenPos' => 149,
            'endFilePos' => 1744,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'errorMessage' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'errorMessage',
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 53,
            'endLine' => 53,
            'startTokenPos' => 161,
            'startFilePos' => 1784,
            'endTokenPos' => 161,
            'endFilePos' => 1787,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'draining' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'draining',
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
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 172,
            'startFilePos' => 1820,
            'endTokenPos' => 172,
            'endFilePos' => 1824,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pendingOperations' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'pendingOperations',
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
            'startLine' => 60,
            'endLine' => 60,
            'startTokenPos' => 185,
            'startFilePos' => 1957,
            'endTokenPos' => 185,
            'endFilePos' => 1957,
          ),
        ),
        'docComment' => '/**
 * Count of in-flight operations (dispatched jobs awaiting completion).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 60,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'drainTimeoutSeconds' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'drainTimeoutSeconds',
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
          'code' => '30',
          'attributes' => 
          array (
            'startLine' => 65,
            'endLine' => 65,
            'startTokenPos' => 198,
            'startFilePos' => 2116,
            'endTokenPos' => 198,
            'endFilePos' => 2117,
          ),
        ),
        'docComment' => '/**
 * Timeout in seconds for graceful drain (configurable via messenger.gateway.shutdown_timeout).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 65,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'webSocketConnectorFactory' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'webSocketConnectorFactory',
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
            'startLine' => 70,
            'endLine' => 70,
            'startTokenPos' => 212,
            'startFilePos' => 2262,
            'endTokenPos' => 212,
            'endFilePos' => 2265,
          ),
        ),
        'docComment' => '/**
 * Factory for creating WebSocket connectors (injectable for testing).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 70,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 55,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'account' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'account',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\ConnectorAccount',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 9,
        'endColumn' => 50,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'loop' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
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
        'startLine' => 74,
        'endLine' => 74,
        'startColumn' => 9,
        'endColumn' => 44,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'reconnection' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'name' => 'reconnection',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 75,
        'endLine' => 75,
        'startColumn' => 9,
        'endColumn' => 59,
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 9,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'reconnection' => 
          array (
            'name' => 'reconnection',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Messenger\\Gateway\\ReconnectionStrategy',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 9,
            'endColumn' => 59,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 72,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'setWebSocketConnectorFactory' => 
      array (
        'name' => 'setWebSocketConnectorFactory',
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
            'startLine' => 81,
            'endLine' => 81,
            'startColumn' => 50,
            'endColumn' => 65,
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
 * Inject a custom WebSocket connector factory for testing.
 */',
        'startLine' => 81,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'start' => 
      array (
        'name' => 'start',
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
 * {@inheritdoc}
 */',
        'startLine' => 89,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'stop' => 
      array (
        'name' => 'stop',
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
 * {@inheritdoc}
 */',
        'startLine' => 113,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'health' => 
      array (
        'name' => 'health',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Messenger\\Gateway\\Enums\\WorkerHealthStatus',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * {@inheritdoc}
 */',
        'startLine' => 131,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'reconnect' => 
      array (
        'name' => 'reconnect',
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
 * {@inheritdoc}
 */',
        'startLine' => 139,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'drain' => 
      array (
        'name' => 'drain',
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
 * {@inheritdoc}
 *
 * Gracefully drains the worker by:
 * 1. Setting the draining flag to stop accepting new events
 * 2. Polling every 100ms to check if all pending operations have completed
 * 3. Stopping the loop when operations complete or timeout is reached
 */',
        'startLine' => 166,
        'endLine' => 201,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'getHealthMetadata' => 
      array (
        'name' => 'getHealthMetadata',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Messenger\\Gateway\\DTOs\\WorkerHealthMetadata',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * {@inheritdoc}
 */',
        'startLine' => 206,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'getConnectorAccountId' => 
      array (
        'name' => 'getConnectorAccountId',
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
        'startLine' => 232,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'getWebSocketUrl' => 
      array (
        'name' => 'getWebSocketUrl',
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
 * Get WebSocket URL from Slack\'s apps.connections.open API.
 */',
        'startLine' => 240,
        'endLine' => 290,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'connectWebSocket' => 
      array (
        'name' => 'connectWebSocket',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
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
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 39,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Connect to the WebSocket URL.
 */',
        'startLine' => 295,
        'endLine' => 349,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'handleMessage' => 
      array (
        'name' => 'handleMessage',
        'parameters' => 
        array (
          'msg' => 
          array (
            'name' => 'msg',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Ratchet\\RFC6455\\Messaging\\MessageInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 354,
            'endLine' => 354,
            'startColumn' => 36,
            'endColumn' => 56,
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
 * Handle incoming WebSocket message.
 */',
        'startLine' => 354,
        'endLine' => 381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'handleHello' => 
      array (
        'name' => 'handleHello',
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
            'startLine' => 388,
            'endLine' => 388,
            'startColumn' => 34,
            'endColumn' => 44,
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
 * Handle \'hello\' event - connection confirmed.
 *
 * @param  array<string, mixed>  $data
 */',
        'startLine' => 388,
        'endLine' => 402,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'handleDisconnect' => 
      array (
        'name' => 'handleDisconnect',
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
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 39,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle \'disconnect\' event - server requests reconnection.
 *
 * @param  array<string, mixed>  $data
 */',
        'startLine' => 409,
        'endLine' => 428,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'handlePing' => 
      array (
        'name' => 'handlePing',
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
            'startLine' => 435,
            'endLine' => 435,
            'startColumn' => 33,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle \'ping\' event - respond with acknowledgment.
 *
 * @param  array<string, mixed>  $data
 */',
        'startLine' => 435,
        'endLine' => 444,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'handleEvent' => 
      array (
        'name' => 'handleEvent',
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
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 34,
            'endColumn' => 44,
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
 * Handle other events (events_api, interactive, etc.).
 *
 * @param  array<string, mixed>  $data
 */',
        'startLine' => 451,
        'endLine' => 479,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'dispatchEvent' => 
      array (
        'name' => 'dispatchEvent',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
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
            'startLine' => 496,
            'endLine' => 496,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dispatch event to ProcessInboundMessage job.
 *
 * ProcessInboundMessage handles:
 * - Parsing the raw payload via the SlackAdapter
 * - Identity link validation
 * - Session management
 * - Message creation with idempotency
 * - Dispatching to ProcessChatIntent
 *
 * The pending operations counter is incremented before dispatch and
 * decremented after, ensuring graceful drain waits for all dispatches.
 *
 * @param  array<string, mixed>  $payload
 */',
        'startLine' => 496,
        'endLine' => 515,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'sendAcknowledgment' => 
      array (
        'name' => 'sendAcknowledgment',
        'parameters' => 
        array (
          'envelopeId' => 
          array (
            'name' => 'envelopeId',
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
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 41,
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
 * Send acknowledgment for an envelope.
 */',
        'startLine' => 520,
        'endLine' => 533,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'scheduleReconnect' => 
      array (
        'name' => 'scheduleReconnect',
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
 * Schedule a reconnection attempt with exponential backoff.
 */',
        'startLine' => 538,
        'endLine' => 554,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'setError' => 
      array (
        'name' => 'setError',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
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
            'startLine' => 559,
            'endLine' => 559,
            'startColumn' => 31,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set error state with message.
 */',
        'startLine' => 559,
        'endLine' => 569,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'aliasName' => NULL,
      ),
      'createWebSocketConnector' => 
      array (
        'name' => 'createWebSocketConnector',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'callable',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a WebSocket connector.
 *
 * Uses the injected factory for testing or creates a real connector.
 */',
        'startLine' => 576,
        'endLine' => 588,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\SlackSocketWorker',
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