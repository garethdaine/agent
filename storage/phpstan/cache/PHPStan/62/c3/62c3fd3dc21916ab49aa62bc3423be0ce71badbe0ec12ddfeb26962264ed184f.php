<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Gateway/Workers/TelegramPollingWorker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Gateway\Workers\TelegramPollingWorker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-d4d3c38903fddab01feca6cc3ed605a96cb1a13e957f1014b955d2e818f1e772',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Gateway/Workers/TelegramPollingWorker.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Gateway\\Workers',
    'name' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
    'shortName' => 'TelegramPollingWorker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Gateway worker for Telegram Bot API long-polling via getUpdates.
 *
 * Long-polling allows the application to receive updates from Telegram without
 * requiring a public webhook URL. This is useful for local development or
 * environments without public internet access.
 *
 * Connection Flow:
 * 1. Call deleteWebhook to clear any existing webhook (required before getUpdates)
 * 2. Call getUpdates with offset, timeout, and allowed_updates parameters
 * 3. Process returned updates array
 * 4. Update offset to max(update_id) + 1 after processing
 * 5. Continue polling loop until stopped
 *
 * Token Requirements:
 * - Bot token: {numeric_bot_id}:{alphanumeric_secret}
 *
 * @see https://core.telegram.org/bots/api#getupdates
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 37,
    'endLine' => 408,
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
      'TELEGRAM_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'name' => 'TELEGRAM_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://api.telegram.org\'',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 85,
            'startFilePos' => 1346,
            'endTokenPos' => 85,
            'endFilePos' => 1371,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 65,
      ),
      'POLL_TIMEOUT' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'name' => 'POLL_TIMEOUT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '30',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 98,
            'startFilePos' => 1517,
            'endTokenPos' => 98,
            'endFilePos' => 1518,
          ),
        ),
        'docComment' => '/**
 * Long-poll timeout in seconds.
 * Telegram recommends 30 seconds for long-polling.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'ALLOWED_UPDATES' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'name' => 'ALLOWED_UPDATES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'message\', \'callback_query\']',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 111,
            'startFilePos' => 1654,
            'endTokenPos' => 116,
            'endFilePos' => 1682,
          ),
        ),
        'docComment' => '/**
 * Update types to receive via getUpdates.
 *
 * @var array<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 66,
      ),
    ),
    'immediateProperties' => 
    array (
      'offset' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'name' => 'offset',
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
            'startLine' => 54,
            'endLine' => 54,
            'startTokenPos' => 127,
            'startFilePos' => 1712,
            'endTokenPos' => 127,
            'endFilePos' => 1712,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'running' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'name' => 'running',
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
            'startTokenPos' => 138,
            'startFilePos' => 1744,
            'endTokenPos' => 138,
            'endFilePos' => 1748,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'draining' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
            'startLine' => 58,
            'endLine' => 58,
            'startTokenPos' => 149,
            'startFilePos' => 1781,
            'endTokenPos' => 149,
            'endFilePos' => 1785,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 58,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'status' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
            'startLine' => 60,
            'endLine' => 60,
            'startTokenPos' => 160,
            'startFilePos' => 1830,
            'endTokenPos' => 162,
            'endFilePos' => 1861,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 60,
        'endLine' => 60,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 174,
            'startFilePos' => 1909,
            'endTokenPos' => 174,
            'endFilePos' => 1912,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 186,
            'startFilePos' => 1960,
            'endTokenPos' => 186,
            'endFilePos' => 1963,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
            'startLine' => 66,
            'endLine' => 66,
            'startTokenPos' => 198,
            'startFilePos' => 2003,
            'endTokenPos' => 198,
            'endFilePos' => 2006,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 66,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'account' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 69,
        'endLine' => 69,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 70,
        'endLine' => 70,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 71,
        'endLine' => 71,
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
            'startLine' => 69,
            'endLine' => 69,
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
            'startLine' => 70,
            'endLine' => 70,
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
            'startLine' => 71,
            'endLine' => 71,
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
        'startLine' => 68,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 77,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'endLine' => 122,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 127,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 135,
        'endLine' => 147,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
 */',
        'startLine' => 152,
        'endLine' => 159,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 164,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 190,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'getOffset' => 
      array (
        'name' => 'getOffset',
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
 * Get the current update offset.
 */',
        'startLine' => 198,
        'endLine' => 201,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'isRunning' => 
      array (
        'name' => 'isRunning',
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
 * Check if the worker is currently running.
 */',
        'startLine' => 206,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'isDraining' => 
      array (
        'name' => 'isDraining',
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
 * Check if the worker is draining.
 */',
        'startLine' => 214,
        'endLine' => 217,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'deleteWebhook' => 
      array (
        'name' => 'deleteWebhook',
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
 * Delete any existing webhook before starting long-polling.
 *
 * This is required because Telegram only allows either webhook or getUpdates,
 * not both simultaneously.
 */',
        'startLine' => 225,
        'endLine' => 260,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'poll' => 
      array (
        'name' => 'poll',
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
 * Poll for updates via getUpdates API.
 */',
        'startLine' => 265,
        'endLine' => 313,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'processUpdates' => 
      array (
        'name' => 'processUpdates',
        'parameters' => 
        array (
          'updates' => 
          array (
            'name' => 'updates',
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
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 37,
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
 * Process updates received from getUpdates.
 *
 * @param  array<int, array<string, mixed>>  $updates
 */',
        'startLine' => 320,
        'endLine' => 341,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'dispatchUpdate' => 
      array (
        'name' => 'dispatchUpdate',
        'parameters' => 
        array (
          'update' => 
          array (
            'name' => 'update',
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
            'startLine' => 348,
            'endLine' => 348,
            'startColumn' => 37,
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
 * Dispatch update to ProcessInboundMessage job.
 *
 * @param  array<string, mixed>  $update
 */',
        'startLine' => 348,
        'endLine' => 360,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
        'startLine' => 365,
        'endLine' => 381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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
            'startLine' => 386,
            'endLine' => 386,
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
        'startLine' => 386,
        'endLine' => 397,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'aliasName' => NULL,
      ),
      'apiUrl' => 
      array (
        'name' => 'apiUrl',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
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
            'startLine' => 402,
            'endLine' => 402,
            'startColumn' => 29,
            'endColumn' => 42,
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
 * Build Telegram API URL for a given method.
 */',
        'startLine' => 402,
        'endLine' => 407,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\TelegramPollingWorker',
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