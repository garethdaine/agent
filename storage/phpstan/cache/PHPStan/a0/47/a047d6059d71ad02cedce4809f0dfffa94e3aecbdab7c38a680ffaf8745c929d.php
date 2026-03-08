<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../react/event-loop/src/Loop.php-PHPStan\BetterReflection\Reflection\ReflectionClass-React\EventLoop\Loop
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-3ff92e61320c7335969b55624ee30eb88c37ef3fdd9356b6dc93fa4a3d62f11e-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'React\\EventLoop\\Loop',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../react/event-loop/src/Loop.php',
      ),
    ),
    'namespace' => 'React\\EventLoop',
    'name' => 'React\\EventLoop\\Loop',
    'shortName' => 'Loop',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The `Loop` class exists as a convenient way to get the currently relevant loop
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 266,
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
      'instance' => 
      array (
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'name' => 'instance',
        'modifiers' => 20,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var ?LoopInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stopped' => 
      array (
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'name' => 'stopped',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 36,
            'startFilePos' => 269,
            'endTokenPos' => 36,
            'endFilePos' => 273,
          ),
        ),
        'docComment' => '/** @var bool */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 36,
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
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the event loop.
 * When no loop is set, it will call the factory to create one.
 *
 * This method always returns an instance implementing `LoopInterface`,
 * the actual event loop implementation is an implementation detail.
 *
 * This method is the preferred way to get the event loop and using
 * Factory::create has been deprecated.
 *
 * @return LoopInterface
 */',
        'startLine' => 30,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'set' => 
      array (
        'name' => 'set',
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
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 32,
            'endColumn' => 50,
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
 * Internal undocumented method, behavior might change or throw in the
 * future. Use with caution and at your own risk.
 *
 * @internal
 * @return void
 */',
        'startLine' => 70,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'addReadStream' => 
      array (
        'name' => 'addReadStream',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 51,
            'endColumn' => 59,
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
 * [Advanced] Register a listener to be notified when a stream is ready to read.
 *
 * @param resource $stream
 * @param callable $listener
 * @return void
 * @throws \\Exception
 * @see LoopInterface::addReadStream()
 */',
        'startLine' => 84,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'addWriteStream' => 
      array (
        'name' => 'addWriteStream',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 52,
            'endColumn' => 60,
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
 * [Advanced] Register a listener to be notified when a stream is ready to write.
 *
 * @param resource $stream
 * @param callable $listener
 * @return void
 * @throws \\Exception
 * @see LoopInterface::addWriteStream()
 */',
        'startLine' => 102,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'removeReadStream' => 
      array (
        'name' => 'removeReadStream',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 118,
            'endLine' => 118,
            'startColumn' => 45,
            'endColumn' => 51,
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
 * Remove the read event listener for the given stream.
 *
 * @param resource $stream
 * @return void
 * @see LoopInterface::removeReadStream()
 */',
        'startLine' => 118,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'removeWriteStream' => 
      array (
        'name' => 'removeWriteStream',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 132,
            'endLine' => 132,
            'startColumn' => 46,
            'endColumn' => 52,
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
 * Remove the write event listener for the given stream.
 *
 * @param resource $stream
 * @return void
 * @see LoopInterface::removeWriteStream()
 */',
        'startLine' => 132,
        'endLine' => 137,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'addTimer' => 
      array (
        'name' => 'addTimer',
        'parameters' => 
        array (
          'interval' => 
          array (
            'name' => 'interval',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 147,
            'endLine' => 147,
            'startColumn' => 37,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 147,
            'endLine' => 147,
            'startColumn' => 48,
            'endColumn' => 56,
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
 * Enqueue a callback to be invoked once after the given interval.
 *
 * @param float $interval
 * @param callable $callback
 * @return TimerInterface
 * @see LoopInterface::addTimer()
 */',
        'startLine' => 147,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'addPeriodicTimer' => 
      array (
        'name' => 'addPeriodicTimer',
        'parameters' => 
        array (
          'interval' => 
          array (
            'name' => 'interval',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 164,
            'endLine' => 164,
            'startColumn' => 45,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 164,
            'endLine' => 164,
            'startColumn' => 56,
            'endColumn' => 64,
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
 * Enqueue a callback to be invoked repeatedly after the given interval.
 *
 * @param float $interval
 * @param callable $callback
 * @return TimerInterface
 * @see LoopInterface::addPeriodicTimer()
 */',
        'startLine' => 164,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'cancelTimer' => 
      array (
        'name' => 'cancelTimer',
        'parameters' => 
        array (
          'timer' => 
          array (
            'name' => 'timer',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'React\\EventLoop\\TimerInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 180,
            'endLine' => 180,
            'startColumn' => 40,
            'endColumn' => 60,
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
 * Cancel a pending timer.
 *
 * @param TimerInterface $timer
 * @return void
 * @see LoopInterface::cancelTimer()
 */',
        'startLine' => 180,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'futureTick' => 
      array (
        'name' => 'futureTick',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 194,
            'endLine' => 194,
            'startColumn' => 39,
            'endColumn' => 47,
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
 * Schedule a callback to be invoked on a future tick of the event loop.
 *
 * @param callable $listener
 * @return void
 * @see LoopInterface::futureTick()
 */',
        'startLine' => 194,
        'endLine' => 202,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'addSignal' => 
      array (
        'name' => 'addSignal',
        'parameters' => 
        array (
          'signal' => 
          array (
            'name' => 'signal',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 47,
            'endColumn' => 55,
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
 * Register a listener to be notified when a signal has been caught by this process.
 *
 * @param int $signal
 * @param callable $listener
 * @return void
 * @see LoopInterface::addSignal()
 */',
        'startLine' => 212,
        'endLine' => 220,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'removeSignal' => 
      array (
        'name' => 'removeSignal',
        'parameters' => 
        array (
          'signal' => 
          array (
            'name' => 'signal',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 50,
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
 * Removes a previously added signal listener.
 *
 * @param int $signal
 * @param callable $listener
 * @return void
 * @see LoopInterface::removeSignal()
 */',
        'startLine' => 230,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the event loop until there are no more tasks to perform.
 *
 * @return void
 * @see LoopInterface::run()
 */',
        'startLine' => 243,
        'endLine' => 251,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
        'aliasName' => NULL,
      ),
      'stop' => 
      array (
        'name' => 'stop',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Instruct a running event loop to stop.
 *
 * @return void
 * @see LoopInterface::stop()
 */',
        'startLine' => 259,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'React\\EventLoop',
        'declaringClassName' => 'React\\EventLoop\\Loop',
        'implementingClassName' => 'React\\EventLoop\\Loop',
        'currentClassName' => 'React\\EventLoop\\Loop',
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