<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Agent/ServiceManagerService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Agent\ServiceManagerService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-5170167c6b698d303a5cc11ffb9a9fab823d729e18f02af20feb0af577852488',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Agent\\ServiceManagerService',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Agent/ServiceManagerService.php',
      ),
    ),
    'namespace' => 'App\\Support\\Agent',
    'name' => 'App\\Support\\Agent\\ServiceManagerService',
    'shortName' => 'ServiceManagerService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 193,
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
      'SERVICES' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'name' => 'SERVICES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'horizon\' => [\'label\' => \'Task Queue\', \'description\' => \'Processes background tasks such as job runs, plan generation, and notifications.\', \'command\' => \'php artisan horizon\', \'signature\' => \'artisan horizon\', \'critical\' => true], \'reverb\' => [\'label\' => \'Live Updates\', \'description\' => \'Provides real-time updates to the dashboard and active sessions.\', \'command\' => \'php artisan reverb:start\', \'signature\' => \'artisan reverb:start\', \'critical\' => false], \'scheduler\' => [\'label\' => \'Scheduler\', \'description\' => \'Runs scheduled jobs and periodic maintenance tasks.\', \'command\' => \'php artisan schedule:work\', \'signature\' => \'artisan schedule:work\', \'critical\' => true], \'messenger-gateway\' => [\'label\' => \'Messenger Gateway\', \'description\' => \'Handles connections to Slack, Telegram, Discord, and other messaging platforms.\', \'command\' => \'php artisan agent:messenger-gateway\', \'signature\' => \'agent:messenger-gateway\', \'critical\' => false]]',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 43,
            'startTokenPos' => 28,
            'startFilePos' => 338,
            'endTokenPos' => 206,
            'endFilePos' => 1605,
          ),
        ),
        'docComment' => '/**
 * Services exposed to the UI with user-friendly labels.
 *
 * @var array<string, array{label: string, description: string, command: string, signature: string, critical: bool}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'processManager' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'name' => 'processManager',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Agent\\ProcessManager',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 9,
        'endColumn' => 55,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'processManager' => 
          array (
            'name' => 'processManager',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Agent\\ProcessManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 9,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 45,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'currentClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'aliasName' => NULL,
      ),
      'status' => 
      array (
        'name' => 'status',
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
 * Get the status of all managed services.
 *
 * @return array<string, array{key: string, label: string, description: string, status: string, critical: bool, pid: int|null}>
 */',
        'startLine' => 54,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'currentClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'aliasName' => NULL,
      ),
      'restart' => 
      array (
        'name' => 'restart',
        'parameters' => 
        array (
          'serviceKey' => 
          array (
            'name' => 'serviceKey',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 29,
            'endColumn' => 46,
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
 * Restart a specific service by key.
 *
 * @return array{success: bool, message: string}
 */',
        'startLine' => 79,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'currentClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'aliasName' => NULL,
      ),
      'startService' => 
      array (
        'name' => 'startService',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 48,
            'endColumn' => 60,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Start a service and record its PID.
 */',
        'startLine' => 137,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'currentClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'aliasName' => NULL,
      ),
      'resolveRunningPid' => 
      array (
        'name' => 'resolveRunningPid',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 173,
            'endLine' => 173,
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'signature' => 
          array (
            'name' => 'signature',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 173,
            'endLine' => 173,
            'startColumn' => 53,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resolve the actual running PID for a service (PID file first, then pgrep fallback).
 */',
        'startLine' => 173,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'implementingClassName' => 'App\\Support\\Agent\\ServiceManagerService',
        'currentClassName' => 'App\\Support\\Agent\\ServiceManagerService',
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