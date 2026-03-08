<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/ListenCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\Console\ListenCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-70d01c69b2cf1cca7a6b12f4d6ab2e35bae8efc7ea3914fc8889285df055ca62-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/ListenCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon\\Console',
    'name' => 'Laravel\\Horizon\\Console\\ListenCommand',
    'shortName' => 'ListenCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
      0 => 
      array (
        'name' => 'Symfony\\Component\\Console\\Attribute\\AsCommand',
        'isRepeated' => false,
        'arguments' => 
        array (
          'name' => 
          array (
            'code' => '\'horizon:listen\'',
            'attributes' => 
            array (
              'startLine' => 11,
              'endLine' => 11,
              'startTokenPos' => 38,
              'startFilePos' => 262,
              'endTokenPos' => 38,
              'endFilePos' => 277,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 11,
    'endLine' => 196,
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
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'horizon:listen
        {--environment= : The environment name}
        {--poll : Use polling for file watching}\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 21,
            'startTokenPos' => 60,
            'startFilePos' => 442,
            'endTokenPos' => 60,
            'endFilePos' => 554,
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
        'startLine' => 19,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 50,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Run Horizon and automatically restart workers on file changes\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 71,
            'startFilePos' => 669,
            'endTokenPos' => 71,
            'endFilePos' => 731,
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
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 93,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'horizonProcess' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'name' => 'horizonProcess',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Horizon process instance.
 *
 * @var \\Symfony\\Component\\Process\\Process|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'watcherProcess' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'name' => 'watcherProcess',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The file watcher process instance.
 *
 * @var \\Symfony\\Component\\Process\\Process|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'trappedSignal' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'name' => 'trappedSignal',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 96,
            'startFilePos' => 1163,
            'endTokenPos' => 96,
            'endFilePos' => 1166,
          ),
        ),
        'docComment' => '/**
 * Indicates if a termination signal has been received.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
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
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the console command.
 *
 * @return int
 */',
        'startLine' => 56,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'aliasName' => NULL,
      ),
      'startWatcher' => 
      array (
        'name' => 'startWatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Start the file watcher process.
 *
 * @return \\Symfony\\Component\\Process\\Process
 */',
        'startLine' => 80,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'aliasName' => NULL,
      ),
      'startHorizon' => 
      array (
        'name' => 'startHorizon',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Start the Horizon process.
 *
 * @return bool
 */',
        'startLine' => 115,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'aliasName' => NULL,
      ),
      'listenForChanges' => 
      array (
        'name' => 'listenForChanges',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Listen for file changes and restart Horizon when detected.
 *
 * @return void
 */',
        'startLine' => 149,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'aliasName' => NULL,
      ),
      'restartHorizon' => 
      array (
        'name' => 'restartHorizon',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Restart the Horizon process.
 *
 * @return void
 */',
        'startLine' => 171,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'aliasName' => NULL,
      ),
      'watcherFailed' => 
      array (
        'name' => 'watcherFailed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle watcher process failure.
 *
 * @return int
 */',
        'startLine' => 186,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\ListenCommand',
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