<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryConsolidateCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\Memory\MemoryConsolidateCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-4b8d0d9c33c8c80962bbc10583acf2a37cad74ce2d8772ace5047d4b956c09ce',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'filename' => '/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryConsolidateCommand.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands\\Memory',
    'name' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
    'shortName' => 'MemoryConsolidateCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Consolidation Command.
 *
 * Runs consolidation operations:
 * - Backfill retry: Retries failed memory formations
 * - Vector deduplication: Merges duplicate embeddings
 *
 * Usage:
 *   php artisan memory:consolidate
 *   php artisan memory:consolidate --user=1
 *   php artisan memory:consolidate --type=backfill
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 101,
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
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'memory:consolidate
        {--user= : Scope consolidation to a specific user ID}
        {--type= : Run specific type only: backfill, dedup}\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 30,
            'startTokenPos' => 50,
            'startFilePos' => 672,
            'endTokenPos' => 50,
            'endFilePos' => 813,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Run memory consolidation (backfill retries and vector deduplication)\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 61,
            'startFilePos' => 902,
            'endTokenPos' => 61,
            'endFilePos' => 971,
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
        'endColumn' => 100,
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
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Memory\\ConsolidationService',
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
            'endColumn' => 56,
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
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryConsolidateCommand',
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