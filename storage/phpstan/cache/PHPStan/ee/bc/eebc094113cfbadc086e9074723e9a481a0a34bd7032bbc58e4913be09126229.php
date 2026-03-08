<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Console/Commands/MessengerGatewayCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\MessengerGatewayCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-244b64038ece739bfddcaa5f092977f579e39b4c7ab3af966a1215e0cde0cf77',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'filename' => '/Users/garethdaine/Code/agent/app/Console/Commands/MessengerGatewayCommand.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\MessengerGatewayCommand',
    'shortName' => 'MessengerGatewayCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Artisan command to run the messenger gateway supervisor.
 *
 * This command runs a long-lived PHP process that manages gateway workers
 * for local-mode messenger connectors (Slack Socket Mode, Telegram polling,
 * Discord Gateway).
 *
 * Designed to run alongside Horizon as a separate supervised process.
 *
 * Assumptions:
 * - ReactPHP EventLoop is available via container
 * - MessengerGatewayManager is configured and can boot workers
 * - Process will be managed by supervisor/systemd in production
 *
 * Signal Handling:
 * - SIGTERM: Graceful shutdown with drain timeout
 * - SIGINT: Same as SIGTERM (Ctrl+C)
 * - SIGUSR1: Output health status table
 *
 * Exit Codes:
 * - 0: Clean shutdown
 * - 1: Error during startup or drain timeout exceeded
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 35,
    'endLine' => 268,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
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
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'agent:messenger-gateway\'',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 42,
            'startTokenPos' => 55,
            'startFilePos' => 1158,
            'endTokenPos' => 55,
            'endFilePos' => 1182,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 53,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Run the messenger gateway supervisor for local-mode connectors\'',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 66,
            'startFilePos' => 1297,
            'endTokenPos' => 66,
            'endFilePos' => 1360,
          ),
        ),
        'docComment' => '/**
 * The console command description.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 94,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shouldShutdown' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'shouldShutdown',
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
            'startLine' => 54,
            'endLine' => 54,
            'startTokenPos' => 79,
            'startFilePos' => 1459,
            'endTokenPos' => 79,
            'endFilePos' => 1463,
          ),
        ),
        'docComment' => '/**
 * Whether shutdown has been requested.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shutdownClean' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'shutdownClean',
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
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 92,
            'startFilePos' => 1565,
            'endTokenPos' => 92,
            'endFilePos' => 1569,
          ),
        ),
        'docComment' => '/**
 * Whether shutdown completed successfully.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'loop' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'loop',
        'modifiers' => 4,
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
        'docComment' => '/**
 * The ReactPHP event loop.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'manager' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'manager',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * The gateway manager instance.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lockFile' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'name' => 'lockFile',
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
 * Path to the lock file for preventing duplicate processes.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 74,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 29,
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
      'handle' => 
      array (
        'name' => 'handle',
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
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 28,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'manager' => 
          array (
            'name' => 'manager',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Messenger\\Gateway\\MessengerGatewayManager',
                'isIdentifier' => false,
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
            'startColumn' => 49,
            'endColumn' => 80,
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
 * Execute the console command.
 */',
        'startLine' => 79,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'aliasName' => NULL,
      ),
      'installSignalHandlers' => 
      array (
        'name' => 'installSignalHandlers',
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
 * Install signal handlers for graceful shutdown and health output.
 */',
        'startLine' => 137,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'aliasName' => NULL,
      ),
      'initiateShutdown' => 
      array (
        'name' => 'initiateShutdown',
        'parameters' => 
        array (
          'signal' => 
          array (
            'name' => 'signal',
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
            'startLine' => 165,
            'endLine' => 165,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Initiate graceful shutdown sequence.
 */',
        'startLine' => 165,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'aliasName' => NULL,
      ),
      'outputBriefStatus' => 
      array (
        'name' => 'outputBriefStatus',
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
 * Output brief status to console (periodic).
 */',
        'startLine' => 211,
        'endLine' => 219,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'aliasName' => NULL,
      ),
      'outputHealthStatus' => 
      array (
        'name' => 'outputHealthStatus',
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
 * Output detailed health status table (SIGUSR1).
 */',
        'startLine' => 224,
        'endLine' => 246,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'aliasName' => NULL,
      ),
      'writeLockFile' => 
      array (
        'name' => 'writeLockFile',
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
 * Write PID to lock file.
 */',
        'startLine' => 251,
        'endLine' => 257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'aliasName' => NULL,
      ),
      'removeLockFile' => 
      array (
        'name' => 'removeLockFile',
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
 * Remove lock file on shutdown.
 */',
        'startLine' => 262,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'implementingClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
        'currentClassName' => 'App\\Console\\Commands\\MessengerGatewayCommand',
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