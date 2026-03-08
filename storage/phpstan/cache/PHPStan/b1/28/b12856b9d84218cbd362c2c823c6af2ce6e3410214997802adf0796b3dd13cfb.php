<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Console/QueueImportCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Scout\Console\QueueImportCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c5c13a5eae339b3a9fbe8d700f6f5645912576eb4e5bc3cd66805bb415280552-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Console/QueueImportCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Scout\\Console',
    'name' => 'Laravel\\Scout\\Console\\QueueImportCommand',
    'shortName' => 'QueueImportCommand',
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
            'code' => '\'scout:queue-import\'',
            'attributes' => 
            array (
              'startLine' => 10,
              'endLine' => 10,
              'startTokenPos' => 33,
              'startFilePos' => 232,
              'endTokenPos' => 33,
              'endFilePos' => 251,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 10,
    'endLine' => 80,
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
        'declaringClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'scout:queue-import
            {model : Class name of model to bulk queue}
            {--min= : The minimum ID to start queuing from}
            {--max= : The maximum ID to queue up to}
            {--c|chunk= : The number of records to queue in a single job (Defaults to configuration value: `scout.chunk.searchable`)}
            {--queue= : The queue that should be used (Defaults to configuration value: `scout.queue.queue`)}\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 23,
            'startTokenPos' => 55,
            'startFilePos' => 421,
            'endTokenPos' => 55,
            'endFilePos' => 853,
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
        'startLine' => 18,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 111,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Import the given model into the search index via chunked, queued jobs\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 66,
            'startFilePos' => 968,
            'endTokenPos' => 66,
            'endFilePos' => 1038,
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
        'endColumn' => 101,
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
 * @return void
 *
 * @throws ScoutException
 */',
        'startLine' => 39,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout\\Console',
        'declaringClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'implementingClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
        'currentClassName' => 'Laravel\\Scout\\Console\\QueueImportCommand',
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