<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/OrgRitualRun.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\OrgRitualRun
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-f84cdf90b83a89be0f350158b20bf5a865adbdae951c84a8aa20b6997e2beca5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\OrgRitualRun',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/OrgRitualRun.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\OrgRitualRun',
    'shortName' => 'OrgRitualRun',
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
    'startLine' => 14,
    'endLine' => 106,
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
      'STATE_DRAFT' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_DRAFT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'draft\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 95,
            'startFilePos' => 627,
            'endTokenPos' => 95,
            'endFilePos' => 633,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'STATE_SCHEDULED' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_SCHEDULED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'scheduled\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 106,
            'startFilePos' => 672,
            'endTokenPos' => 106,
            'endFilePos' => 682,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'STATE_QUEUED' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_QUEUED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'queued\'',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 117,
            'startFilePos' => 718,
            'endTokenPos' => 117,
            'endFilePos' => 725,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'STATE_RUNNING' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_RUNNING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'running\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 128,
            'startFilePos' => 762,
            'endTokenPos' => 128,
            'endFilePos' => 770,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'STATE_WAITING_APPROVAL' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_WAITING_APPROVAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'waiting_approval\'',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 139,
            'startFilePos' => 816,
            'endTokenPos' => 139,
            'endFilePos' => 833,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 61,
      ),
      'STATE_REVIEWING' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_REVIEWING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'reviewing\'',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 150,
            'startFilePos' => 872,
            'endTokenPos' => 150,
            'endFilePos' => 882,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'STATE_SUCCEEDED' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_SUCCEEDED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'succeeded\'',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 161,
            'startFilePos' => 921,
            'endTokenPos' => 161,
            'endFilePos' => 931,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'STATE_FAILED' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_FAILED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'failed\'',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 172,
            'startFilePos' => 967,
            'endTokenPos' => 172,
            'endFilePos' => 974,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'STATE_CANCELLED' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'cancelled\'',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 183,
            'startFilePos' => 1013,
            'endTokenPos' => 183,
            'endFilePos' => 1023,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'STATE_PARTIAL' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'STATE_PARTIAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'partial\'',
          'attributes' => 
          array (
            'startLine' => 47,
            'endLine' => 47,
            'startTokenPos' => 194,
            'startFilePos' => 1060,
            'endTokenPos' => 194,
            'endFilePos' => 1068,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'ACTIVE_STATES' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'ACTIVE_STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::STATE_QUEUED, self::STATE_RUNNING, self::STATE_WAITING_APPROVAL, self::STATE_REVIEWING]',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 54,
            'startTokenPos' => 205,
            'startFilePos' => 1105,
            'endTokenPos' => 227,
            'endFilePos' => 1237,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'TERMINAL_STATES' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'TERMINAL_STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::STATE_SUCCEEDED, self::STATE_FAILED, self::STATE_CANCELLED, self::STATE_PARTIAL]',
          'attributes' => 
          array (
            'startLine' => 56,
            'endLine' => 61,
            'startTokenPos' => 238,
            'startFilePos' => 1276,
            'endTokenPos' => 260,
            'endFilePos' => 1401,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'ritual_template_id\', \'user_id\', \'state\', \'delegation_graph_id\', \'phase_outputs\', \'started_at\', \'completed_at\', \'correlation_id\']',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 27,
            'startTokenPos' => 58,
            'startFilePos' => 392,
            'endTokenPos' => 84,
            'endFilePos' => 592,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 27,
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
        'startLine' => 63,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'template' => 
      array (
        'name' => 'template',
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
        'startLine' => 72,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'user' => 
      array (
        'name' => 'user',
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
        'startLine' => 77,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'delegationGraph' => 
      array (
        'name' => 'delegationGraph',
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
        'startLine' => 82,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'scopeActive' => 
      array (
        'name' => 'scopeActive',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 33,
            'endColumn' => 46,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 87,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'scopeTerminal' => 
      array (
        'name' => 'scopeTerminal',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 35,
            'endColumn' => 48,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 92,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'scopeForUser' => 
      array (
        'name' => 'scopeForUser',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
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
            'startColumn' => 34,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'userId' => 
          array (
            'name' => 'userId',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
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
            'startColumn' => 50,
            'endColumn' => 67,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 97,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
        'aliasName' => NULL,
      ),
      'scopeForTemplate' => 
      array (
        'name' => 'scopeForTemplate',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'templateId' => 
          array (
            'name' => 'templateId',
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
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 54,
            'endColumn' => 71,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 102,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\OrgRitualRun',
        'implementingClassName' => 'App\\Models\\OrgRitualRun',
        'currentClassName' => 'App\\Models\\OrgRitualRun',
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