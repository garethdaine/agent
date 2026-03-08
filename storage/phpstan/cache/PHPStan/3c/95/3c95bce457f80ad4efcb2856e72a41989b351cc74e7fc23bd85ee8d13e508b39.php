<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/PendingChain.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Foundation\Bus\PendingChain
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-e0ba7b691b6c931b43b3e27a05c600586eefef3ebd338b106f61aeb6da5ea1b4-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/PendingChain.php',
      ),
    ),
    'namespace' => 'Illuminate\\Foundation\\Bus',
    'name' => 'Illuminate\\Foundation\\Bus\\PendingChain',
    'shortName' => 'PendingChain',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 237,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Conditionable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'job' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'name' => 'job',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The class name of the job being dispatched.
 *
 * @var mixed
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 16,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'chain' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'name' => 'chain',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The jobs to be chained.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'connection' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'name' => 'connection',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name of the connection the chain should be sent to.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queue' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'name' => 'queue',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name of the queue the chain should be sent to.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'delay' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'name' => 'delay',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of seconds before the chain should be made available.
 *
 * @var \\DateTimeInterface|\\DateInterval|int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'catchCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'name' => 'catchCallbacks',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 103,
            'startFilePos' => 1145,
            'endTokenPos' => 104,
            'endFilePos' => 1146,
          ),
        ),
        'docComment' => '/**
 * The callbacks to be executed on failure.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 32,
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
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'chain' => 
          array (
            'name' => 'chain',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 39,
            'endColumn' => 44,
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
 * Create a new PendingChain instance.
 *
 * @param  mixed  $job
 * @param  array  $chain
 */',
        'startLine' => 67,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'onConnection' => 
      array (
        'name' => 'onConnection',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 34,
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
 * Set the desired connection for the job.
 *
 * @param  \\UnitEnum|string|null  $connection
 * @return $this
 */',
        'startLine' => 79,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'onQueue' => 
      array (
        'name' => 'onQueue',
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
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 29,
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
 * Set the desired queue for the job.
 *
 * @param  \\UnitEnum|string|null  $queue
 * @return $this
 */',
        'startLine' => 92,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'prepend' => 
      array (
        'name' => 'prepend',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 29,
            'endColumn' => 32,
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
 * Prepend a job to the chain.
 *
 * @param  mixed  $job
 * @return $this
 */',
        'startLine' => 105,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'append' => 
      array (
        'name' => 'append',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 128,
            'endLine' => 128,
            'startColumn' => 28,
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
 * Append a job to the chain.
 *
 * @param  mixed  $job
 * @return $this
 */',
        'startLine' => 128,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'delay' => 
      array (
        'name' => 'delay',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 27,
            'endColumn' => 32,
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
 * Set the desired delay in seconds for the chain.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int|null  $delay
 * @return $this
 */',
        'startLine' => 149,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'catch' => 
      array (
        'name' => 'catch',
        'parameters' => 
        array (
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 27,
            'endColumn' => 35,
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
 * Add a callback to be executed on job failure.
 *
 * @param  callable  $callback
 * @return $this
 */',
        'startLine' => 162,
        'endLine' => 169,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'catchCallbacks' => 
      array (
        'name' => 'catchCallbacks',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the "catch" callbacks that have been registered.
 *
 * @return array
 */',
        'startLine' => 176,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'dispatch' => 
      array (
        'name' => 'dispatch',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dispatch the job chain.
 *
 * @return \\Illuminate\\Foundation\\Bus\\PendingDispatch
 */',
        'startLine' => 186,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'dispatchIf' => 
      array (
        'name' => 'dispatchIf',
        'parameters' => 
        array (
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 222,
            'endLine' => 222,
            'startColumn' => 32,
            'endColumn' => 39,
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
 * Dispatch the job chain if the given truth test passes.
 *
 * @param  bool|\\Closure  $boolean
 * @return \\Illuminate\\Foundation\\Bus\\PendingDispatch|null
 */',
        'startLine' => 222,
        'endLine' => 225,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'aliasName' => NULL,
      ),
      'dispatchUnless' => 
      array (
        'name' => 'dispatchUnless',
        'parameters' => 
        array (
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 233,
            'endLine' => 233,
            'startColumn' => 36,
            'endColumn' => 43,
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
 * Dispatch the job chain unless the given truth test passes.
 *
 * @param  bool|\\Closure  $boolean
 * @return \\Illuminate\\Foundation\\Bus\\PendingDispatch|null
 */',
        'startLine' => 233,
        'endLine' => 236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Foundation\\Bus',
        'declaringClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'implementingClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
        'currentClassName' => 'Illuminate\\Foundation\\Bus\\PendingChain',
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