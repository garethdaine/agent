<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Jetstream.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Jetstream\Jetstream
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-a8cee1ad8fd43805f083dd223ee59862721fd22702f9476359e3540f9c8b7025-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Jetstream\\Jetstream',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Jetstream.php',
      ),
    ),
    'namespace' => 'Laravel\\Jetstream',
    'name' => 'Laravel\\Jetstream\\Jetstream',
    'shortName' => 'Jetstream',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 490,
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
      'registersRoutes' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'registersRoutes',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 68,
            'startFilePos' => 599,
            'endTokenPos' => 68,
            'endFilePos' => 602,
          ),
        ),
        'docComment' => '/**
 * Indicates if Jetstream routes will be registered.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'roles' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'roles',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 81,
            'startFilePos' => 730,
            'endTokenPos' => 82,
            'endFilePos' => 731,
          ),
        ),
        'docComment' => '/**
 * The roles that are available to assign to users.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'permissions' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'permissions',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 95,
            'startFilePos' => 867,
            'endTokenPos' => 96,
            'endFilePos' => 868,
          ),
        ),
        'docComment' => '/**
 * The permissions that exist within the application.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultPermissions' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'defaultPermissions',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 109,
            'startFilePos' => 1026,
            'endTokenPos' => 110,
            'endFilePos' => 1027,
          ),
        ),
        'docComment' => '/**
 * The default permissions that should be available to new entities.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'userModel' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'userModel',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'App\\Models\\User\'',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 123,
            'startFilePos' => 1160,
            'endTokenPos' => 123,
            'endFilePos' => 1178,
          ),
        ),
        'docComment' => '/**
 * The user model that should be used by Jetstream.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 51,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'teamModel' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'teamModel',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'App\\Models\\Team\'',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 136,
            'startFilePos' => 1311,
            'endTokenPos' => 136,
            'endFilePos' => 1329,
          ),
        ),
        'docComment' => '/**
 * The team model that should be used by Jetstream.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 51,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'membershipModel' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'membershipModel',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'App\\Models\\Membership\'',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 149,
            'startFilePos' => 1474,
            'endTokenPos' => 149,
            'endFilePos' => 1498,
          ),
        ),
        'docComment' => '/**
 * The membership model that should be used by Jetstream.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 63,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'teamInvitationModel' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'teamInvitationModel',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'App\\Models\\TeamInvitation\'',
          'attributes' => 
          array (
            'startLine' => 71,
            'endLine' => 71,
            'startTokenPos' => 162,
            'startFilePos' => 1652,
            'endTokenPos' => 162,
            'endFilePos' => 1680,
          ),
        ),
        'docComment' => '/**
 * The team invitation model that should be used by Jetstream.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 71,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'inertiaManager' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'name' => 'inertiaManager',
        'modifiers' => 17,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Inertia manager instance.
 *
 * @var \\Laravel\\Jetstream\\InertiaManager
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 34,
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
      'hasRoles' => 
      array (
        'name' => 'hasRoles',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if Jetstream has registered roles.
 *
 * @return bool
 */',
        'startLine' => 85,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'findRole' => 
      array (
        'name' => 'findRole',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 96,
            'endLine' => 96,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Find the role with the given key.
 *
 * @param  string  $key
 * @return \\Laravel\\Jetstream\\Role|null
 */',
        'startLine' => 96,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'role' => 
      array (
        'name' => 'role',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 33,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'permissions' => 
          array (
            'name' => 'permissions',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 60,
            'endColumn' => 77,
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
 * Define a role.
 *
 * @param  string  $key
 * @param  string  $name
 * @param  array  $permissions
 * @return \\Laravel\\Jetstream\\Role
 */',
        'startLine' => 109,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'hasPermissions' => 
      array (
        'name' => 'hasPermissions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if any permissions have been registered with Jetstream.
 *
 * @return bool
 */',
        'startLine' => 127,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'permissions' => 
      array (
        'name' => 'permissions',
        'parameters' => 
        array (
          'permissions' => 
          array (
            'name' => 'permissions',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 40,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Define the available API token permissions.
 *
 * @param  array  $permissions
 * @return static
 */',
        'startLine' => 138,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'defaultApiTokenPermissions' => 
      array (
        'name' => 'defaultApiTokenPermissions',
        'parameters' => 
        array (
          'permissions' => 
          array (
            'name' => 'permissions',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 55,
            'endColumn' => 72,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Define the default permissions that should be available to new API tokens.
 *
 * @param  array  $permissions
 * @return static
 */',
        'startLine' => 151,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'validPermissions' => 
      array (
        'name' => 'validPermissions',
        'parameters' => 
        array (
          'permissions' => 
          array (
            'name' => 'permissions',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 164,
            'endLine' => 164,
            'startColumn' => 45,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the permissions in the given list that are actually defined permissions for the application.
 *
 * @param  array  $permissions
 * @return array
 */',
        'startLine' => 164,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'managesProfilePhotos' => 
      array (
        'name' => 'managesProfilePhotos',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if Jetstream is managing profile photos.
 *
 * @return bool
 */',
        'startLine' => 174,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'hasApiFeatures' => 
      array (
        'name' => 'hasApiFeatures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if Jetstream is supporting API features.
 *
 * @return bool
 */',
        'startLine' => 184,
        'endLine' => 187,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'hasTeamFeatures' => 
      array (
        'name' => 'hasTeamFeatures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if Jetstream is supporting team features.
 *
 * @return bool
 */',
        'startLine' => 194,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'userHasTeamFeatures' => 
      array (
        'name' => 'userHasTeamFeatures',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 205,
            'endLine' => 205,
            'startColumn' => 48,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if a given user model utilizes the "HasTeams" trait.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Model
 * @return bool
 */',
        'startLine' => 205,
        'endLine' => 210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'hasTermsAndPrivacyPolicyFeature' => 
      array (
        'name' => 'hasTermsAndPrivacyPolicyFeature',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is using the terms confirmation feature.
 *
 * @return bool
 */',
        'startLine' => 217,
        'endLine' => 220,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'hasAccountDeletionFeatures' => 
      array (
        'name' => 'hasAccountDeletionFeatures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is using any account deletion features.
 *
 * @return bool
 */',
        'startLine' => 227,
        'endLine' => 230,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'findUserByIdOrFail' => 
      array (
        'name' => 'findUserByIdOrFail',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 238,
            'endLine' => 238,
            'startColumn' => 47,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Find a user instance by the given ID.
 *
 * @param  int  $id
 * @return mixed
 */',
        'startLine' => 238,
        'endLine' => 241,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'findUserByEmailOrFail' => 
      array (
        'name' => 'findUserByEmailOrFail',
        'parameters' => 
        array (
          'email' => 
          array (
            'name' => 'email',
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
            'startLine' => 249,
            'endLine' => 249,
            'startColumn' => 50,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Find a user instance by the given email address or fail.
 *
 * @param  string  $email
 * @return mixed
 */',
        'startLine' => 249,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'userModel' => 
      array (
        'name' => 'userModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the name of the user model used by the application.
 *
 * @return string
 */',
        'startLine' => 259,
        'endLine' => 262,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'newUserModel' => 
      array (
        'name' => 'newUserModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new instance of the user model.
 *
 * @return mixed
 */',
        'startLine' => 269,
        'endLine' => 274,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'useUserModel' => 
      array (
        'name' => 'useUserModel',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 282,
            'endLine' => 282,
            'startColumn' => 41,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Specify the user model that should be used by Jetstream.
 *
 * @param  string  $model
 * @return static
 */',
        'startLine' => 282,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'teamModel' => 
      array (
        'name' => 'teamModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the name of the team model used by the application.
 *
 * @return string
 */',
        'startLine' => 294,
        'endLine' => 297,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'newTeamModel' => 
      array (
        'name' => 'newTeamModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new instance of the team model.
 *
 * @return mixed
 */',
        'startLine' => 304,
        'endLine' => 309,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'useTeamModel' => 
      array (
        'name' => 'useTeamModel',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 41,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Specify the team model that should be used by Jetstream.
 *
 * @param  string  $model
 * @return static
 */',
        'startLine' => 317,
        'endLine' => 322,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'membershipModel' => 
      array (
        'name' => 'membershipModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the name of the membership model used by the application.
 *
 * @return string
 */',
        'startLine' => 329,
        'endLine' => 332,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'useMembershipModel' => 
      array (
        'name' => 'useMembershipModel',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 340,
            'endLine' => 340,
            'startColumn' => 47,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Specify the membership model that should be used by Jetstream.
 *
 * @param  string  $model
 * @return static
 */',
        'startLine' => 340,
        'endLine' => 345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'teamInvitationModel' => 
      array (
        'name' => 'teamInvitationModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the name of the team invitation model used by the application.
 *
 * @return string
 */',
        'startLine' => 352,
        'endLine' => 355,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'useTeamInvitationModel' => 
      array (
        'name' => 'useTeamInvitationModel',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 363,
            'endLine' => 363,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Specify the team invitation model that should be used by Jetstream.
 *
 * @param  string  $model
 * @return static
 */',
        'startLine' => 363,
        'endLine' => 368,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'createTeamsUsing' => 
      array (
        'name' => 'createTeamsUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 376,
            'endLine' => 376,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to create teams.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 376,
        'endLine' => 379,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'updateTeamNamesUsing' => 
      array (
        'name' => 'updateTeamNamesUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 49,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to update team names.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 387,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'addTeamMembersUsing' => 
      array (
        'name' => 'addTeamMembersUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 398,
            'endLine' => 398,
            'startColumn' => 48,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to add team members.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 398,
        'endLine' => 401,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'inviteTeamMembersUsing' => 
      array (
        'name' => 'inviteTeamMembersUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to add team members.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 409,
        'endLine' => 412,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'removeTeamMembersUsing' => 
      array (
        'name' => 'removeTeamMembersUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 420,
            'endLine' => 420,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to remove team members.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 420,
        'endLine' => 423,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'deleteTeamsUsing' => 
      array (
        'name' => 'deleteTeamsUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 431,
            'endLine' => 431,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to delete teams.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 431,
        'endLine' => 434,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'deleteUsersUsing' => 
      array (
        'name' => 'deleteUsersUsing',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
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
            'startLine' => 442,
            'endLine' => 442,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a class / callback that should be used to delete users.
 *
 * @param  string  $class
 * @return void
 */',
        'startLine' => 442,
        'endLine' => 445,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'inertia' => 
      array (
        'name' => 'inertia',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Manage Jetstream\'s Inertia settings.
 *
 * @return \\Laravel\\Jetstream\\InertiaManager
 */',
        'startLine' => 452,
        'endLine' => 459,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'localizedMarkdownPath' => 
      array (
        'name' => 'localizedMarkdownPath',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 467,
            'endLine' => 467,
            'startColumn' => 50,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Find the path to a localized Markdown resource.
 *
 * @param  string  $name
 * @return string|null
 */',
        'startLine' => 467,
        'endLine' => 477,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
        'aliasName' => NULL,
      ),
      'ignoreRoutes' => 
      array (
        'name' => 'ignoreRoutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Configure Jetstream to not register its routes.
 *
 * @return static
 */',
        'startLine' => 484,
        'endLine' => 489,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Jetstream',
        'implementingClassName' => 'Laravel\\Jetstream\\Jetstream',
        'currentClassName' => 'Laravel\\Jetstream\\Jetstream',
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