<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Reliability/AssistedSlaExpiryReclassifier.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Reliability\AssistedSlaExpiryReclassifier
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-7234f4ff2f3800be8b62e3e4aaf0e05661e8a249534016920f801a06f21976a0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Reliability/AssistedSlaExpiryReclassifier.php',
      ),
    ),
    'namespace' => 'App\\Services\\Reliability',
    'name' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
    'shortName' => 'AssistedSlaExpiryReclassifier',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 45,
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
      'clock' => 
      array (
        'declaringClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'implementingClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'name' => 'clock',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Time\\AgentClock',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 9,
        'endColumn' => 42,
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
          'clock' => 
          array (
            'name' => 'clock',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Time\\AgentClock',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 15,
            'endLine' => 15,
            'startColumn' => 9,
            'endColumn' => 42,
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
        'startLine' => 14,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Reliability',
        'declaringClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'implementingClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'currentClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'aliasName' => NULL,
      ),
      'reclassifyExpired' => 
      array (
        'name' => 'reclassifyExpired',
        'parameters' => 
        array (
          'chunkSize' => 
          array (
            'name' => 'chunkSize',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 18,
                'endLine' => 18,
                'startTokenPos' => 76,
                'startFilePos' => 405,
                'endTokenPos' => 76,
                'endFilePos' => 407,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 39,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
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
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Reliability',
        'declaringClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'implementingClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
        'currentClassName' => 'App\\Services\\Reliability\\AssistedSlaExpiryReclassifier',
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