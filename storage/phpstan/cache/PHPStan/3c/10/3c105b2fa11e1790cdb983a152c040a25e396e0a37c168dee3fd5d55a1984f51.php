<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryGraphSnapshotCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\Memory\MemoryGraphSnapshotCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-9cb2e885125bd1863e3a12d3613e643a1c00bda4f10ebd53775a302eaee91c58',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'filename' => '/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryGraphSnapshotCommand.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands\\Memory',
    'name' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
    'shortName' => 'MemoryGraphSnapshotCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Graph Snapshot Command.
 *
 * Point-in-time inspection of knowledge graph using bi-temporal metadata.
 *
 * Usage:
 *   php artisan memory:graph-snapshot 1
 *   php artisan memory:graph-snapshot 1 --output=snapshot.json
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 238,
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
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'memory:graph-snapshot
        {userId : The user ID to snapshot}
        {--output= : Output file path (default: stdout)}
        {--format=json : Output format: json, table}
        {--limit=100 : Maximum entities to return}\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 30,
            'startTokenPos' => 55,
            'startFilePos' => 606,
            'endTokenPos' => 55,
            'endFilePos' => 832,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 52,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Generate a point-in-time snapshot of the knowledge graph for a user\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 66,
            'startFilePos' => 921,
            'endTokenPos' => 66,
            'endFilePos' => 989,
          ),
        ),
        'docComment' => '/**
 * The console command description.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 99,
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
          'graphStore' => 
          array (
            'name' => 'graphStore',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Memory\\Neo4jGraphStore',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 28,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the console command.
 */',
        'startLine' => 40,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'aliasName' => NULL,
      ),
      'getGraphSnapshot' => 
      array (
        'name' => 'getGraphSnapshot',
        'parameters' => 
        array (
          'userId' => 
          array (
            'name' => 'userId',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 39,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'limit' => 
          array (
            'name' => 'limit',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 52,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get graph snapshot data.
 *
 * @return array<string, mixed>
 */',
        'startLine' => 94,
        'endLine' => 191,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'aliasName' => NULL,
      ),
      'displayAsTable' => 
      array (
        'name' => 'displayAsTable',
        'parameters' => 
        array (
          'snapshot' => 
          array (
            'name' => 'snapshot',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 198,
            'endLine' => 198,
            'startColumn' => 37,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Display snapshot as table.
 *
 * @param  array<string, mixed>  $snapshot
 */',
        'startLine' => 198,
        'endLine' => 237,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryGraphSnapshotCommand',
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