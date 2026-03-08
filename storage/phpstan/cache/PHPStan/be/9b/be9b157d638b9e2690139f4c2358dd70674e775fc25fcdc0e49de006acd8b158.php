<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Contracts/PointInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Contracts\PointInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-18309b1eac3b23e3333ffbdfcca29376dda91bb66b23cd1b88942d94117b379b-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Contracts/PointInterface.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Contracts',
    'name' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
    'shortName' => 'PointInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Defines a basic Point type in neo4j.
 *
 * @psalm-immutable
 *
 * @psalm-type Crs = \'wgs-84\'|\'wgs-84-3d\'|\'cartesian\'|\'cartesian-3d\';
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 50,
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
      'getX' => 
      array (
        'name' => 'getX',
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
 * Returns the x coordinate.
 */',
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'aliasName' => NULL,
      ),
      'getY' => 
      array (
        'name' => 'getY',
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
 * Returns the y coordinate.
 */',
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
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
        'docComment' => '/**
 * Returns the Coordinates Reference System.
 *
 * @see https://en.wikipedia.org/wiki/Spatial_reference_system
 *
 * @return Crs
 */',
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 37,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'aliasName' => NULL,
      ),
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
        'docComment' => '/**
 * Returns the spacial reference identifier.
 *
 * @see https://en.wikipedia.org/wiki/Spatial_reference_system
 */',
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 35,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\PointInterface',
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