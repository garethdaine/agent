<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Bus/Dispatcher.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Bus\Dispatcher
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-5a9a28d61536b267c8c3fa9c861db0228496cf1ecc97fc354268e6ccd3a82c50-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Bus\\Dispatcher',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Bus/Dispatcher.php',
      ),
    ),
    'namespace' => 'Illuminate\\Bus',
    'name' => 'Illuminate\\Bus\\Dispatcher',
    'shortName' => 'Dispatcher',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 316,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Bus\\QueueingDispatcher',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'container' => 
      array (
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'name' => 'container',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The container implementation.
 *
 * @var \\Illuminate\\Contracts\\Container\\Container
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pipeline' => 
      array (
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'name' => 'pipeline',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The pipeline instance for the bus.
 *
 * @var \\Illuminate\\Pipeline\\Pipeline
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pipes' => 
      array (
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'name' => 'pipes',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 94,
            'startFilePos' => 885,
            'endTokenPos' => 95,
            'endFilePos' => 886,
          ),
        ),
        'docComment' => '/**
 * The pipes to send commands through before dispatching.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'handlers' => 
      array (
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'name' => 'handlers',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 106,
            'startFilePos' => 1025,
            'endTokenPos' => 107,
            'endFilePos' => 1026,
          ),
        ),
        'docComment' => '/**
 * The command to handler mapping for non-self-handling events.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queueResolver' => 
      array (
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'name' => 'queueResolver',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The queue resolver callback.
 *
 * @var \\Closure|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowsDispatchingAfterResponses' => 
      array (
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'name' => 'allowsDispatchingAfterResponses',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 125,
            'startFilePos' => 1295,
            'endTokenPos' => 125,
            'endFilePos' => 1298,
          ),
        ),
        'docComment' => '/**
 * Indicates if dispatching after response is disabled.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 54,
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
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'queueResolver' => 
          array (
            'name' => 'queueResolver',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 64,
                'endLine' => 64,
                'startTokenPos' => 148,
                'startFilePos' => 1447,
                'endTokenPos' => 148,
                'endFilePos' => 1450,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 55,
            'endColumn' => 84,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new command dispatcher instance.
 */',
        'startLine' => 64,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'dispatch' => 
      array (
        'name' => 'dispatch',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dispatch a command to its appropriate handler.
 *
 * @param  mixed  $command
 * @return mixed
 */',
        'startLine' => 77,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'dispatchSync' => 
      array (
        'name' => 'dispatchSync',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'handler' => 
          array (
            'name' => 'handler',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 93,
                'endLine' => 93,
                'startTokenPos' => 251,
                'startFilePos' => 2260,
                'endTokenPos' => 251,
                'endFilePos' => 2263,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 44,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dispatch a command to its appropriate handler in the current process.
 *
 * Queueable jobs will be dispatched to the "sync" queue.
 *
 * @param  mixed  $command
 * @param  mixed  $handler
 * @return mixed
 */',
        'startLine' => 93,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'dispatchNow' => 
      array (
        'name' => 'dispatchNow',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'handler' => 
          array (
            'name' => 'handler',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 111,
                'endLine' => 111,
                'startTokenPos' => 332,
                'startFilePos' => 2837,
                'endTokenPos' => 332,
                'endFilePos' => 2840,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 43,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dispatch a command to its appropriate handler in the current process without using the synchronous queue.
 *
 * @param  mixed  $command
 * @param  mixed  $handler
 * @return mixed
 */',
        'startLine' => 111,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'findBatch' => 
      array (
        'name' => 'findBatch',
        'parameters' => 
        array (
          'batchId' => 
          array (
            'name' => 'batchId',
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
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 31,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attempt to find the batch with the given ID.
 *
 * @return \\Illuminate\\Bus\\Batch|null
 */',
        'startLine' => 141,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'batch' => 
      array (
        'name' => 'batch',
        'parameters' => 
        array (
          'jobs' => 
          array (
            'name' => 'jobs',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 27,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new batch of queueable jobs.
 *
 * @param  \\Illuminate\\Support\\Collection|mixed  $jobs
 * @return \\Illuminate\\Bus\\PendingBatch
 */',
        'startLine' => 152,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'chain' => 
      array (
        'name' => 'chain',
        'parameters' => 
        array (
          'jobs' => 
          array (
            'name' => 'jobs',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 163,
                'endLine' => 163,
                'startTokenPos' => 654,
                'startFilePos' => 4499,
                'endTokenPos' => 654,
                'endFilePos' => 4502,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 27,
            'endColumn' => 38,
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
 * Create a new chain of queueable jobs.
 *
 * @param  \\Illuminate\\Support\\Collection|array|null  $jobs
 * @return \\Illuminate\\Foundation\\Bus\\PendingChain
 */',
        'startLine' => 163,
        'endLine' => 169,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'hasCommandHandler' => 
      array (
        'name' => 'hasCommandHandler',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 177,
            'endLine' => 177,
            'startColumn' => 39,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given command has a handler.
 *
 * @param  mixed  $command
 * @return bool
 */',
        'startLine' => 177,
        'endLine' => 180,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'getCommandHandler' => 
      array (
        'name' => 'getCommandHandler',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 188,
            'endLine' => 188,
            'startColumn' => 39,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the handler for a command.
 *
 * @param  mixed  $command
 * @return mixed
 */',
        'startLine' => 188,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'commandShouldBeQueued' => 
      array (
        'name' => 'commandShouldBeQueued',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 203,
            'endLine' => 203,
            'startColumn' => 46,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given command should be queued.
 *
 * @param  mixed  $command
 * @return bool
 */',
        'startLine' => 203,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'dispatchToQueue' => 
      array (
        'name' => 'dispatchToQueue',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 216,
            'endLine' => 216,
            'startColumn' => 37,
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
        'docComment' => '/**
 * Dispatch a command to its appropriate handler behind a queue.
 *
 * @param  mixed  $command
 * @return mixed
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 216,
        'endLine' => 231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'pushCommandToQueue' => 
      array (
        'name' => 'pushCommandToQueue',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 240,
            'endLine' => 240,
            'startColumn' => 43,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 240,
            'endLine' => 240,
            'startColumn' => 51,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Push the command onto the given queue instance.
 *
 * @param  \\Illuminate\\Contracts\\Queue\\Queue  $queue
 * @param  mixed  $command
 * @return mixed
 */',
        'startLine' => 240,
        'endLine' => 247,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'dispatchAfterResponse' => 
      array (
        'name' => 'dispatchAfterResponse',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 256,
            'endLine' => 256,
            'startColumn' => 43,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'handler' => 
          array (
            'name' => 'handler',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 256,
                'endLine' => 256,
                'startTokenPos' => 1023,
                'startFilePos' => 6928,
                'endTokenPos' => 1023,
                'endFilePos' => 6931,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 256,
            'endLine' => 256,
            'startColumn' => 53,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dispatch a command to its appropriate handler after the current process.
 *
 * @param  mixed  $command
 * @param  mixed  $handler
 * @return void
 */',
        'startLine' => 256,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'pipeThrough' => 
      array (
        'name' => 'pipeThrough',
        'parameters' => 
        array (
          'pipes' => 
          array (
            'name' => 'pipes',
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
            'startLine' => 274,
            'endLine' => 274,
            'startColumn' => 33,
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
        'docComment' => '/**
 * Set the pipes through which commands should be piped before dispatching.
 *
 * @return $this
 */',
        'startLine' => 274,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'map' => 
      array (
        'name' => 'map',
        'parameters' => 
        array (
          'map' => 
          array (
            'name' => 'map',
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
            'startLine' => 286,
            'endLine' => 286,
            'startColumn' => 25,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Map a command to a handler.
 *
 * @return $this
 */',
        'startLine' => 286,
        'endLine' => 291,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'withDispatchingAfterResponses' => 
      array (
        'name' => 'withDispatchingAfterResponses',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Allow dispatching after responses.
 *
 * @return $this
 */',
        'startLine' => 298,
        'endLine' => 303,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
        'aliasName' => NULL,
      ),
      'withoutDispatchingAfterResponses' => 
      array (
        'name' => 'withoutDispatchingAfterResponses',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable dispatching after responses.
 *
 * @return $this
 */',
        'startLine' => 310,
        'endLine' => 315,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Bus',
        'declaringClassName' => 'Illuminate\\Bus\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Bus\\Dispatcher',
        'currentClassName' => 'Illuminate\\Bus\\Dispatcher',
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