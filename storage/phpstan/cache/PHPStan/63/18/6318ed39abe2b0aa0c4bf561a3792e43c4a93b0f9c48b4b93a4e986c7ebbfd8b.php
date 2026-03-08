<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Console/DeleteIndexCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Scout\Console\DeleteIndexCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-fdd15f6564e2d0128d7fdeb20a6b9ad0fd3bd126a689387ec55828978697b288-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Console/DeleteIndexCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Scout\\Console',
    'name' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
    'shortName' => 'DeleteIndexCommand',
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
            'code' => '\'scout:delete-index\'',
            'attributes' => 
            array (
              'startLine' => 11,
              'endLine' => 11,
              'startTokenPos' => 38,
              'startFilePos' => 219,
              'endTokenPos' => 38,
              'endFilePos' => 238,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 11,
    'endLine' => 61,
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
        'declaringClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'scout:delete-index {name : The name of the index}\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 60,
            'startFilePos' => 408,
            'endTokenPos' => 60,
            'endFilePos' => 458,
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
        'endLine' => 19,
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
        'declaringClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Delete an index\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 71,
            'startFilePos' => 573,
            'endTokenPos' => 71,
            'endFilePos' => 589,
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
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 47,
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
                'name' => 'Laravel\\Scout\\EngineManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 28,
            'endColumn' => 49,
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
 * @param  \\Laravel\\Scout\\EngineManager  $manager
 * @return void
 */',
        'startLine' => 34,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout\\Console',
        'declaringClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'currentClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'aliasName' => NULL,
      ),
      'indexName' => 
      array (
        'name' => 'indexName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 51,
            'endLine' => 51,
            'startColumn' => 34,
            'endColumn' => 38,
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
 * Get the fully-qualified index name for the given index.
 *
 * @param  string  $name
 * @return string
 */',
        'startLine' => 51,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Scout\\Console',
        'declaringClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
        'currentClassName' => 'Laravel\\Scout\\Console\\DeleteIndexCommand',
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