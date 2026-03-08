<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../ratchet/rfc6455/src/Messaging/DataInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Ratchet\RFC6455\Messaging\DataInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-df5404a81e80aa5c1fa4f61eeddd37a2e70a2bc0dd4bbff8973ac72117aa73c5-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../ratchet/rfc6455/src/Messaging/DataInterface.php',
      ),
    ),
    'namespace' => 'Ratchet\\RFC6455\\Messaging',
    'name' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
    'shortName' => 'DataInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 4,
    'endLine' => 28,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Stringable',
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
      'isCoalesced' => 
      array (
        'name' => 'isCoalesced',
        'parameters' => 
        array (
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
 * Determine if the message is complete or still fragmented
 * @return bool
 */',
        'startLine' => 9,
        'endLine' => 9,
        'startColumn' => 5,
        'endColumn' => 40,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\RFC6455\\Messaging',
        'declaringClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'implementingClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'currentClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'aliasName' => NULL,
      ),
      'getPayloadLength' => 
      array (
        'name' => 'getPayloadLength',
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
 * Get the number of bytes the payload is set to be
 * @return int
 */',
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 44,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\RFC6455\\Messaging',
        'declaringClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'implementingClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'currentClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'aliasName' => NULL,
      ),
      'getPayload' => 
      array (
        'name' => 'getPayload',
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
 * Get the payload (message) sent from peer
 * @return string
 */',
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 41,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\RFC6455\\Messaging',
        'declaringClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'implementingClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'currentClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'aliasName' => NULL,
      ),
      'getContents' => 
      array (
        'name' => 'getContents',
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
 * Get raw contents of the message
 * @return string
 */',
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 42,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\RFC6455\\Messaging',
        'declaringClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'implementingClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
        'currentClassName' => 'Ratchet\\RFC6455\\Messaging\\DataInterface',
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