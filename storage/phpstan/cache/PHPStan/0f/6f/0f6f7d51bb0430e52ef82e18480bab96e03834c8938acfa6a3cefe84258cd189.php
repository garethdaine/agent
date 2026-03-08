<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/ChatAction/Handlers/StreamableHandlerInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\ChatAction\Handlers\StreamableHandlerInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-733d75f36c6d862c4bf25f235e549d93d75d618f6b05718fdc7880b068684b37',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\ChatAction\\Handlers\\StreamableHandlerInterface',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/ChatAction/Handlers/StreamableHandlerInterface.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\ChatAction\\Handlers',
    'name' => 'App\\Messenger\\ChatAction\\Handlers\\StreamableHandlerInterface',
    'shortName' => 'StreamableHandlerInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 16,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Messenger\\ChatAction\\Handlers\\ChatActionHandlerInterface',
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
      'handleStreaming' => 
      array (
        'name' => 'handleStreaming',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Messenger\\ChatAction\\ChatActionContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 15,
            'endLine' => 15,
            'startColumn' => 37,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'onChunk' => 
          array (
            'name' => 'onChunk',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 15,
            'endLine' => 15,
            'startColumn' => 65,
            'endColumn' => 81,
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
            'name' => 'App\\Messenger\\ChatAction\\ChatActionResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle the action with streaming output.
 *
 * @param  callable(string): void  $onChunk  Called with each chunk of output as it becomes available
 */',
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 101,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\ChatAction\\Handlers',
        'declaringClassName' => 'App\\Messenger\\ChatAction\\Handlers\\StreamableHandlerInterface',
        'implementingClassName' => 'App\\Messenger\\ChatAction\\Handlers\\StreamableHandlerInterface',
        'currentClassName' => 'App\\Messenger\\ChatAction\\Handlers\\StreamableHandlerInterface',
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