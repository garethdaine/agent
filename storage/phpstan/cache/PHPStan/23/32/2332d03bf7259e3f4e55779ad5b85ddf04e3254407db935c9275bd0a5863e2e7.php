<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/pennant/src/Commands/PurgeCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Pennant\Commands\PurgeCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-4341788b9aed12ebaa9fb1b4132e6a6bd6b529bb74758f1c4092914bea84068a-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/pennant/src/Commands/PurgeCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Pennant\\Commands',
    'name' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
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
            'code' => '\'pennant:purge\'',
            'attributes' => 
            array (
              'startLine' => 9,
              'endLine' => 9,
              'startTokenPos' => 28,
              'startFilePos' => 182,
              'endTokenPos' => 28,
              'endFilePos' => 196,
            ),
          ),
          'aliases' => 
          array (
            'code' => '[\'pennant:clear\']',
            'attributes' => 
            array (
              'startLine' => 9,
              'endLine' => 9,
              'startTokenPos' => 34,
              'startFilePos' => 208,
              'endTokenPos' => 36,
              'endFilePos' => 224,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 9,
    'endLine' => 74,
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
        'declaringClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'pennant:purge
                            {features?* : The features to purge}
                            {--except=* : The features that should be excluded from purging}
                            {--except-registered : Purge all features except those registered}
                            {--store= : The store to purge the features from}\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 21,
            'startTokenPos' => 58,
            'startFilePos' => 367,
            'endTokenPos' => 58,
            'endFilePos' => 712,
          ),
        ),
        'docComment' => '/**
 * The console command name.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 79,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Delete Pennant features from storage\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 69,
            'startFilePos' => 827,
            'endTokenPos' => 69,
            'endFilePos' => 864,
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
        'endColumn' => 68,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'aliases' => 
      array (
        'declaringClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'name' => 'aliases',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'pennant:clear\']',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 80,
            'startFilePos' => 975,
            'endTokenPos' => 82,
            'endFilePos' => 991,
          ),
        ),
        'docComment' => '/**
 * The console command name aliases.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 43,
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
          'manager' => 
          array (
            'name' => 'manager',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Pennant\\FeatureManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 28,
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
 * Execute the console command.
 *
 * @return int
 */',
        'startLine' => 42,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Pennant\\Commands',
        'declaringClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'implementingClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
        'currentClassName' => 'Laravel\\Pennant\\Commands\\PurgeCommand',
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