<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/HasTeams.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Jetstream\HasTeams
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-aa1744414cbc7e5d7599cfa7bf365ff5420621a00e7bde38a74fe427f7addc81-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Jetstream\\HasTeams',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/HasTeams.php',
      ),
    ),
    'namespace' => 'Laravel\\Jetstream',
    'name' => 'Laravel\\Jetstream\\HasTeams',
    'shortName' => 'HasTeams',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 223,
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
    ),
    'immediateMethods' => 
    array (
      'isCurrentTeam' => 
      array (
        'name' => 'isCurrentTeam',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 16,
            'endLine' => 16,
            'startColumn' => 35,
            'endColumn' => 39,
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
 * Determine if the given team is the current team.
 *
 * @param  mixed  $team
 * @return bool
 */',
        'startLine' => 16,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'currentTeam' => 
      array (
        'name' => 'currentTeam',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current team of the user\'s context.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo
 */',
        'startLine' => 26,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'switchTeam' => 
      array (
        'name' => 'switchTeam',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 41,
            'endLine' => 41,
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
 * Switch the user\'s context to the given team.
 *
 * @param  mixed  $team
 * @return bool
 */',
        'startLine' => 41,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'allTeams' => 
      array (
        'name' => 'allTeams',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the teams the user owns or belongs to.
 *
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'ownedTeams' => 
      array (
        'name' => 'ownedTeams',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the teams the user owns.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\HasMany
 */',
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'teams' => 
      array (
        'name' => 'teams',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the teams the user belongs to.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany
 */',
        'startLine' => 81,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'personalTeam' => 
      array (
        'name' => 'personalTeam',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the user\'s "personal" team.
 *
 * @return \\App\\Models\\Team
 */',
        'startLine' => 94,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'ownsTeam' => 
      array (
        'name' => 'ownsTeam',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 30,
            'endColumn' => 34,
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
 * Determine if the user owns the given team.
 *
 * @param  mixed  $team
 * @return bool
 */',
        'startLine' => 105,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'belongsToTeam' => 
      array (
        'name' => 'belongsToTeam',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 120,
            'endLine' => 120,
            'startColumn' => 35,
            'endColumn' => 39,
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
 * Determine if the user belongs to the given team.
 *
 * @param  mixed  $team
 * @return bool
 */',
        'startLine' => 120,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'teamRole' => 
      array (
        'name' => 'teamRole',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 30,
            'endColumn' => 34,
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
 * Get the role that the user has on the team.
 *
 * @param  mixed  $team
 * @return \\Laravel\\Jetstream\\Role|null
 */',
        'startLine' => 137,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'hasTeamRole' => 
      array (
        'name' => 'hasTeamRole',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 33,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'role' => 
          array (
            'name' => 'role',
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
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 40,
            'endColumn' => 51,
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
 * Determine if the user has the given role on the given team.
 *
 * @param  mixed  $team
 * @param  string  $role
 * @return bool
 */',
        'startLine' => 163,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'teamPermissions' => 
      array (
        'name' => 'teamPermissions',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 180,
            'endLine' => 180,
            'startColumn' => 37,
            'endColumn' => 41,
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
 * Get the user\'s permissions for the given team.
 *
 * @param  mixed  $team
 * @return array
 */',
        'startLine' => 180,
        'endLine' => 191,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
        'aliasName' => NULL,
      ),
      'hasTeamPermission' => 
      array (
        'name' => 'hasTeamPermission',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 200,
            'endLine' => 200,
            'startColumn' => 39,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'permission' => 
          array (
            'name' => 'permission',
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
            'startLine' => 200,
            'endLine' => 200,
            'startColumn' => 46,
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
        'docComment' => '/**
 * Determine if the user has the given permission on the given team.
 *
 * @param  mixed  $team
 * @param  string  $permission
 * @return bool
 */',
        'startLine' => 200,
        'endLine' => 222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\HasTeams',
        'implementingClassName' => 'Laravel\\Jetstream\\HasTeams',
        'currentClassName' => 'Laravel\\Jetstream\\HasTeams',
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