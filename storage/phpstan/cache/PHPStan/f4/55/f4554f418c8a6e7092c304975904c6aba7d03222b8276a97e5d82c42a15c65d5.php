<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Documentation/AgentSystemDocsTelemetryStore.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Documentation\AgentSystemDocsTelemetryStore
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-ada7de6b4eff3243f75e01547ddc57be6c720aaa40bbcb00c9f2271a493e6c06',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Documentation/AgentSystemDocsTelemetryStore.php',
      ),
    ),
    'namespace' => 'App\\Support\\Documentation',
    'name' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
    'shortName' => 'AgentSystemDocsTelemetryStore',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 184,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Support\\Documentation\\DocsTelemetryStore',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'COUNTER_PREFIX' => 
      array (
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'name' => 'COUNTER_PREFIX',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'docs_telemetry:counter:\'',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 48,
            'startFilePos' => 262,
            'endTokenPos' => 48,
            'endFilePos' => 286,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 61,
      ),
      'RECENT_FAILURES_KEY' => 
      array (
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'name' => 'RECENT_FAILURES_KEY',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'docs_telemetry:recent_failures\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 59,
            'startFilePos' => 330,
            'endTokenPos' => 59,
            'endFilePos' => 361,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 73,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'incrementCounter' => 
      array (
        'name' => 'incrementCounter',
        'parameters' => 
        array (
          'counter' => 
          array (
            'name' => 'counter',
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
            'startLine' => 17,
            'endLine' => 17,
            'startColumn' => 38,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'incrementBy' => 
          array (
            'name' => 'incrementBy',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 17,
                'endLine' => 17,
                'startTokenPos' => 79,
                'startFilePos' => 438,
                'endTokenPos' => 79,
                'endFilePos' => 438,
              ),
            ),
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
            'startLine' => 17,
            'endLine' => 17,
            'startColumn' => 55,
            'endColumn' => 74,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 17,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'aliasName' => NULL,
      ),
      'getCounters' => 
      array (
        'name' => 'getCounters',
        'parameters' => 
        array (
          'counterNames' => 
          array (
            'name' => 'counterNames',
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
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 33,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, string>  $counterNames
 * @return array<string, int>
 */',
        'startLine' => 44,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'aliasName' => NULL,
      ),
      'appendRecentFailure' => 
      array (
        'name' => 'appendRecentFailure',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
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
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 41,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'maxItems' => 
          array (
            'name' => 'maxItems',
            'default' => 
            array (
              'code' => '50',
              'attributes' => 
              array (
                'startLine' => 78,
                'endLine' => 78,
                'startTokenPos' => 545,
                'startFilePos' => 2393,
                'endTokenPos' => 545,
                'endFilePos' => 2394,
              ),
            ),
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
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 55,
            'endColumn' => 72,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<string, mixed>  $event
 */',
        'startLine' => 78,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'aliasName' => NULL,
      ),
      'getRecentFailures' => 
      array (
        'name' => 'getRecentFailures',
        'parameters' => 
        array (
          'limit' => 
          array (
            'name' => 'limit',
            'default' => 
            array (
              'code' => '20',
              'attributes' => 
              array (
                'startLine' => 104,
                'endLine' => 104,
                'startTokenPos' => 761,
                'startFilePos' => 3295,
                'endTokenPos' => 761,
                'endFilePos' => 3296,
              ),
            ),
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
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 39,
            'endColumn' => 53,
            'parameterIndex' => 0,
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
 * @return array<int, array<string, mixed>>
 */',
        'startLine' => 104,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'aliasName' => NULL,
      ),
      'parseCounterValue' => 
      array (
        'name' => 'parseCounterValue',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 123,
            'endLine' => 123,
            'startColumn' => 40,
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
        'docComment' => NULL,
        'startLine' => 123,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'aliasName' => NULL,
      ),
      'parseRecentFailures' => 
      array (
        'name' => 'parseRecentFailures',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 144,
            'endLine' => 144,
            'startColumn' => 42,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array<int, array<string, mixed>>
 */',
        'startLine' => 144,
        'endLine' => 169,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'aliasName' => NULL,
      ),
      'encodeRecentFailures' => 
      array (
        'name' => 'encodeRecentFailures',
        'parameters' => 
        array (
          'events' => 
          array (
            'name' => 'events',
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
            'startLine' => 174,
            'endLine' => 174,
            'startColumn' => 43,
            'endColumn' => 55,
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
 * @param  array<int, array<string, mixed>>  $events
 */',
        'startLine' => 174,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'implementingClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
        'currentClassName' => 'App\\Support\\Documentation\\AgentSystemDocsTelemetryStore',
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