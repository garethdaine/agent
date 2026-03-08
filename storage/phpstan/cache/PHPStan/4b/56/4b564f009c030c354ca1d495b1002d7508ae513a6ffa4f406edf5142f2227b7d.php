<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Agent/DiagnosticsService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Agent\DiagnosticsService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-9fb9518a05239a4354a90f028e40f1ee1329be8203ef0aa18f345b0cb166d244',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Agent\\DiagnosticsService',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Agent/DiagnosticsService.php',
      ),
    ),
    'namespace' => 'App\\Support\\Agent',
    'name' => 'App\\Support\\Agent\\DiagnosticsService',
    'shortName' => 'DiagnosticsService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 278,
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
      'STATUS_OK' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'name' => 'STATUS_OK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ok\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 61,
            'startFilePos' => 404,
            'endTokenPos' => 61,
            'endFilePos' => 407,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'STATUS_WARN' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'name' => 'STATUS_WARN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'warn\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 72,
            'startFilePos' => 442,
            'endTokenPos' => 72,
            'endFilePos' => 447,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'STATUS_ERROR' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'name' => 'STATUS_ERROR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'error\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 83,
            'startFilePos' => 483,
            'endTokenPos' => 83,
            'endFilePos' => 489,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'run' => 
      array (
        'name' => 'run',
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
 * Run diagnostics checks. Returns list of checks with status, message, and optional fix.
 *
 * @return array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>
 */',
        'startLine' => 27,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkAppKey' => 
      array (
        'name' => 'checkAppKey',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 34,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 46,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkDatabase' => 
      array (
        'name' => 'checkDatabase',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 36,
            'endColumn' => 49,
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
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 71,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkRedis' => 
      array (
        'name' => 'checkRedis',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 97,
            'endLine' => 97,
            'startColumn' => 33,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 97,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkQueue' => 
      array (
        'name' => 'checkQueue',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 123,
            'endLine' => 123,
            'startColumn' => 33,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 123,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkScheduler' => 
      array (
        'name' => 'checkScheduler',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 149,
        'endLine' => 190,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkMessenger' => 
      array (
        'name' => 'checkMessenger',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 195,
            'endLine' => 195,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 195,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkStorage' => 
      array (
        'name' => 'checkStorage',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 240,
            'endLine' => 240,
            'startColumn' => 35,
            'endColumn' => 48,
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
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 240,
        'endLine' => 262,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'aliasName' => NULL,
      ),
      'checkRuntimeConfig' => 
      array (
        'name' => 'checkRuntimeConfig',
        'parameters' => 
        array (
          'checks' => 
          array (
            'name' => 'checks',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 267,
            'endLine' => 267,
            'startColumn' => 41,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
 */',
        'startLine' => 267,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'implementingClassName' => 'App\\Support\\Agent\\DiagnosticsService',
        'currentClassName' => 'App\\Support\\Agent\\DiagnosticsService',
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