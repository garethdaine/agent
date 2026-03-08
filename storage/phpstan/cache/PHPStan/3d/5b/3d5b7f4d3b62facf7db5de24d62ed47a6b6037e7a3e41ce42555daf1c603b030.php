<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Contracts/HasPropertiesInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Contracts\HasPropertiesInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c477cdf16b1f9ecdbdcb9497df62a3238382fac73fd134851304a489c0ee20b8-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Contracts/HasPropertiesInterface.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Contracts',
    'name' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
    'shortName' => 'HasPropertiesInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Defines how an object with properties should behave.
 *
 * @psalm-immutable
 *
 * @template T
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 58,
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
        'docComment' => '/**
 * Returns the properties a map.
 *
 * @return CypherMap<T>
 */',
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'aliasName' => NULL,
      ),
      '__get' => 
      array (
        'name' => '__get',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 27,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $name
 *
 * @return T
 */',
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 33,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'aliasName' => NULL,
      ),
      '__set' => 
      array (
        'name' => '__set',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 27,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 34,
            'endColumn' => 39,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Always throws an exception as cypher objects are immutable.
 *
 * @param string $name
 * @param T      $value
 *
 * @throws BadMethodCallException
 */',
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'aliasName' => NULL,
      ),
      '__isset' => 
      array (
        'name' => '__isset',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 29,
            'endColumn' => 33,
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
 * Checks to see if the property exists and is set.
 *
 * @param string $name
 */',
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 41,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\HasPropertiesInterface',
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