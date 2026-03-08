<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Http/Controllers/Messenger/MessengerHealthController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\Messenger\MessengerHealthController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-31745c4a380cedd7bbe1c3e1536f727fbf3ef78e4fdebd2148f962b88961a1fb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'filename' => '/Users/garethdaine/Code/agent/app/Http/Controllers/Messenger/MessengerHealthController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers\\Messenger',
    'name' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
    'shortName' => 'MessengerHealthController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Public health endpoint for monitoring tools.
 *
 * Returns HTTP 200 always with per-connector breakdown and aggregate summary.
 * Reports real worker state from gateway manager (runtime_state), not static config.
 *
 * No authentication required - designed for external monitoring systems.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 226,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\Controller',
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
      'index' => 
      array (
        'name' => 'index',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 27,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'checkDependencies' => 
      array (
        'name' => 'checkDependencies',
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
 * @return array<string, array{status: string, latency_ms: int|null, error: string|null}>
 */',
        'startLine' => 63,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'checkDatabase' => 
      array (
        'name' => 'checkDatabase',
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
 * @return array{status: string, latency_ms: int|null, error: string|null}
 */',
        'startLine' => 75,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'checkRedis' => 
      array (
        'name' => 'checkRedis',
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
 * @return array{status: string, latency_ms: int|null, error: string|null}
 */',
        'startLine' => 90,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'checkQueue' => 
      array (
        'name' => 'checkQueue',
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
 * @return array{status: string, latency_ms: int|null, error: string|null}
 */',
        'startLine' => 105,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'elapsedMs' => 
      array (
        'name' => 'elapsedMs',
        'parameters' => 
        array (
          'start' => 
          array (
            'name' => 'start',
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
            'startLine' => 117,
            'endLine' => 117,
            'startColumn' => 32,
            'endColumn' => 41,
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
        'docComment' => NULL,
        'startLine' => 117,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'determineAggregateStatus' => 
      array (
        'name' => 'determineAggregateStatus',
        'parameters' => 
        array (
          'summary' => 
          array (
            'name' => 'summary',
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
            'startLine' => 127,
            'endLine' => 127,
            'startColumn' => 47,
            'endColumn' => 60,
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
 * Determine the aggregate health status based on connector states.
 *
 * @param  array{total_connectors: int, connected: int, reconnecting: int, disconnected: int, error: int}  $summary
 */',
        'startLine' => 127,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'dashboard' => 
      array (
        'name' => 'dashboard',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Inertia\\Response',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Health dashboard page for authenticated users.
 *
 * Renders an Inertia page with connector health overview, gateway status,
 * and queue health metrics.
 */',
        'startLine' => 159,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'checkGatewayProcess' => 
      array (
        'name' => 'checkGatewayProcess',
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
 * Check if the gateway process is running via heartbeat cache.
 */',
        'startLine' => 200,
        'endLine' => 210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'aliasName' => NULL,
      ),
      'getQueueHealth' => 
      array (
        'name' => 'getQueueHealth',
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
 * Get queue health metrics from Horizon.
 *
 * @return array<string, mixed>
 */',
        'startLine' => 217,
        'endLine' => 225,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers\\Messenger',
        'declaringClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'implementingClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
        'currentClassName' => 'App\\Http\\Controllers\\Messenger\\MessengerHealthController',
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