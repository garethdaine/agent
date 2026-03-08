<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/Time.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Types\Time
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-4a0703cc30f3d08fcb7cf8184037e4b442ce06295a99fd5222698dd11bbb2b5c-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Types\\Time',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/Time.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Types',
    'name' => 'Laudis\\Neo4j\\Types\\Time',
    'shortName' => 'Time',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A time object represented in seconds since the unix epoch.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<float, float>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 61,
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
      'nanoSeconds' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'name' => 'nanoSeconds',
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 9,
        'endColumn' => 41,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tzOffsetSeconds' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'name' => 'tzOffsetSeconds',
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
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 9,
        'endColumn' => 45,
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
          'nanoSeconds' => 
          array (
            'name' => 'nanoSeconds',
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
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 9,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'tzOffsetSeconds' => 
          array (
            'name' => 'tzOffsetSeconds',
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
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 9,
            'endColumn' => 45,
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
        'startLine' => 28,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Time',
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
 * @return array{nanoSeconds: int, tzOffsetSeconds: int}
 */',
        'startLine' => 37,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'aliasName' => NULL,
      ),
      'getTzOffsetSeconds' => 
      array (
        'name' => 'getTzOffsetSeconds',
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
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'aliasName' => NULL,
      ),
      'getNanoSeconds' => 
      array (
        'name' => 'getNanoSeconds',
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
        'startLine' => 47,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Time',
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
        'startLine' => 52,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Time',
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
        'startLine' => 57,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Time',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Time',
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