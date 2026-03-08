<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/TerminateCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\Console\TerminateCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-04b4c877e7635624fcb3a5da9c3dd94c220552fbccf10d45664bb788da2cb952-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/TerminateCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon\\Console',
    'name' => 'Laravel\\Horizon\\Console\\TerminateCommand',
    'shortName' => 'TerminateCommand',
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
            'code' => '\'horizon:terminate\'',
            'attributes' => 
            array (
              'startLine' => 14,
              'endLine' => 14,
              'startTokenPos' => 57,
              'startFilePos' => 395,
              'endTokenPos' => 57,
              'endFilePos' => 413,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 14,
    'endLine' => 70,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\InteractsWithTime',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'signature' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'horizon:terminate
                            {--wait : Wait for all workers to terminate}\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 25,
            'startTokenPos' => 84,
            'startFilePos' => 609,
            'endTokenPos' => 84,
            'endFilePos' => 700,
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
        'startLine' => 24,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 74,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Terminate the master supervisor so it can be restarted\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 95,
            'startFilePos' => 815,
            'endTokenPos' => 95,
            'endFilePos' => 870,
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
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 86,
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
          'cache' => 
          array (
            'name' => 'cache',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Cache\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 28,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 49,
            'endColumn' => 83,
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
 * Execute the console command.
 *
 * @param  \\Illuminate\\Contracts\\Cache\\Factory  $cache
 * @param  \\Laravel\\Horizon\\Contracts\\MasterSupervisorRepository  $masters
 * @return void
 */',
        'startLine' => 41,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\TerminateCommand',
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