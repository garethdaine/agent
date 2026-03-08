<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Messenger/SystemNotificationDispatcher.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Messenger\SystemNotificationDispatcher
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-dc540e3f99d843278e2511d54c17d450a679bfe6b9505f0515d4bcb78420b119',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Messenger/SystemNotificationDispatcher.php',
      ),
    ),
    'namespace' => 'App\\Services\\Messenger',
    'name' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
    'shortName' => 'SystemNotificationDispatcher',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Routes system event notifications to eligible messenger channels.
 *
 * Finds ConnectorAccounts linked to the payload\'s user via MessengerIdentityLink,
 * filters by notification level and event type whitelist, then dispatches
 * SendOutboundMessage jobs for delivery.
 *
 * Notifications are best-effort — failures are logged but never thrown.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 186,
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
      'SEVERITY_EMOJI' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'name' => 'SEVERITY_EMOJI',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_INFO => \'ℹ️\', \\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_WARNING => \'⚠️\', \\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_ERROR => \'🔴\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 31,
            'startTokenPos' => 71,
            'startFilePos' => 778,
            'endTokenPos' => 100,
            'endFilePos' => 972,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'LEVEL_SEVERITY_MAP' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'name' => 'LEVEL_SEVERITY_MAP',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'escalations_only\' => [\\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_ERROR], \'lifecycle\' => [\\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_ERROR, \\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_WARNING, \\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_INFO], \'verbose\' => [\\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_ERROR, \\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_WARNING, \\App\\DTOs\\Messenger\\SystemNotificationPayload::SEVERITY_INFO]]',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 51,
            'startTokenPos' => 113,
            'startFilePos' => 1205,
            'endTokenPos' => 174,
            'endFilePos' => 1688,
          ),
        ),
        'docComment' => '/**
 * Notification levels ordered by verbosity (most restrictive first).
 * Maps each level to the severities it allows.
 *
 * @var array<string, array<string>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'featureFlagManager' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'name' => 'featureFlagManager',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Agent\\FeatureFlagManager',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 9,
        'endColumn' => 63,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'chatSessionManager' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'name' => 'chatSessionManager',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Messenger\\ChatSessionManager',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 9,
        'endColumn' => 63,
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
          'featureFlagManager' => 
          array (
            'name' => 'featureFlagManager',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Agent\\FeatureFlagManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 9,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'chatSessionManager' => 
          array (
            'name' => 'chatSessionManager',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Messenger\\ChatSessionManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 55,
            'endLine' => 55,
            'startColumn' => 9,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'currentClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'aliasName' => NULL,
      ),
      'dispatch' => 
      array (
        'name' => 'dispatch',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Messenger\\SystemNotificationPayload',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 30,
            'endColumn' => 63,
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
        'docComment' => '/**
 * Dispatch a system notification to all eligible messenger accounts for the user.
 */',
        'startLine' => 61,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'currentClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'aliasName' => NULL,
      ),
      'shouldNotify' => 
      array (
        'name' => 'shouldNotify',
        'parameters' => 
        array (
          'configLevel' => 
          array (
            'name' => 'configLevel',
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
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 34,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'severity' => 
          array (
            'name' => 'severity',
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
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 55,
            'endColumn' => 70,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check whether a notification level allows the given severity.
 */',
        'startLine' => 85,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'currentClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'aliasName' => NULL,
      ),
      'findEligibleAccounts' => 
      array (
        'name' => 'findEligibleAccounts',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Messenger\\SystemNotificationPayload',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 97,
            'endLine' => 97,
            'startColumn' => 43,
            'endColumn' => 76,
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
            'name' => 'Illuminate\\Support\\Collection',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Find all ConnectorAccounts eligible to receive this notification.
 *
 * @return \\Illuminate\\Support\\Collection<int, ConnectorAccount>
 */',
        'startLine' => 97,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'currentClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'aliasName' => NULL,
      ),
      'sendToAccount' => 
      array (
        'name' => 'sendToAccount',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 36,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Messenger\\SystemNotificationPayload',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 63,
            'endColumn' => 96,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send the notification to a specific ConnectorAccount.
 */',
        'startLine' => 141,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'currentClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'aliasName' => NULL,
      ),
      'formatMessage' => 
      array (
        'name' => 'formatMessage',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Messenger\\SystemNotificationPayload',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 180,
            'endLine' => 180,
            'startColumn' => 36,
            'endColumn' => 69,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Format the notification payload into a markdown message.
 */',
        'startLine' => 180,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'implementingClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
        'currentClassName' => 'App\\Services\\Messenger\\SystemNotificationDispatcher',
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