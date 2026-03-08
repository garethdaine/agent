<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendRepoAnalysisCompletedNotification.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Listeners\Messenger\SendRepoAnalysisCompletedNotification
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-352eecc3c1046cd72f53ed60962e4ce685f97fd038394f246d393375646d288b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'filename' => '/Users/garethdaine/Code/agent/app/Listeners/Messenger/SendRepoAnalysisCompletedNotification.php',
      ),
    ),
    'namespace' => 'App\\Listeners\\Messenger',
    'name' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
    'shortName' => 'SendRepoAnalysisCompletedNotification',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Sends a messenger notification when a code analysis session reaches a terminal state.
 *
 * Only fires for terminal status values (completed, failed).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 61,
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
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
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
            'startFilePos' => 502,
            'endTokenPos' => 56,
            'endFilePos' => 524,
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
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
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
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
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
                'name' => 'App\\Events\\RepoAnalysisSessionUpdated',
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
            'endColumn' => 60,
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
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Messenger',
        'declaringClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'implementingClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
        'currentClassName' => 'App\\Listeners\\Messenger\\SendRepoAnalysisCompletedNotification',
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