<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/Cartesian3DPoint.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Types\Cartesian3DPoint
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-da7965e27224deb4c51307b88ad5051ff2465ea98e741bc02665dbb072efcfca-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/Cartesian3DPoint.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Types',
    'name' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
    'shortName' => 'Cartesian3DPoint',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A cartesian point in three dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-cartesian-3d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from \\Laudis\\Neo4j\\Contracts\\PointInterface
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 42,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laudis\\Neo4j\\Types\\Abstract3DPoint',
    'implementsClassNames' => 
    array (
      0 => 'Laudis\\Neo4j\\Contracts\\PointInterface',
      1 => 'Laudis\\Neo4j\\Contracts\\BoltConvertibleInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'SRID' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'name' => 'SRID',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '9157',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 56,
            'startFilePos' => 791,
            'endTokenPos' => 56,
            'endFilePos' => 794,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'CRS' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'name' => 'CRS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'cartesian-3d\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 67,
            'startFilePos' => 820,
            'endTokenPos' => 67,
            'endFilePos' => 833,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'getSrid' => 
      array (
        'name' => 'getSrid',
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
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'aliasName' => NULL,
      ),
      'getCrs' => 
      array (
        'name' => 'getCrs',
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
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\Cartesian3DPoint',
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