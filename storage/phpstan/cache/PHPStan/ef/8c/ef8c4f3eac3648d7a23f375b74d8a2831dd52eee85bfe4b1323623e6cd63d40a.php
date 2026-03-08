<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Agent/DatabaseIsolationEnvironment.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Agent\DatabaseIsolationEnvironment
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-0c3acf3e9cacf3dfdbfbc997d2b1c6651f0fdb62f8883bac2135206dc93a0bc3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Agent/DatabaseIsolationEnvironment.php',
      ),
    ),
    'namespace' => 'App\\Support\\Agent',
    'name' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
    'shortName' => 'DatabaseIsolationEnvironment',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 119,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'PRODUCTION_DB_KEYS' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'implementingClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'name' => 'PRODUCTION_DB_KEYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'DB_CONNECTION\', \'DB_HOST\', \'DB_PORT\', \'DB_DATABASE\', \'DB_USERNAME\', \'DB_PASSWORD\', \'DB_URL\', \'DB_CHARSET\', \'DB_SSLMODE\', \'DB_SEARCH_PATH\']',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 24,
            'startTokenPos' => 23,
            'startFilePos' => 274,
            'endTokenPos' => 55,
            'endFilePos' => 500,
          ),
        ),
        'docComment' => '/**
 * Keys that carry production database credentials and must never
 * leak into agent subprocesses.
 *
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'SUPPRESSED_KEYS' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'implementingClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'name' => 'SUPPRESSED_KEYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'CLAUDECODE\']',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 38,
            'startTokenPos' => 68,
            'startFilePos' => 955,
            'endTokenPos' => 73,
            'endFilePos' => 983,
          ),
        ),
        'docComment' => '/**
 * Keys that must be explicitly suppressed in subprocesses.
 *
 * Symfony Process merges getenv()/\\$_SERVER back into the env array
 * via += for any missing keys.  Simply omitting a key is not enough;
 * we must set it to `false` so the key is present (blocking the
 * backfill) while Symfony skips false values when building envPairs.
 *
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'build' => 
      array (
        'name' => 'build',
        'parameters' => 
        array (
          'baseEnv' => 
          array (
            'name' => 'baseEnv',
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 34,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'overrides' => 
          array (
            'name' => 'overrides',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 48,
                'endLine' => 48,
                'startTokenPos' => 97,
                'startFilePos' => 1382,
                'endTokenPos' => 98,
                'endFilePos' => 1383,
              ),
            ),
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 50,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => true,
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
 * Build a subprocess environment from $_ENV with production DB
 * credentials stripped and safe testing values injected.
 *
 * @param  array<string, mixed>  $baseEnv  Typically $_ENV
 * @param  array<string, mixed>  $overrides  Job-level env_json
 * @return array<string, string|false>
 */',
        'startLine' => 48,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'implementingClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'currentClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'aliasName' => NULL,
      ),
      'safeTestingDatabaseVars' => 
      array (
        'name' => 'safeTestingDatabaseVars',
        'parameters' => 
        array (
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
 * @return array<string, string>
 */',
        'startLine' => 92,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'implementingClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'currentClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'aliasName' => NULL,
      ),
      'productionDbKeys' => 
      array (
        'name' => 'productionDbKeys',
        'parameters' => 
        array (
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
 * @return array<int, string>
 */',
        'startLine' => 115,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'implementingClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
        'currentClassName' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
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