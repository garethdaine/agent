<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/WGS84Point.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Types\WGS84Point
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-bfcb91d05366f6f489cb2d434c80e4e2e4c0af8a12d8c48216cadff8fd1b8715-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/WGS84Point.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Types',
    'name' => 'Laudis\\Neo4j\\Types\\WGS84Point',
    'shortName' => 'WGS84Point',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A WGS84 Point in two dimensional space.
 *
 * @psalm-immutable
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-wgs84-2d
 *
 * @psalm-import-type Crs from \\Laudis\\Neo4j\\Contracts\\PointInterface
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 58,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laudis\\Neo4j\\Types\\AbstractPoint',
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'name' => 'SRID',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4326',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 56,
            'startFilePos' => 773,
            'endTokenPos' => 56,
            'endFilePos' => 776,
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'name' => 'CRS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'wgs-84\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 67,
            'startFilePos' => 802,
            'endTokenPos' => 67,
            'endFilePos' => 809,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 32,
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'aliasName' => NULL,
      ),
      'getLongitude' => 
      array (
        'name' => 'getLongitude',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * A numeric expression that represents the longitude/x value in decimal degrees.
 */',
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'aliasName' => NULL,
      ),
      'getLatitude' => 
      array (
        'name' => 'getLatitude',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * A numeric expression that represents the latitude/y value in decimal degrees.
 */',
        'startLine' => 54,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS84Point',
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