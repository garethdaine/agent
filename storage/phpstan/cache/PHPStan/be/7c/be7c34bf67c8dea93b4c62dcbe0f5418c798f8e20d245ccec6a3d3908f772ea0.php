<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/PurgeCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\Console\PurgeCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-9710789b0ed49889375cc8f21a00fc1a6db65ad6a4cdd3f71098828e3dc917b0-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/PurgeCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon\\Console',
    'name' => 'Laravel\\Horizon\\Console\\PurgeCommand',
    'shortName' => 'PurgeCommand',
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
            'code' => '\'horizon:purge\'',
            'attributes' => 
            array (
              'startLine' => 14,
              'endLine' => 14,
              'startTokenPos' => 53,
              'startFilePos' => 408,
              'endTokenPos' => 53,
              'endFilePos' => 422,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 14,
    'endLine' => 139,
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
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'horizon:purge
                            {--signal=SIGTERM : The signal to send to the rogue processes}\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 23,
            'startTokenPos' => 75,
            'startFilePos' => 586,
            'endTokenPos' => 75,
            'endFilePos' => 691,
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
        'startLine' => 22,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 92,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Terminate any rogue Horizon processes\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 86,
            'startFilePos' => 806,
            'endTokenPos' => 86,
            'endFilePos' => 844,
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
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 69,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'supervisors' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'name' => 'supervisors',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var \\Laravel\\Horizon\\Contracts\\SupervisorRepository
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'processes' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'name' => 'processes',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var \\Laravel\\Horizon\\Contracts\\ProcessRepository
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'inspector' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'name' => 'inspector',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var \\Laravel\\Horizon\\ProcessInspector
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 23,
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
          'supervisors' => 
          array (
            'name' => 'supervisors',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Horizon\\Contracts\\SupervisorRepository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 9,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'processes' => 
          array (
            'name' => 'processes',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Horizon\\Contracts\\ProcessRepository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 9,
            'endColumn' => 36,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'inspector' => 
          array (
            'name' => 'inspector',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Horizon\\ProcessInspector',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 9,
            'endColumn' => 35,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new command instance.
 *
 * @param  \\Laravel\\Horizon\\Contracts\\SupervisorRepository  $supervisors
 * @param  \\Laravel\\Horizon\\Contracts\\ProcessRepository  $processes
 * @param  \\Laravel\\Horizon\\ProcessInspector  $inspector
 * @return void
 */',
        'startLine' => 55,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'masters' => 
          array (
            'name' => 'masters',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Horizon\\Contracts\\MasterSupervisorRepository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 28,
            'endColumn' => 62,
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
 * Execute the console command.
 *
 * @param  \\Laravel\\Horizon\\Contracts\\MasterSupervisorRepository  $masters
 * @return void
 */',
        'startLine' => 73,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'aliasName' => NULL,
      ),
      'purge' => 
      array (
        'name' => 'purge',
        'parameters' => 
        array (
          'master' => 
          array (
            'name' => 'master',
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
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'signal' => 
          array (
            'name' => 'signal',
            'default' => 
            array (
              'code' => 'SIGTERM',
              'attributes' => 
              array (
                'startLine' => 93,
                'endLine' => 93,
                'startTokenPos' => 287,
                'startFilePos' => 2478,
                'endTokenPos' => 287,
                'endFilePos' => 2484,
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
            'startColumn' => 36,
            'endColumn' => 52,
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
 * Purge any orphan processes.
 *
 * @param  string  $master
 * @param  int  $signal
 * @return void
 */',
        'startLine' => 93,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'aliasName' => NULL,
      ),
      'recordOrphans' => 
      array (
        'name' => 'recordOrphans',
        'parameters' => 
        array (
          'master' => 
          array (
            'name' => 'master',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 47,
            'endColumn' => 53,
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
 * Record the orphaned Horizon processes.
 *
 * @param  string  $master
 * @param  int  $signal
 * @return void
 */',
        'startLine' => 119,
        'endLine' => 138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\PurgeCommand',
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