<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/PauseSupervisorCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\Console\PauseSupervisorCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-74625b7e451ef8a995306599e1f40c5d6ef268ca00d16cb79bd3432ee04c63e2-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/PauseSupervisorCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon\\Console',
    'name' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
    'shortName' => 'PauseSupervisorCommand',
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
            'code' => '\'horizon:pause-supervisor\'',
            'attributes' => 
            array (
              'startLine' => 11,
              'endLine' => 11,
              'startTokenPos' => 38,
              'startFilePos' => 263,
              'endTokenPos' => 38,
              'endFilePos' => 288,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 11,
    'endLine' => 54,
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
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'horizon:pause-supervisor
                            {name : The name of the supervisor to pause}\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 20,
            'startTokenPos' => 60,
            'startFilePos' => 462,
            'endTokenPos' => 60,
            'endFilePos' => 560,
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
        'endLine' => 20,
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
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Pause a supervisor\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 71,
            'startFilePos' => 675,
            'endTokenPos' => 71,
            'endFilePos' => 694,
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
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 50,
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 28,
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
 * Execute the console command.
 *
 * @param  \\Laravel\\Horizon\\Contracts\\SupervisorRepository  $supervisors
 * @return int|void
 */',
        'startLine' => 35,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\PauseSupervisorCommand',
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