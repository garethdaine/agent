<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendInterrogationPhaseNotification.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Listeners\Messenger\SendInterrogationPhaseNotification
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-9c5ca1b6e818c120ebbb231027c6e5d73cfede326b01941a28aaec772a1453ed',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'filename' => '/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendInterrogationPhaseNotification.php',
      ),
    ),
    'namespace' => 'App\\Listeners\\Messenger',
    'name' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
    'shortName' => 'SendInterrogationPhaseNotification',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Sends a messenger notification on significant interrogation phase changes.
 *
 * Only fires for terminal statuses (completed, failed) to avoid noise.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 58,
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
      'TERMINAL_STATUSES' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'name' => 'TERMINAL_STATUSES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'completed\', \'failed\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 51,
            'startFilePos' => 498,
            'endTokenPos' => 56,
            'endFilePos' => 520,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 62,
      ),
    ),
    'immediateProperties' => 
    array (
      'dispatcher' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
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
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
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
                'name' => 'App\\Events\\InterrogationPhaseChanged',
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
            'endColumn' => 59,
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
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Messenger',
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendInterrogationPhaseNotification',
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