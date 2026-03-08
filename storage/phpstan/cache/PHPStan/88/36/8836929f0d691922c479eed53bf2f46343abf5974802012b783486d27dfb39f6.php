<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryPurgeUserCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\Memory\MemoryPurgeUserCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-1dbdd47dca95f4e757c9f7b754c32e5ce5cec729cf319ce8c57ab9acc99bbdbc',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'filename' => '/Users/garethdaine/Code/agent/app/Console/Commands/Memory/MemoryPurgeUserCommand.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands\\Memory',
    'name' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
    'shortName' => 'MemoryPurgeUserCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Purge User Command.
 *
 * GDPR compliance: Cascade delete all user memory data including Neo4j cleanup.
 *
 * This is a destructive operation that cannot be undone!
 *
 * Usage:
 *   php artisan memory:purge-user 1
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 223,
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
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'memory:purge-user
        {userId : The user ID to purge all memory data for}
        {--force : Skip confirmation prompt}\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 37,
            'startTokenPos' => 95,
            'startFilePos' => 863,
            'endTokenPos' => 95,
            'endFilePos' => 986,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Permanently delete all memory data for a user (GDPR compliance)\'',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 42,
            'startTokenPos' => 106,
            'startFilePos' => 1075,
            'endTokenPos' => 106,
            'endFilePos' => 1139,
          ),
        ),
        'docComment' => '/**
 * The console command description.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 95,
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
            'startLine' => 47,
            'endLine' => 47,
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
        'startLine' => 47,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'aliasName' => NULL,
      ),
      'displayPurgeSummary' => 
      array (
        'name' => 'displayPurgeSummary',
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
            'startLine' => 114,
            'endLine' => 114,
            'startColumn' => 42,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 114,
            'endLine' => 114,
            'startColumn' => 55,
            'endColumn' => 81,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Display summary of what will be deleted.
 */',
        'startLine' => 114,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'aliasName' => NULL,
      ),
      'countRedisKeys' => 
      array (
        'name' => 'countRedisKeys',
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
            'startLine' => 136,
            'endLine' => 136,
            'startColumn' => 37,
            'endColumn' => 47,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Count Redis working memory keys for user.
 */',
        'startLine' => 136,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'aliasName' => NULL,
      ),
      'purgeUserData' => 
      array (
        'name' => 'purgeUserData',
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
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 36,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 49,
            'endColumn' => 75,
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
 * Purge all user data.
 *
 * @return array<string, int> Counts of deleted records
 */',
        'startLine' => 152,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'aliasName' => NULL,
      ),
      'clearUserRedisKeys' => 
      array (
        'name' => 'clearUserRedisKeys',
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
            'startLine' => 198,
            'endLine' => 198,
            'startColumn' => 41,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clear Redis working memory keys for user\'s runs.
 */',
        'startLine' => 198,
        'endLine' => 222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands\\Memory',
        'declaringClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'implementingClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
        'currentClassName' => 'App\\Console\\Commands\\Memory\\MemoryPurgeUserCommand',
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