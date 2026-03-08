<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Gateway/Workers/DiscordGatewayWorker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Gateway\Workers\DiscordGatewayWorker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-fc22b7b8c48a442603cc0098cab6d63285c13c30f3f36d4f83eda4436ddfa182',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Gateway/Workers/DiscordGatewayWorker.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Gateway\\Workers',
    'name' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
    'shortName' => 'DiscordGatewayWorker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Gateway worker for Discord Gateway WebSocket v10.
 *
 * Discord Gateway allows the application to receive real-time events over a WebSocket
 * connection. This is the local mode alternative to webhook-based interactions.
 *
 * Gateway Protocol (v10):
 * 1. GET /gateway/bot to obtain Gateway URL and shard info
 * 2. Connect to wss://gateway.discord.gg/?v=10&encoding=json
 * 3. Receive HELLO (opcode 10) with heartbeat_interval
 * 4. Send IDENTIFY (opcode 2) with token and intents
 * 5. Receive READY (opcode 0, t=READY) with session_id and resume_gateway_url
 * 6. Start heartbeat loop at heartbeat_interval
 * 7. Track sequence numbers (s field) for resume capability
 * 8. On disconnect: attempt RESUME (opcode 6) with session_id and last sequence
 *
 * Opcodes:
 * 0 - Dispatch (receive events, includes \'t\' field with event name)
 * 1 - Heartbeat (send/receive to maintain connection)
 * 2 - Identify (send to authenticate)
 * 6 - Resume (send to resume session after disconnect)
 * 7 - Reconnect (receive, server requests reconnect)
 * 9 - Invalid Session (receive, session is invalid)
 * 10 - Hello (receive on connect with heartbeat_interval)
 * 11 - Heartbeat ACK (receive to confirm heartbeat)
 *
 * Required Intents:
 * - GUILDS (1 << 0) = 1
 * - GUILD_MESSAGES (1 << 9) = 512
 * - MESSAGE_CONTENT (1 << 15) = 32768 (privileged)
 * - DIRECT_MESSAGES (1 << 12) = 4096
 *
 * @see https://discord.com/developers/docs/topics/gateway
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 56,
    'endLine' => 867,
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
      'DISCORD_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'DISCORD_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://discord.com/api/v10\'',
          'attributes' => 
          array (
            'startLine' => 58,
            'endLine' => 58,
            'startTokenPos' => 105,
            'startFilePos' => 2184,
            'endTokenPos' => 105,
            'endFilePos' => 2212,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 58,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
      'GATEWAY_VERSION' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'GATEWAY_VERSION',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '10',
          'attributes' => 
          array (
            'startLine' => 60,
            'endLine' => 60,
            'startTokenPos' => 116,
            'startFilePos' => 2252,
            'endTokenPos' => 116,
            'endFilePos' => 2253,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 60,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'GATEWAY_ENCODING' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'GATEWAY_ENCODING',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'json\'',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 127,
            'startFilePos' => 2294,
            'endTokenPos' => 127,
            'endFilePos' => 2299,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'OP_DISPATCH' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_DISPATCH',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 65,
            'endLine' => 65,
            'startTokenPos' => 140,
            'startFilePos' => 2366,
            'endTokenPos' => 140,
            'endFilePos' => 2366,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 65,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'OP_HEARTBEAT' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_HEARTBEAT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 67,
            'startTokenPos' => 151,
            'startFilePos' => 2403,
            'endTokenPos' => 151,
            'endFilePos' => 2403,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'OP_IDENTIFY' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_IDENTIFY',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 69,
            'endLine' => 69,
            'startTokenPos' => 162,
            'startFilePos' => 2439,
            'endTokenPos' => 162,
            'endFilePos' => 2439,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'OP_RESUME' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_RESUME',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '6',
          'attributes' => 
          array (
            'startLine' => 71,
            'endLine' => 71,
            'startTokenPos' => 173,
            'startFilePos' => 2473,
            'endTokenPos' => 173,
            'endFilePos' => 2473,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'OP_RECONNECT' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_RECONNECT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '7',
          'attributes' => 
          array (
            'startLine' => 73,
            'endLine' => 73,
            'startTokenPos' => 184,
            'startFilePos' => 2510,
            'endTokenPos' => 184,
            'endFilePos' => 2510,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'OP_INVALID_SESSION' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_INVALID_SESSION',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '9',
          'attributes' => 
          array (
            'startLine' => 75,
            'endLine' => 75,
            'startTokenPos' => 195,
            'startFilePos' => 2553,
            'endTokenPos' => 195,
            'endFilePos' => 2553,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 75,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'OP_HELLO' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_HELLO',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '10',
          'attributes' => 
          array (
            'startLine' => 77,
            'endLine' => 77,
            'startTokenPos' => 206,
            'startFilePos' => 2586,
            'endTokenPos' => 206,
            'endFilePos' => 2587,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 77,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'OP_HEARTBEAT_ACK' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'OP_HEARTBEAT_ACK',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '11',
          'attributes' => 
          array (
            'startLine' => 79,
            'endLine' => 79,
            'startTokenPos' => 217,
            'startFilePos' => 2628,
            'endTokenPos' => 217,
            'endFilePos' => 2629,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 79,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'DEFAULT_INTENTS' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'DEFAULT_INTENTS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '37377',
          'attributes' => 
          array (
            'startLine' => 82,
            'endLine' => 82,
            'startTokenPos' => 230,
            'startFilePos' => 2754,
            'endTokenPos' => 230,
            'endFilePos' => 2758,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'INTERACTION_TYPE_APPLICATION_COMMAND_AUTOCOMPLETE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'INTERACTION_TYPE_APPLICATION_COMMAND_AUTOCOMPLETE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 84,
            'endLine' => 84,
            'startTokenPos' => 243,
            'startFilePos' => 2858,
            'endTokenPos' => 243,
            'endFilePos' => 2858,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 84,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 72,
      ),
      'INTERACTION_RESPONSE_TYPE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'INTERACTION_RESPONSE_TYPE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 86,
            'endLine' => 86,
            'startTokenPos' => 254,
            'startFilePos' => 2945,
            'endTokenPos' => 254,
            'endFilePos' => 2945,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 86,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 85,
      ),
      'INTERACTION_RESPONSE_TYPE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'INTERACTION_RESPONSE_TYPE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 88,
            'endLine' => 88,
            'startTokenPos' => 265,
            'startFilePos' => 3035,
            'endTokenPos' => 265,
            'endFilePos' => 3035,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 88,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 88,
      ),
    ),
    'immediateProperties' => 
    array (
      'connection' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 90,
            'endLine' => 90,
            'startTokenPos' => 277,
            'startFilePos' => 3076,
            'endTokenPos' => 277,
            'endFilePos' => 3079,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 90,
        'endLine' => 90,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 92,
            'endLine' => 92,
            'startTokenPos' => 288,
            'startFilePos' => 3124,
            'endTokenPos' => 290,
            'endFilePos' => 3155,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 94,
            'endLine' => 94,
            'startTokenPos' => 302,
            'startFilePos' => 3203,
            'endTokenPos' => 302,
            'endFilePos' => 3206,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 94,
        'endLine' => 94,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 96,
            'endLine' => 96,
            'startTokenPos' => 314,
            'startFilePos' => 3254,
            'endTokenPos' => 314,
            'endFilePos' => 3257,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 96,
        'endLine' => 96,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 98,
            'endLine' => 98,
            'startTokenPos' => 326,
            'startFilePos' => 3297,
            'endTokenPos' => 326,
            'endFilePos' => 3300,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 98,
        'endLine' => 98,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 100,
            'endLine' => 100,
            'startTokenPos' => 337,
            'startFilePos' => 3333,
            'endTokenPos' => 337,
            'endFilePos' => 3337,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 100,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'sessionId' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'sessionId',
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
            'startLine' => 103,
            'endLine' => 103,
            'startTokenPos' => 351,
            'startFilePos' => 3403,
            'endTokenPos' => 351,
            'endFilePos' => 3406,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'resumeGatewayUrl' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'resumeGatewayUrl',
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
            'startLine' => 105,
            'endLine' => 105,
            'startTokenPos' => 363,
            'startFilePos' => 3450,
            'endTokenPos' => 363,
            'endFilePos' => 3453,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 105,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lastSequence' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'lastSequence',
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 107,
            'endLine' => 107,
            'startTokenPos' => 375,
            'startFilePos' => 3490,
            'endTokenPos' => 375,
            'endFilePos' => 3493,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 107,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'heartbeatTimer' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'heartbeatTimer',
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
                  'name' => 'React\\EventLoop\\TimerInterface',
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
            'startLine' => 109,
            'endLine' => 109,
            'startTokenPos' => 387,
            'startFilePos' => 3543,
            'endTokenPos' => 387,
            'endFilePos' => 3546,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 109,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 51,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'heartbeatAckReceived' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'heartbeatAckReceived',
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
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 111,
            'endLine' => 111,
            'startTokenPos' => 398,
            'startFilePos' => 3591,
            'endTokenPos' => 398,
            'endFilePos' => 3594,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 111,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'heartbeatInterval' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'name' => 'heartbeatInterval',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '41.25',
          'attributes' => 
          array (
            'startLine' => 113,
            'endLine' => 113,
            'startTokenPos' => 409,
            'startFilePos' => 3637,
            'endTokenPos' => 409,
            'endFilePos' => 3641,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 113,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'webSocketConnectorFactory' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 118,
            'endLine' => 118,
            'startTokenPos' => 425,
            'startFilePos' => 3825,
            'endTokenPos' => 425,
            'endFilePos' => 3828,
          ),
        ),
        'docComment' => '/**
 * Factory for creating WebSocket connectors (injectable for testing).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 118,
        'endLine' => 118,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 121,
        'endLine' => 121,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 122,
        'endLine' => 122,
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
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 123,
        'endLine' => 123,
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
            'startLine' => 121,
            'endLine' => 121,
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
            'startLine' => 122,
            'endLine' => 122,
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
            'startLine' => 123,
            'endLine' => 123,
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
        'startLine' => 120,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 129,
            'endLine' => 129,
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
        'startLine' => 129,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 137,
        'endLine' => 161,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 166,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 186,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 194,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 219,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 231,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 257,
        'endLine' => 260,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'getGatewayInfo' => 
      array (
        'name' => 'getGatewayInfo',
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
                  'name' => 'array',
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
 * Get Gateway info from Discord\'s /gateway/bot endpoint.
 *
 * @return array{url: string, shards: int}|null
 */',
        'startLine' => 267,
        'endLine' => 313,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 318,
            'endLine' => 318,
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
        'startLine' => 318,
        'endLine' => 373,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 378,
            'endLine' => 378,
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
        'startLine' => 378,
        'endLine' => 417,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
                      'name' => 'array',
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
            'startLine' => 424,
            'endLine' => 424,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle HELLO (opcode 10) - extract heartbeat interval and send IDENTIFY.
 *
 * @param  array<string, mixed>|null  $data
 */',
        'startLine' => 424,
        'endLine' => 443,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleHeartbeatAck' => 
      array (
        'name' => 'handleHeartbeatAck',
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
 * Handle Heartbeat ACK (opcode 11).
 */',
        'startLine' => 448,
        'endLine' => 456,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleDispatch' => 
      array (
        'name' => 'handleDispatch',
        'parameters' => 
        array (
          'eventType' => 
          array (
            'name' => 'eventType',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 464,
            'endLine' => 464,
            'startColumn' => 37,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
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
                      'name' => 'array',
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
            'startLine' => 464,
            'endLine' => 464,
            'startColumn' => 57,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'fullPayload' => 
          array (
            'name' => 'fullPayload',
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
            'startLine' => 464,
            'endLine' => 464,
            'startColumn' => 71,
            'endColumn' => 88,
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
 * Handle DISPATCH (opcode 0) - process events.
 *
 * @param  array<string, mixed>|null  $data
 * @param  array<string, mixed>  $fullPayload
 */',
        'startLine' => 464,
        'endLine' => 475,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleReady' => 
      array (
        'name' => 'handleReady',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
                      'name' => 'array',
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
            'startLine' => 482,
            'endLine' => 482,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle READY event - store session info.
 *
 * @param  array<string, mixed>|null  $data
 */',
        'startLine' => 482,
        'endLine' => 501,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleResumed' => 
      array (
        'name' => 'handleResumed',
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
 * Handle RESUMED event - session resumed successfully.
 */',
        'startLine' => 506,
        'endLine' => 518,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleMessageCreate' => 
      array (
        'name' => 'handleMessageCreate',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
                      'name' => 'array',
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
            'startLine' => 526,
            'endLine' => 526,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fullPayload' => 
          array (
            'name' => 'fullPayload',
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
            'startLine' => 526,
            'endLine' => 526,
            'startColumn' => 56,
            'endColumn' => 73,
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
 * Handle MESSAGE_CREATE event.
 *
 * @param  array<string, mixed>|null  $data
 * @param  array<string, mixed>  $fullPayload
 */',
        'startLine' => 526,
        'endLine' => 548,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleInteractionCreate' => 
      array (
        'name' => 'handleInteractionCreate',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
                      'name' => 'array',
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
            'startLine' => 556,
            'endLine' => 556,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fullPayload' => 
          array (
            'name' => 'fullPayload',
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
            'startLine' => 556,
            'endLine' => 556,
            'startColumn' => 60,
            'endColumn' => 77,
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
 * Handle INTERACTION_CREATE event.
 *
 * @param  array<string, mixed>|null  $data
 * @param  array<string, mixed>  $fullPayload
 */',
        'startLine' => 556,
        'endLine' => 575,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'acknowledgeInteraction' => 
      array (
        'name' => 'acknowledgeInteraction',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
                      'name' => 'array',
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
            'startLine' => 582,
            'endLine' => 582,
            'startColumn' => 45,
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
 * Acknowledge Discord interactions so slash commands do not show timeout banners.
 *
 * @param  array<string, mixed>|null  $data
 */',
        'startLine' => 582,
        'endLine' => 622,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleReconnect' => 
      array (
        'name' => 'handleReconnect',
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
 * Handle RECONNECT (opcode 7) - server requests reconnection.
 */',
        'startLine' => 627,
        'endLine' => 642,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'handleInvalidSession' => 
      array (
        'name' => 'handleInvalidSession',
        'parameters' => 
        array (
          'resumable' => 
          array (
            'name' => 'resumable',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 649,
            'endLine' => 649,
            'startColumn' => 43,
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
 * Handle INVALID_SESSION (opcode 9).
 *
 * @param  mixed  $resumable  Whether the session can be resumed (boolean)
 */',
        'startLine' => 649,
        'endLine' => 677,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'sendIdentify' => 
      array (
        'name' => 'sendIdentify',
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
 * Send IDENTIFY (opcode 2).
 */',
        'startLine' => 682,
        'endLine' => 706,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'sendResume' => 
      array (
        'name' => 'sendResume',
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
 * Send RESUME (opcode 6).
 */',
        'startLine' => 711,
        'endLine' => 731,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'startHeartbeat' => 
      array (
        'name' => 'startHeartbeat',
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
 * Start the heartbeat timer.
 */',
        'startLine' => 736,
        'endLine' => 751,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'cancelHeartbeat' => 
      array (
        'name' => 'cancelHeartbeat',
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
 * Cancel the heartbeat timer.
 */',
        'startLine' => 756,
        'endLine' => 762,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'onHeartbeatTick' => 
      array (
        'name' => 'onHeartbeatTick',
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
 * Called on each heartbeat interval.
 */',
        'startLine' => 767,
        'endLine' => 782,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'sendHeartbeat' => 
      array (
        'name' => 'sendHeartbeat',
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
 * Send HEARTBEAT (opcode 1).
 */',
        'startLine' => 787,
        'endLine' => 800,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'aliasName' => NULL,
      ),
      'send' => 
      array (
        'name' => 'send',
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
            'startLine' => 807,
            'endLine' => 807,
            'startColumn' => 27,
            'endColumn' => 40,
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
 * Send a payload to the WebSocket.
 *
 * @param  array<string, mixed>  $payload
 */',
        'startLine' => 807,
        'endLine' => 814,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
        'startLine' => 819,
        'endLine' => 834,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
            'startLine' => 839,
            'endLine' => 839,
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
        'startLine' => 839,
        'endLine' => 849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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
 */',
        'startLine' => 854,
        'endLine' => 866,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Gateway\\Workers',
        'declaringClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'implementingClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
        'currentClassName' => 'App\\Messenger\\Gateway\\Workers\\DiscordGatewayWorker',
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