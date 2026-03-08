<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Console/SyncIndexSettingsCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Scout\Console\SyncIndexSettingsCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-1b194e9a3553df970465b7bf110677b66c3a5570145b1ced8f5f4192c024959f-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Console/SyncIndexSettingsCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Scout\\Console',
    'name' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
    'shortName' => 'SyncIndexSettingsCommand',
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
            'code' => '\'scout:sync-index-settings\'',
            'attributes' => 
            array (
              'startLine' => 13,
              'endLine' => 13,
              'startTokenPos' => 48,
              'startFilePos' => 315,
              'endTokenPos' => 48,
              'endFilePos' => 341,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 13,
    'endLine' => 95,
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
        'declaringClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'scout:sync-index-settings {--driver= : The name of the search engine driver (Defaults to configuration value: `scout.driver`)}\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 70,
            'startFilePos' => 517,
            'endTokenPos' => 70,
            'endFilePos' => 644,
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
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 156,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Sync your configured index settings with your search engine (Meilisearch)\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 81,
            'startFilePos' => 759,
            'endTokenPos' => 81,
            'endFilePos' => 833,
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
        'endColumn' => 105,
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
            'startLine' => 36,
            'endLine' => 36,
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
        'startLine' => 36,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout\\Console',
        'declaringClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'currentClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
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
            'startLine' => 85,
            'endLine' => 85,
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
        'startLine' => 85,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Scout\\Console',
        'declaringClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
        'currentClassName' => 'Laravel\\Scout\\Console\\SyncIndexSettingsCommand',
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