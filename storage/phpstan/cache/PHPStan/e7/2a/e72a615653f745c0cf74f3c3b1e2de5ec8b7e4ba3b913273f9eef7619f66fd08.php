<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/LocalDateTime.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Types\LocalDateTime
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-e7b433a46db4044041eacc9a3b49c7fc856a949e800033ba2743bfbdd8a2e0f3-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/LocalDateTime.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Types',
    'name' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
    'shortName' => 'LocalDateTime',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A date time represented in seconds and nanoseconds since the unix epoch.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int, int>
 *
 * @psalm-suppress TypeDoesNotContainType
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 34,
    'endLine' => 92,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laudis\\Neo4j\\Types\\AbstractPropertyObject',
    'implementsClassNames' => 
    array (
      0 => 'Laudis\\Neo4j\\Contracts\\BoltConvertibleInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'seconds' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'name' => 'seconds',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 9,
        'endColumn' => 37,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'nanoseconds' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'name' => 'nanoseconds',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 9,
        'endColumn' => 41,
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
          'seconds' => 
          array (
            'name' => 'seconds',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'nanoseconds' => 
          array (
            'name' => 'nanoseconds',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 9,
            'endColumn' => 41,
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
        'startLine' => 36,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'aliasName' => NULL,
      ),
      'getSeconds' => 
      array (
        'name' => 'getSeconds',
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
        'docComment' => '/**
 * The amount of seconds since the unix epoch.
 */',
        'startLine' => 45,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'aliasName' => NULL,
      ),
      'getNanoseconds' => 
      array (
        'name' => 'getNanoseconds',
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
        'docComment' => '/**
 * The amount of nanoseconds after the seconds have passed.
 */',
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'aliasName' => NULL,
      ),
      'toDateTime' => 
      array (
        'name' => 'toDateTime',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws Exception
 */',
        'startLine' => 61,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'aliasName' => NULL,
      ),
      'toArray' => 
      array (
        'name' => 'toArray',
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
 * @return array{seconds: int, nanoseconds: int}
 */',
        'startLine' => 75,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'aliasName' => NULL,
      ),
      'getProperties' => 
      array (
        'name' => 'getProperties',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Laudis\\Neo4j\\Types\\CypherMap',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'aliasName' => NULL,
      ),
      'convertToBolt' => 
      array (
        'name' => 'convertToBolt',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Bolt\\protocol\\IStructure',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 88,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\LocalDateTime',
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