<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Logging/RedactSensitiveProcessor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Logging\RedactSensitiveProcessor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-15ea2ca6f383c7bc6051f52b314c893fb64c5e8a333f0c41b329c7a173c20501',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Logging\\RedactSensitiveProcessor',
        'filename' => '/Users/garethdaine/Code/agent/app/Logging/RedactSensitiveProcessor.php',
      ),
    ),
    'namespace' => 'App\\Logging',
    'name' => 'App\\Logging\\RedactSensitiveProcessor',
    'shortName' => 'RedactSensitiveProcessor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 52,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'implementingClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'name' => 'PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/Bearer\\s+[A-Za-z0-9\\-._~+\\/]+=*/i\' => \'Bearer [REDACTED]\', \'/(?:api[_-]?key|token|secret|password|credential|authorization)\\s*[:=]\\s*["\\\']?[A-Za-z0-9\\-._~+\\/]{8,}["\\\']?/i\' => \'$0_REDACTED\', \'/sk-[A-Za-z0-9]{20,}/\' => \'[REDACTED_KEY]\', \'/xoxb-[A-Za-z0-9\\-]+/\' => \'[REDACTED_SLACK_TOKEN]\', \'/ghp_[A-Za-z0-9]{36,}/\' => \'[REDACTED_GH_TOKEN]\']',
          'attributes' => 
          array (
            'startLine' => 10,
            'endLine' => 16,
            'startTokenPos' => 35,
            'startFilePos' => 189,
            'endTokenPos' => 72,
            'endFilePos' => 576,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 10,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      '__invoke' => 
      array (
        'name' => '__invoke',
        'parameters' => 
        array (
          'record' => 
          array (
            'name' => 'record',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Monolog\\LogRecord',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 30,
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
            'name' => 'Monolog\\LogRecord',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Logging',
        'declaringClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'implementingClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'currentClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'aliasName' => NULL,
      ),
      'redact' => 
      array (
        'name' => 'redact',
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 29,
            'endColumn' => 41,
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
        'docComment' => NULL,
        'startLine' => 31,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Logging',
        'declaringClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'implementingClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'currentClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'aliasName' => NULL,
      ),
      'redactArray' => 
      array (
        'name' => 'redactArray',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 34,
            'endColumn' => 44,
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
        'docComment' => NULL,
        'startLine' => 40,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Logging',
        'declaringClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'implementingClassName' => 'App\\Logging\\RedactSensitiveProcessor',
        'currentClassName' => 'App\\Logging\\RedactSensitiveProcessor',
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