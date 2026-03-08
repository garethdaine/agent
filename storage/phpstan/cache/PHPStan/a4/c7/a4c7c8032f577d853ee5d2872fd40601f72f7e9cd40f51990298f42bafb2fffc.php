<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryPruneCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\Memory\MemoryPruneCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-0022423e5fda84d0a07ebf0da5e161ade5b3babec8116827225fa6d39ecc7231',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'filename' => '/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryPruneCommand.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands\\Memory',
    'name' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
    'shortName' => 'MemoryPruneCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Prune Command.
 *
 * Runs tiered pruning of memory data:
 * - Embeddings below decay threshold (0.1 default)
 * - Graph entities below importance threshold (0.2 default) after 90 days
 *
 * Default mode is DRY-RUN (preview only). Use --force to actually delete.
 *
 * Usage:
 *   php artisan memory:prune                  # Dry-run for all users
 *   php artisan memory:prune --user=1         # Dry-run for user 1
 *   php artisan memory:prune --force          # Actually delete
 *   php artisan memory:prune --execute        # Actually delete (alias)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 134,
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
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'memory:prune
        {--user= : Scope pruning to a specific user ID}
        {--force : Actually delete (not dry-run)}
        {--execute : Actually delete (alias for --force)}\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 34,
            'startTokenPos' => 50,
            'startFilePos' => 900,
            'endTokenPos' => 50,
            'endFilePos' => 1077,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 59,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Prune memory data below decay thresholds (dry-run by default, use --force to delete)\'',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 61,
            'startFilePos' => 1166,
            'endTokenPos' => 61,
            'endFilePos' => 1251,
          ),
        ),
        'docComment' => '/**
 * The console command description.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 116,
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
                'name' => 'App\\Support\\Memory\\ForgettingService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 28,
            'endColumn' => 53,
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
        'startLine' => 44,
        'endLine' => 133,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryPruneCommand',
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