<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Exception/Neo4jException.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laudis\Neo4j\Exception\Neo4jException
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-1d5b4297160d2107c4cc06d6c39563fc8f6a994c7658ed20273eace21f7bb2ef-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laudis/neo4j-php-client/src/Exception/Neo4jException.php',
      ),
    ),
    'namespace' => 'Laudis\\Neo4j\\Exception',
    'name' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
    'shortName' => 'Neo4jException',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Exception when a Neo4j Error occurs.
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 85,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'RuntimeException',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'MESSAGE_TEMPLATE' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'name' => 'MESSAGE_TEMPLATE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'Neo4j errors detected. First one with code "%s" and message "%s"\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 59,
            'startFilePos' => 616,
            'endTokenPos' => 59,
            'endFilePos' => 681,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 104,
      ),
    ),
    'immediateProperties' => 
    array (
      'errors' => 
      array (
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'name' => 'errors',
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
        'docComment' => '/** @var non-empty-list<Neo4jError> */',
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 26,
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
          'errors' => 
          array (
            'name' => 'errors',
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'previous' => 
          array (
            'name' => 'previous',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 37,
                'endLine' => 37,
                'startTokenPos' => 91,
                'startFilePos' => 890,
                'endTokenPos' => 91,
                'endFilePos' => 893,
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
                      'name' => 'Throwable',
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
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 48,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param non-empty-list<Neo4jError> $errors
 */',
        'startLine' => 37,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'fromBoltResponse' => 
      array (
        'name' => 'fromBoltResponse',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Bolt\\protocol\\Response',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 45,
            'endColumn' => 62,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @pure
 */',
        'startLine' => 48,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'getErrors' => 
      array (
        'name' => 'getErrors',
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
 * @return non-empty-list<Neo4jError>
 */',
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'getNeo4jCode' => 
      array (
        'name' => 'getNeo4jCode',
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
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'getNeo4jMessage' => 
      array (
        'name' => 'getNeo4jMessage',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
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
                  'name' => 'string',
                  'isIdentifier' => true,
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
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 66,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'getCategory' => 
      array (
        'name' => 'getCategory',
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
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'getClassification' => 
      array (
        'name' => 'getClassification',
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
        'startLine' => 76,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'aliasName' => NULL,
      ),
      'getTitle' => 
      array (
        'name' => 'getTitle',
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
        'startLine' => 81,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laudis\\Neo4j\\Exception',
        'declaringClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'implementingClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
        'currentClassName' => 'Laudis\\Neo4j\\Exception\\Neo4jException',
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