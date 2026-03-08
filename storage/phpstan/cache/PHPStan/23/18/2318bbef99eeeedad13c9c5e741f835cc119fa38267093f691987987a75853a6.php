<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Contracts/TransactionInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Contracts\TransactionInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-e94673d937b18cca2cf179d8a5760996dd44f3c1fd471c2dd58f5798d622a3db-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Contracts/TransactionInterface.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Contracts',
    'name' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
    'shortName' => 'TransactionInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Transactions are atomic units of work that may contain one or more query.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/introduction/transactions/
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 43,
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
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'statement' => 
          array (
            'name' => 'statement',
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 25,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 31,
                'endLine' => 31,
                'startTokenPos' => 64,
                'startFilePos' => 803,
                'endTokenPos' => 65,
                'endFilePos' => 804,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 44,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param iterable<string, mixed> $parameters
 */',
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 88,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'aliasName' => NULL,
      ),
      'runStatement' => 
      array (
        'name' => 'runStatement',
        'parameters' => 
        array (
          'statement' => 
          array (
            'name' => 'statement',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laudis\\Neo4j\\Databags\\Statement',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 33,
            'endLine' => 33,
            'startColumn' => 34,
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
            'name' => 'Laudis\\Neo4j\\Databags\\SummarizedResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 73,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'aliasName' => NULL,
      ),
      'runStatements' => 
      array (
        'name' => 'runStatements',
        'parameters' => 
        array (
          'statements' => 
          array (
            'name' => 'statements',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
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
            'startColumn' => 35,
            'endColumn' => 54,
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
            'name' => 'Laudis\\Neo4j\\Types\\CypherList',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param iterable<Statement> $statements
 *
 * @throws Neo4jException
 *
 * @return CypherList<SummarizedResult>
 */',
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 68,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Contracts',
        'declaringClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'implementingClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
        'currentClassName' => 'Laudis\\Neo4j\\Contracts\\TransactionInterface',
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