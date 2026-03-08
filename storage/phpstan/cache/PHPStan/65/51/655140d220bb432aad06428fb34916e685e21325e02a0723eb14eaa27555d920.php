<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/AgentConnectorCredential.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\AgentConnectorCredential
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-777fef575c221c1c7cf7099fbeb0a54db3452885386f4e5ec30d4f2302bc998d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\AgentConnectorCredential',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/AgentConnectorCredential.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\AgentConnectorCredential',
    'shortName' => 'AgentConnectorCredential',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin Builder
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 88,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
      1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
    ),
    'immediateConstants' => 
    array (
      'STATUS_ACTIVE' => 
      array (
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'name' => 'STATUS_ACTIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'active\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 65,
            'startFilePos' => 463,
            'endTokenPos' => 65,
            'endFilePos' => 470,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'STATUS_DEGRADED' => 
      array (
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'name' => 'STATUS_DEGRADED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'degraded\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 76,
            'startFilePos' => 509,
            'endTokenPos' => 76,
            'endFilePos' => 518,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 46,
      ),
      'STATUS_EXPIRED' => 
      array (
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'name' => 'STATUS_EXPIRED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'expired\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 87,
            'startFilePos' => 556,
            'endTokenPos' => 87,
            'endFilePos' => 564,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'STATUS_REVOKED' => 
      array (
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'name' => 'STATUS_REVOKED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'revoked\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 98,
            'startFilePos' => 602,
            'endTokenPos' => 98,
            'endFilePos' => 610,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'team_id\', \'connector_id\', \'auth_type\', \'encrypted_data\', \'encryption_key_id\', \'scopes_granted\', \'token_expires_at\', \'refresh_token_expires_at\', \'status\', \'last_refreshed_at\', \'last_used_at\', \'refresh_failure_count\', \'created_by\', \'updated_by\', \'revoked_by\', \'revoked_at\', \'rotation_count\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 45,
            'startTokenPos' => 107,
            'startFilePos' => 640,
            'endTokenPos' => 160,
            'endFilePos' => 1073,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 6,
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
      'casts' => 
      array (
        'name' => 'casts',
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
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
        'aliasName' => NULL,
      ),
      'team' => 
      array (
        'name' => 'team',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 59,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
        'aliasName' => NULL,
      ),
      'connector' => 
      array (
        'name' => 'connector',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 64,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
        'aliasName' => NULL,
      ),
      'createdBy' => 
      array (
        'name' => 'createdBy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 69,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
        'aliasName' => NULL,
      ),
      'updatedBy' => 
      array (
        'name' => 'updatedBy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 74,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
        'aliasName' => NULL,
      ),
      'revokedBy' => 
      array (
        'name' => 'revokedBy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 79,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
        'aliasName' => NULL,
      ),
      'events' => 
      array (
        'name' => 'events',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 84,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AgentConnectorCredential',
        'implementingClassName' => 'App\\Models\\AgentConnectorCredential',
        'currentClassName' => 'App\\Models\\AgentConnectorCredential',
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