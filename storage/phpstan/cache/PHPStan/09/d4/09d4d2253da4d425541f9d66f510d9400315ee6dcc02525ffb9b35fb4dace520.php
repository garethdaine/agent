<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Databags/SummarizedResult.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Databags\SummarizedResult
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-ad72f8499e2f65b2a8ed924b73b2ec6ad263ebfb4ef7426656811564d3c6a24f-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Databags/SummarizedResult.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Databags',
    'name' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
    'shortName' => 'SummarizedResult',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A result containing the values and the summary.
 *
 * @psalm-import-type OGMTypes from SummarizedResultFormatter
 *
 * @extends CypherList<CypherMap<OGMTypes>>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 88,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laudis\\Neo4j\\Types\\CypherList',
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
      'summary' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'name' => 'summary',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'Laudis\\Neo4j\\Databags\\ResultSummary',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 60,
            'startFilePos' => 707,
            'endTokenPos' => 60,
            'endFilePos' => 710,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'keys' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'name' => 'keys',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * @var list<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
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
          'summary' => 
          array (
            'name' => 'summary',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Laudis\\Neo4j\\Databags\\ResultSummary',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 33,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'iterable' => 
          array (
            'name' => 'iterable',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 42,
                'endLine' => 42,
                'startTokenPos' => 95,
                'startFilePos' => 1135,
                'endTokenPos' => 96,
                'endFilePos' => 1136,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'iterable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 59,
            'endColumn' => 90,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'keys' => 
          array (
            'name' => 'keys',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 42,
                'endLine' => 42,
                'startTokenPos' => 105,
                'startFilePos' => 1153,
                'endTokenPos' => 106,
                'endFilePos' => 1154,
              ),
            ),
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
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 93,
            'endColumn' => 108,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @psalm-mutation-free
 *
 * @param iterable<mixed, CypherMap<OGMTypes>>|callable():Generator<mixed, CypherMap<OGMTypes>> $iterable
 * @param list<string>                                                                          $keys
 */',
        'startLine' => 42,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Databags',
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'currentClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'aliasName' => NULL,
      ),
      'getSummary' => 
      array (
        'name' => 'getSummary',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Laudis\\Neo4j\\Databags\\ResultSummary',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the result summary.
 */',
        'startLine' => 52,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Databags',
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'currentClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'aliasName' => NULL,
      ),
      'getResults' => 
      array (
        'name' => 'getResults',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Laudis\\Neo4j\\Types\\CypherList',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return CypherList<CypherMap<OGMTypes>>
 */',
        'startLine' => 65,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Databags',
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'currentClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'aliasName' => NULL,
      ),
      'jsonSerialize' => 
      array (
        'name' => 'jsonSerialize',
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
 * @return array{summary: ResultSummary|null, result: mixed}
 */',
        'startLine' => 73,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Databags',
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'currentClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'aliasName' => NULL,
      ),
      'keys' => 
      array (
        'name' => 'keys',
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
 * @return list<string>
 */',
        'startLine' => 84,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Databags',
        'declaringClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'implementingClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
        'currentClassName' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
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