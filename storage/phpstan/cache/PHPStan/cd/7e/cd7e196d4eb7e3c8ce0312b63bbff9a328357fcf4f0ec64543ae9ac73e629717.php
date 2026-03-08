<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Agent/PreRunDatabaseBackup.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Agent\PreRunDatabaseBackup
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-5d1b00b64e7f2de3dc429cdf5c33d87fb524e67d8b79caa69d884ea0298aec93',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Agent/PreRunDatabaseBackup.php',
      ),
    ),
    'namespace' => 'App\\Support\\Agent',
    'name' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
    'shortName' => 'PreRunDatabaseBackup',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 94,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'backup' => 
      array (
        'name' => 'backup',
        'parameters' => 
        array (
          'run' => 
          array (
            'name' => 'run',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\AgentJobRun',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 15,
            'endLine' => 15,
            'startColumn' => 28,
            'endColumn' => 43,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array{ok: bool, attempted_at: string, message: string, output: ?string, skipped: bool, duration_ms: int}
 */',
        'startLine' => 15,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
        'implementingClassName' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
        'currentClassName' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
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