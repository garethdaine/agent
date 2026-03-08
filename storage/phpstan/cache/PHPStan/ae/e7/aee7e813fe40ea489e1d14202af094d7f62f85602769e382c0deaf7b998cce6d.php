<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/WGS843DPoint.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Types\WGS843DPoint
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-91bd141d10878a0985347b69fad34ccf7f981a29ed5a8960b3d408ed6ae88106-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Types/WGS843DPoint.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Types',
    'name' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
    'shortName' => 'WGS843DPoint',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A WGS84 Point in three-dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-wgs84-3d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from PointInterface
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 57,
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'name' => 'SRID',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4979',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 56,
            'startFilePos' => 755,
            'endTokenPos' => 56,
            'endFilePos' => 758,
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'name' => 'CRS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'wgs-84-3d\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 67,
            'startFilePos' => 784,
            'endTokenPos' => 67,
            'endFilePos' => 794,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 35,
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
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
        'docComment' => NULL,
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'aliasName' => NULL,
      ),
      'getHeight' => 
      array (
        'name' => 'getHeight',
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
        'docComment' => NULL,
        'startLine' => 48,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Types',
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
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
        'declaringClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'implementingClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
        'currentClassName' => 'Laudis\\Neo4j\\Types\\WGS843DPoint',
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