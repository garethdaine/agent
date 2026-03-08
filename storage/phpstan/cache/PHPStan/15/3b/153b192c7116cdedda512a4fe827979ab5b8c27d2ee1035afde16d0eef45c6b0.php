<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Skills/Validation/CodeSafetyValidator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Skills\Validation\CodeSafetyValidator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-81af14169412d3d3099c89ee101f75aba508fe2c5a563d8c51a4d5920f6ae19c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Skills/Validation/CodeSafetyValidator.php',
      ),
    ),
    'namespace' => 'App\\Services\\Skills\\Validation',
    'name' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
    'shortName' => 'CodeSafetyValidator',
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
    'endLine' => 252,
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
      'BINARY_HEADERS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'BINARY_HEADERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    "ELF",
    // ELF
    "MZ",
    // PE/COFF (Windows)
    "\\xfe\\xed\\xfa",
    // Mach-O
    "\\xca\\xfe\\xba\\xbe",
]',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 17,
            'startTokenPos' => 39,
            'startFilePos' => 203,
            'endTokenPos' => 61,
            'endFilePos' => 372,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'EXTENSION_TO_LANGUAGE' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'EXTENSION_TO_LANGUAGE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'py\' => \'python\', \'sh\' => \'bash\', \'bash\' => \'bash\', \'js\' => \'javascript\', \'mjs\' => \'javascript\', \'php\' => \'php\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 26,
            'startTokenPos' => 72,
            'startFilePos' => 418,
            'endTokenPos' => 116,
            'endFilePos' => 585,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'DANGEROUS_PHP_TOKENS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'DANGEROUS_PHP_TOKENS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[T_EVAL]',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 30,
            'startTokenPos' => 127,
            'startFilePos' => 630,
            'endTokenPos' => 132,
            'endFilePos' => 652,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'DANGEROUS_FUNCTION_NAMES' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'DANGEROUS_FUNCTION_NAMES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'eval\', \'exec\', \'system\', \'shell_exec\', \'passthru\', \'proc_open\', \'popen\', \'pcntl_exec\']',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 35,
            'startTokenPos' => 143,
            'startFilePos' => 701,
            'endTokenPos' => 169,
            'endFilePos' => 811,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'NETWORK_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'NETWORK_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/\\brequests\\.(get|post|put|delete|patch)\\s*\\(/i\', \'/\\burllib\\.request/i\', \'/\\bhttp\\.client/i\', \'/\\bcurl_exec\\s*\\(/i\', \'/\\bcurl_init\\s*\\(/i\', \'/\\bfetch\\s*\\(/i\', \'/\\bXMLHttpRequest/i\', \'/\\baxios\\b/i\', \'/\\bwget\\b/i\', \'/\\bsocket_create\\s*\\(/i\', \'/\\bfsockopen\\s*\\(/i\', \'/\\bstream_socket_client\\s*\\(/i\']',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 50,
            'startTokenPos' => 180,
            'startFilePos' => 852,
            'endTokenPos' => 218,
            'endFilePos' => 1253,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'FILESYSTEM_ESCAPE_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'FILESYSTEM_ESCAPE_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/\\.\\.\\//\', \'/\\.\\.\\\\\\\\/\']',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 55,
            'startTokenPos' => 229,
            'startFilePos' => 1304,
            'endTokenPos' => 237,
            'endFilePos' => 1352,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'PROCESS_SPAWN_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'name' => 'PROCESS_SPAWN_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/\\bsubprocess\\.(run|call|Popen|check_output)\\s*\\(/i\', \'/\\bos\\.system\\s*\\(/i\', \'/\\bos\\.popen\\s*\\(/i\', \'/\\bchild_process/i\', \'/\\bspawn\\s*\\(/i\']',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 63,
            'startTokenPos' => 248,
            'startFilePos' => 1399,
            'endTokenPos' => 265,
            'endFilePos' => 1588,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'validate' => 
      array (
        'name' => 'validate',
        'parameters' => 
        array (
          'extractedPath' => 
          array (
            'name' => 'extractedPath',
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 30,
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
            'name' => 'App\\Services\\Skills\\Validation\\StageResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 65,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Skills\\Validation',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'aliasName' => NULL,
      ),
      'isBinary' => 
      array (
        'name' => 'isBinary',
        'parameters' => 
        array (
          'filePath' => 
          array (
            'name' => 'filePath',
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
            'startLine' => 169,
            'endLine' => 169,
            'startColumn' => 31,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 169,
        'endLine' => 190,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Skills\\Validation',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'aliasName' => NULL,
      ),
      'scanDangerousFunctions' => 
      array (
        'name' => 'scanDangerousFunctions',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 192,
            'endLine' => 192,
            'startColumn' => 45,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'language' => 
          array (
            'name' => 'language',
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
            'startLine' => 192,
            'endLine' => 192,
            'startColumn' => 62,
            'endColumn' => 77,
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
        'docComment' => NULL,
        'startLine' => 192,
        'endLine' => 218,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Skills\\Validation',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'aliasName' => NULL,
      ),
      'hasNetworkCalls' => 
      array (
        'name' => 'hasNetworkCalls',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 220,
            'endLine' => 220,
            'startColumn' => 38,
            'endColumn' => 52,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 220,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Skills\\Validation',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'aliasName' => NULL,
      ),
      'hasFilesystemEscape' => 
      array (
        'name' => 'hasFilesystemEscape',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 231,
            'endLine' => 231,
            'startColumn' => 42,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 231,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Skills\\Validation',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'aliasName' => NULL,
      ),
      'hasProcessSpawning' => 
      array (
        'name' => 'hasProcessSpawning',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 242,
            'endLine' => 242,
            'startColumn' => 41,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 242,
        'endLine' => 251,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Skills\\Validation',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\CodeSafetyValidator',
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