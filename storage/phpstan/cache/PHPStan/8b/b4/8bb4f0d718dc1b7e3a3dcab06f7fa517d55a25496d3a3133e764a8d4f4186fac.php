<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Team.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Jetstream\Team
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-a6b799e21fe9521233ecd5449d6e68ec92925d45b5d0ebd7a950e34808ef66cc-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Jetstream\\Team',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Team.php',
      ),
    ),
    'namespace' => 'Laravel\\Jetstream',
    'name' => 'Laravel\\Jetstream\\Team',
    'shortName' => 'Team',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 122,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
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
    ),
    'immediateMethods' => 
    array (
      'owner' => 
      array (
        'name' => 'owner',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the owner of the team.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo
 */',
        'startLine' => 14,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'allUsers' => 
      array (
        'name' => 'allUsers',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the team\'s users including its owner.
 *
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'users' => 
      array (
        'name' => 'users',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the users that belong to the team.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany
 */',
        'startLine' => 34,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'hasUser' => 
      array (
        'name' => 'hasUser',
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 29,
            'endColumn' => 33,
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
 * Determine if the given user belongs to the team.
 *
 * @param  \\App\\Models\\User  $user
 * @return bool
 */',
        'startLine' => 48,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'hasUserWithEmail' => 
      array (
        'name' => 'hasUserWithEmail',
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
            'startLine' => 59,
            'endLine' => 59,
            'startColumn' => 38,
            'endColumn' => 50,
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
 * Determine if the given email address belongs to a user on the team.
 *
 * @param  string  $email
 * @return bool
 */',
        'startLine' => 59,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'userHasPermission' => 
      array (
        'name' => 'userHasPermission',
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
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'permission' => 
          array (
            'name' => 'permission',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 46,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given user has the given permission on the team.
 *
 * @param  \\App\\Models\\User  $user
 * @param  string  $permission
 * @return bool
 */',
        'startLine' => 73,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'teamInvitations' => 
      array (
        'name' => 'teamInvitations',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the pending user invitations for the team.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\HasMany
 */',
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'removeUser' => 
      array (
        'name' => 'removeUser',
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
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 32,
            'endColumn' => 36,
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
 * Remove the given user from the team.
 *
 * @param  \\App\\Models\\User  $user
 * @return void
 */',
        'startLine' => 94,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
        'aliasName' => NULL,
      ),
      'purge' => 
      array (
        'name' => 'purge',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Purge all of the team\'s resources.
 *
 * @return void
 */',
        'startLine' => 110,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Team',
        'implementingClassName' => 'Laravel\\Jetstream\\Team',
        'currentClassName' => 'Laravel\\Jetstream\\Team',
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