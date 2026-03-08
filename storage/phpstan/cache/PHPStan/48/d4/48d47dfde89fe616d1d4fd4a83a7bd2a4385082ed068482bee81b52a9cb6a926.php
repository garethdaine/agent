<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../ratchet/pawl/src/WebSocket.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Ratchet\Client\WebSocket
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6e4b1855d2373e9808fc7015e263570934e374b61ae077f7b75b473a44154f31-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Ratchet\\Client\\WebSocket',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../ratchet/pawl/src/WebSocket.php',
      ),
    ),
    'namespace' => 'Ratchet\\Client',
    'name' => 'Ratchet\\Client\\WebSocket',
    'shortName' => 'WebSocket',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 157,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Evenement\\EventEmitterInterface',
    ),
    'traitClassNames' => 
    array (
      0 => 'Evenement\\EventEmitterTrait',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'request' => 
      array (
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'name' => 'request',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The request headers sent to establish the connection
 * @var \\Psr\\Http\\Message\\RequestInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'response' => 
      array (
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'name' => 'response',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The response headers received from the server to establish the connection
 * @var \\Psr\\Http\\Message\\ResponseInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      '_stream' => 
      array (
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'name' => '_stream',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var \\React\\Socket\\ConnectionInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      '_close' => 
      array (
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'name' => '_close',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var \\Closure
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 22,
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
          'stream' => 
          array (
            'name' => 'stream',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'React\\Socket\\ConnectionInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 33,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\ResponseInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 62,
            'endColumn' => 88,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\RequestInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 91,
            'endColumn' => 115,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * WebSocket constructor.
 * @param \\React\\Socket\\ConnectionInterface $stream
 * @param \\Psr\\Http\\Message\\ResponseInterface $response
 * @param \\Psr\\Http\\Message\\RequestInterface  $request
 * @event message
 * @event ping
 * @event pong
 * @event close
 * @event error
 */',
        'startLine' => 50,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\Client',
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'currentClassName' => 'Ratchet\\Client\\WebSocket',
        'aliasName' => NULL,
      ),
      'send' => 
      array (
        'name' => 'send',
        'parameters' => 
        array (
          'msg' => 
          array (
            'name' => 'msg',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 122,
            'endLine' => 122,
            'startColumn' => 26,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 122,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\Client',
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'currentClassName' => 'Ratchet\\Client\\WebSocket',
        'aliasName' => NULL,
      ),
      'close' => 
      array (
        'name' => 'close',
        'parameters' => 
        array (
          'code' => 
          array (
            'name' => 'code',
            'default' => 
            array (
              'code' => '1000',
              'attributes' => 
              array (
                'startLine' => 137,
                'endLine' => 137,
                'startTokenPos' => 821,
                'startFilePos' => 4331,
                'endTokenPos' => 821,
                'endFilePos' => 4334,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 27,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'reason' => 
          array (
            'name' => 'reason',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 137,
                'endLine' => 137,
                'startTokenPos' => 828,
                'startFilePos' => 4347,
                'endTokenPos' => 828,
                'endFilePos' => 4348,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 41,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 137,
        'endLine' => 146,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\Client',
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'currentClassName' => 'Ratchet\\Client\\WebSocket',
        'aliasName' => NULL,
      ),
      'pause' => 
      array (
        'name' => 'pause',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 148,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\Client',
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'currentClassName' => 'Ratchet\\Client\\WebSocket',
        'aliasName' => NULL,
      ),
      'resume' => 
      array (
        'name' => 'resume',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 153,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Ratchet\\Client',
        'declaringClassName' => 'Ratchet\\Client\\WebSocket',
        'implementingClassName' => 'Ratchet\\Client\\WebSocket',
        'currentClassName' => 'Ratchet\\Client\\WebSocket',
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