<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendDelegationTaskFailedNotification.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Listeners\Messenger\SendDelegationTaskFailedNotification
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-42d21c9bbe11855e80cf658c127a442af25d1ba3403dd3b6b121861227f546dd',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
        'filename' => '/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendDelegationTaskFailedNotification.php',
      ),
    ),
    'namespace' => 'App\\Listeners\\Messenger',
    'name' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
    'shortName' => 'SendDelegationTaskFailedNotification',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Sends a messenger notification when a delegation task fails verification.
 *
 * Only fires when verification fails ($event->passed === false).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 62,
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
      'dispatcher' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
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
        'startLine' => 19,
        'endLine' => 19,
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
            'startLine' => 19,
            'endLine' => 19,
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
        'startLine' => 18,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Messenger',
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
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
                'name' => 'App\\Events\\DelegationTaskVerified',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 28,
            'endColumn' => 56,
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
        'startLine' => 22,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Messenger',
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendDelegationTaskFailedNotification',
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