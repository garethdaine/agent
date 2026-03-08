<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Runtime/Adapters/BrowserToolAdapter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Runtime\Adapters\BrowserToolAdapter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-8f7edbecc18a8befca71116c75beb229a6cd2a015f4889cb178939dbe5c11187',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Runtime/Adapters/BrowserToolAdapter.php',
      ),
    ),
    'namespace' => 'App\\Services\\Runtime\\Adapters',
    'name' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
    'shortName' => 'BrowserToolAdapter',
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
    'endLine' => 256,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Services\\Runtime\\Adapters\\AbstractToolAdapter',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'READ_COMMANDS' => 
      array (
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'name' => 'READ_COMMANDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'screenshot\', \'snapshot\', \'get\', \'is\', \'find\', \'console\', \'errors\', \'diff\', \'network\', \'session\', \'pdf\']',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 19,
            'startTokenPos' => 47,
            'startFilePos' => 381,
            'endTokenPos' => 82,
            'endFilePos' => 516,
          ),
        ),
        'docComment' => '/**
 * Commands that only read page state (require browser_snapshot capability).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'MUTATION_COMMANDS' => 
      array (
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'name' => 'MUTATION_COMMANDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'open\', \'click\', \'dblclick\', \'fill\', \'type\', \'press\', \'hover\', \'focus\', \'check\', \'uncheck\', \'select\', \'drag\', \'upload\', \'download\', \'scroll\', \'scrollintoview\', \'eval\', \'back\', \'forward\', \'reload\', \'wait\', \'set\', \'keyboard\', \'mouse\', \'tab\', \'close\', \'cookies\', \'storage\', \'highlight\', \'auth\']',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 32,
            'startTokenPos' => 95,
            'startFilePos' => 650,
            'endTokenPos' => 187,
            'endFilePos' => 1004,
          ),
        ),
        'docComment' => '/**
 * Commands that mutate page state (require browser_action capability).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'name' => 
      array (
        'name' => 'name',
        'parameters' => 
        array (
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
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'schema' => 
      array (
        'name' => 'schema',
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
        'docComment' => NULL,
        'startLine' => 39,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'authorize' => 
      array (
        'name' => 'authorize',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Runtime\\RuntimeContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 31,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
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
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 56,
            'endColumn' => 66,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 73,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'getRequiredCapability' => 
      array (
        'name' => 'getRequiredCapability',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
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
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 46,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 85,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'execute' => 
      array (
        'name' => 'execute',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Runtime\\RuntimeContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 97,
            'endLine' => 97,
            'startColumn' => 29,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
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
            'startLine' => 97,
            'endLine' => 97,
            'startColumn' => 54,
            'endColumn' => 64,
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
            'name' => 'App\\DTOs\\Runtime\\ToolResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 97,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'extractOperation' => 
      array (
        'name' => 'extractOperation',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 159,
            'endLine' => 159,
            'startColumn' => 39,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Extract the operation (first token) from a command string.
 */',
        'startLine' => 159,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'isReadCommand' => 
      array (
        'name' => 'isReadCommand',
        'parameters' => 
        array (
          'operation' => 
          array (
            'name' => 'operation',
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
            'startLine' => 170,
            'endLine' => 170,
            'startColumn' => 36,
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
        'docComment' => '/**
 * Check if an operation is read-only.
 */',
        'startLine' => 170,
        'endLine' => 173,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'buildCommand' => 
      array (
        'name' => 'buildCommand',
        'parameters' => 
        array (
          'binary' => 
          array (
            'name' => 'binary',
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
            'startLine' => 181,
            'endLine' => 181,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'tokens' => 
          array (
            'name' => 'tokens',
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
            'startLine' => 181,
            'endLine' => 181,
            'startColumn' => 51,
            'endColumn' => 63,
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
 * Build the full CLI command array for Process execution.
 *
 * @param  array<int, string>  $tokens  Tokenized command arguments
 * @return array<int, string>
 */',
        'startLine' => 181,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'tokenizeCommand' => 
      array (
        'name' => 'tokenizeCommand',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 44,
            'endColumn' => 58,
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
 * Tokenize a command string into arguments, handling quoted strings.
 *
 * Supports double quotes ("hello world") and single quotes (\'hello world\').
 * Backslash escapes within double-quoted strings are supported.
 *
 * @return array<int, string>
 */',
        'startLine' => 206,
        'endLine' => 250,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'aliasName' => NULL,
      ),
      'duration' => 
      array (
        'name' => 'duration',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
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
            'startLine' => 252,
            'endLine' => 252,
            'startColumn' => 31,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 252,
        'endLine' => 255,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Runtime\\Adapters',
        'declaringClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'implementingClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
        'currentClassName' => 'App\\Services\\Runtime\\Adapters\\BrowserToolAdapter',
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