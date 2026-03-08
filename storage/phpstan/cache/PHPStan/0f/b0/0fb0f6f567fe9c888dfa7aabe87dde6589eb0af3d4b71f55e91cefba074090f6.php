<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendAgentJobFinishedNotification.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Listeners\Messenger\SendAgentJobFinishedNotification
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-4547441733c987df75a103724307b2216ce0238c701297401099a69f6a0cf89d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'filename' => '/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendAgentJobFinishedNotification.php',
      ),
    ),
    'namespace' => 'App\\Listeners\\Messenger',
    'name' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
    'shortName' => 'SendAgentJobFinishedNotification',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Sends a messenger notification when an agent job run fails.
 *
 * Only fires for terminal failure statuses: failed, killed, timed_out.
 * Succeeded runs are intentionally silent to avoid notification fatigue.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 67,
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
      'FAILURE_STATUSES' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'name' => 'FAILURE_STATUSES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'failed\', \'killed\', \'timed_out\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 46,
            'startFilePos' => 511,
            'endTokenPos' => 54,
            'endFilePos' => 543,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 71,
      ),
    ),
    'immediateProperties' => 
    array (
      'dispatcher' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'name' => 'dispatcher',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 65,
        'isPromoted' => true,
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
          'dispatcher' => 
          array (
            'name' => 'dispatcher',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 9,
            'endColumn' => 65,
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
        'startLine' => 21,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Messenger',
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Events\\AgentJobRunFinished',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 28,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 25,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Messenger',
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendAgentJobFinishedNotification',
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