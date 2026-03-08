<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Telemetry/ActiveBuildFreshnessService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Telemetry\ActiveBuildFreshnessService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-1ccc8fce54c7795fca42ecb5df34d54889b9ef02274ca5adb9583b880acd20e8',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Telemetry/ActiveBuildFreshnessService.php',
      ),
    ),
    'namespace' => 'App\\Services\\Telemetry',
    'name' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
    'shortName' => 'ActiveBuildFreshnessService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 77,
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
      'ACTIVE_BUILD_AGE_SECONDS_METRIC_KEY' => 
      array (
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'name' => 'ACTIVE_BUILD_AGE_SECONDS_METRIC_KEY',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'agent:telemetry:active_build_age_seconds\'',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 46,
            'startFilePos' => 266,
            'endTokenPos' => 46,
            'endFilePos' => 307,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 99,
      ),
    ),
    'immediateProperties' => 
    array (
      'buildStateService' => 
      array (
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'name' => 'buildStateService',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Telemetry\\ActiveProjectionBuildStateService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 9,
        'endColumn' => 77,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'clock' => 
      array (
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'name' => 'clock',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Time\\AgentClock',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 9,
        'endColumn' => 42,
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
          'buildStateService' => 
          array (
            'name' => 'buildStateService',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Telemetry\\ActiveProjectionBuildStateService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 16,
            'endLine' => 16,
            'startColumn' => 9,
            'endColumn' => 77,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'clock' => 
          array (
            'name' => 'clock',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Time\\AgentClock',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 17,
            'endLine' => 17,
            'startColumn' => 9,
            'endColumn' => 42,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 15,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Telemetry',
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'currentClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'aliasName' => NULL,
      ),
      'snapshot' => 
      array (
        'name' => 'snapshot',
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
 * @return array{
 *     active_build_id:?string,
 *     active_build_activated_at:?string,
 *     active_build_age_seconds:?int,
 *     active_build_is_stale:?bool,
 *     stale_after_seconds:int
 * }
 */',
        'startLine' => 29,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Telemetry',
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'currentClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'aliasName' => NULL,
      ),
      'staleAfterSeconds' => 
      array (
        'name' => 'staleAfterSeconds',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 62,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Telemetry',
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'currentClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'aliasName' => NULL,
      ),
      'emitGauge' => 
      array (
        'name' => 'emitGauge',
        'parameters' => 
        array (
          'ageSeconds' => 
          array (
            'name' => 'ageSeconds',
            'default' => NULL,
            'type' => 
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 32,
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
        'docComment' => NULL,
        'startLine' => 67,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Telemetry',
        'declaringClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'implementingClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
        'currentClassName' => 'App\\Services\\Telemetry\\ActiveBuildFreshnessService',
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