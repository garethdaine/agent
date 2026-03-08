<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Gateway/Contracts/GatewayWorkerInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Gateway\Contracts\GatewayWorkerInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-77dde6fb562fb44a6bbe66fc00eac961a4a0ecd5ddc8657ebfd4795c73003190',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Gateway/Contracts/GatewayWorkerInterface.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Gateway\\Contracts',
    'name' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
    'shortName' => 'GatewayWorkerInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Interface for gateway workers that manage real-time connections to messenger providers.
 *
 * Gateway workers are responsible for:
 * - Establishing and maintaining persistent connections (WebSocket, long-polling)
 * - Receiving inbound events/messages from the provider
 * - Dispatching events to the processing pipeline
 * - Managing connection lifecycle (start, stop, reconnect)
 * - Reporting health status for monitoring
 *
 * Implementations:
 * - SlackSocketWorker: WebSocket connection via Socket Mode API
 * - TelegramPollingWorker: Long-polling via getUpdates API
 * - DiscordGatewayWorker: WebSocket connection via Discord Gateway
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 101,
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
 * Initialize and start the worker connection.
 *
 * This method should:
 * - Establish the initial connection to the provider
 * - Begin receiving events
 * - Set up heartbeat/ping-pong mechanisms as required
 * - Transition state to Connected on success
 *
 * @throws \\RuntimeException If connection cannot be established
 */',
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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
 * Gracefully stop the worker.
 *
 * This method should:
 * - Stop accepting new events
 * - Close the connection cleanly
 * - Transition state to Disconnected
 *
 * This method should NOT wait for in-flight messages to complete.
 * Use drain() before stop() for graceful shutdown.
 */',
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 33,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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
 * Get the current health status of the worker.
 *
 * Returns the high-level connection state. For detailed diagnostics,
 * use getHealthMetadata().
 */',
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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
 * Force a reconnection to the provider.
 *
 * This method should:
 * - Close the existing connection if any
 * - Attempt to establish a new connection
 * - Use the ReconnectionStrategy for backoff timing if called repeatedly
 * - Transition state to Reconnecting during the process
 *
 * Use this when credentials are rotated or when the connection
 * is suspected to be unhealthy.
 */',
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 38,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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
 * Get detailed health metadata for monitoring and diagnostics.
 *
 * Returns additional context beyond the status enum, including:
 * - Last event timestamp
 * - Connection uptime
 * - Error messages if in error state
 */',
        'startLine' => 83,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 62,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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
 * Drain in-flight messages before stopping.
 *
 * This method should:
 * - Stop accepting new events
 * - Wait for currently processing messages to complete
 * - Respect a timeout (typically from config)
 *
 * Called before stop() during graceful shutdown or credential rotation.
 */',
        'startLine' => 95,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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
 * Get the connector account ID this worker is associated with.
 */',
        'startLine' => 100,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Contracts',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
        'currentClassName' => 'App\\Messenger\\Gateway\\Contracts\\GatewayWorkerInterface',
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