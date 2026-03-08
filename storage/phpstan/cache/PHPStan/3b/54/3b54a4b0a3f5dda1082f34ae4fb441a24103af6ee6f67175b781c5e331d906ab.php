<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/ChatAction.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\ChatAction
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-6a258caa91ae75d50b0a938a845770b07ff48a9101c279a9be50202dff36d0d4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\ChatAction',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/ChatAction.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\ChatAction',
    'shortName' => 'ChatAction',
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
    'endLine' => 141,
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
      'STATUS_PENDING' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'STATUS_PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'pending\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 86,
            'startFilePos' => 520,
            'endTokenPos' => 86,
            'endFilePos' => 528,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'STATUS_EXECUTING' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'STATUS_EXECUTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'executing\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 97,
            'startFilePos' => 568,
            'endTokenPos' => 97,
            'endFilePos' => 578,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'STATUS_COMPLETED' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'STATUS_COMPLETED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'completed\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 108,
            'startFilePos' => 618,
            'endTokenPos' => 108,
            'endFilePos' => 628,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'STATUS_FAILED' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'STATUS_FAILED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'failed\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 119,
            'startFilePos' => 665,
            'endTokenPos' => 119,
            'endFilePos' => 672,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'STATUS_CANCELLED' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'STATUS_CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'cancelled\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 130,
            'startFilePos' => 712,
            'endTokenPos' => 130,
            'endFilePos' => 722,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'STATUS_TIMEOUT' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'STATUS_TIMEOUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'timeout\'',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 141,
            'startFilePos' => 760,
            'endTokenPos' => 141,
            'endFilePos' => 768,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'TERMINAL_STATUSES' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'TERMINAL_STATUSES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'completed\', \'failed\', \'cancelled\', \'timeout\']',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 152,
            'startFilePos' => 809,
            'endTokenPos' => 163,
            'endFilePos' => 855,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 85,
      ),
      'PENDING' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::STATUS_PENDING',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 176,
            'startFilePos' => 946,
            'endTokenPos' => 178,
            'endFilePos' => 965,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'EXECUTING' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'EXECUTING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::STATUS_EXECUTING',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 189,
            'startFilePos' => 998,
            'endTokenPos' => 191,
            'endFilePos' => 1019,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'COMPLETED' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'COMPLETED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::STATUS_COMPLETED',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 202,
            'startFilePos' => 1052,
            'endTokenPos' => 204,
            'endFilePos' => 1073,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'FAILED' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'FAILED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::STATUS_FAILED',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 215,
            'startFilePos' => 1103,
            'endTokenPos' => 217,
            'endFilePos' => 1121,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 46,
      ),
      'CANCELLED' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::STATUS_CANCELLED',
          'attributes' => 
          array (
            'startLine' => 47,
            'endLine' => 47,
            'startTokenPos' => 228,
            'startFilePos' => 1154,
            'endTokenPos' => 230,
            'endFilePos' => 1175,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'TIMEOUT' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'TIMEOUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::STATUS_TIMEOUT',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 241,
            'startFilePos' => 1206,
            'endTokenPos' => 243,
            'endFilePos' => 1225,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'ACTION_JOBS_CREATE' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_JOBS_CREATE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'jobs.create\'',
          'attributes' => 
          array (
            'startLine' => 51,
            'endLine' => 51,
            'startTokenPos' => 254,
            'startFilePos' => 1267,
            'endTokenPos' => 254,
            'endFilePos' => 1279,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'ACTION_JOBS_UPDATE' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_JOBS_UPDATE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'jobs.update\'',
          'attributes' => 
          array (
            'startLine' => 53,
            'endLine' => 53,
            'startTokenPos' => 265,
            'startFilePos' => 1321,
            'endTokenPos' => 265,
            'endFilePos' => 1333,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'ACTION_JOBS_DELETE' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_JOBS_DELETE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'jobs.delete\'',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 276,
            'startFilePos' => 1375,
            'endTokenPos' => 276,
            'endFilePos' => 1387,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'ACTION_JOBS_LIST' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_JOBS_LIST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'jobs.list\'',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 287,
            'startFilePos' => 1427,
            'endTokenPos' => 287,
            'endFilePos' => 1437,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'ACTION_RUNS_LIST_ACTIVE' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_RUNS_LIST_ACTIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'runs.list_active\'',
          'attributes' => 
          array (
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 298,
            'startFilePos' => 1484,
            'endTokenPos' => 298,
            'endFilePos' => 1501,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 62,
      ),
      'ACTION_RUNS_STOP' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_RUNS_STOP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'runs.stop\'',
          'attributes' => 
          array (
            'startLine' => 61,
            'endLine' => 61,
            'startTokenPos' => 309,
            'startFilePos' => 1541,
            'endTokenPos' => 309,
            'endFilePos' => 1551,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
      'ACTION_RUNS_RETRY' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_RUNS_RETRY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'runs.retry\'',
          'attributes' => 
          array (
            'startLine' => 63,
            'endLine' => 63,
            'startTokenPos' => 320,
            'startFilePos' => 1592,
            'endTokenPos' => 320,
            'endFilePos' => 1603,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'ACTION_RUNS_RUN_NOW' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_RUNS_RUN_NOW',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'runs.run_now\'',
          'attributes' => 
          array (
            'startLine' => 65,
            'endLine' => 65,
            'startTokenPos' => 331,
            'startFilePos' => 1646,
            'endTokenPos' => 331,
            'endFilePos' => 1659,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 65,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 54,
      ),
      'ACTION_RUNS_STEER' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'ACTION_RUNS_STEER',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'runs.steer\'',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 67,
            'startTokenPos' => 342,
            'startFilePos' => 1700,
            'endTokenPos' => 342,
            'endFilePos' => 1711,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'DESTRUCTIVE_ACTIONS' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'DESTRUCTIVE_ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'runs.stop\', \'jobs.update\', \'jobs.delete\']',
          'attributes' => 
          array (
            'startLine' => 69,
            'endLine' => 69,
            'startTokenPos' => 353,
            'startFilePos' => 1754,
            'endTokenPos' => 361,
            'endFilePos' => 1796,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 83,
      ),
    ),
    'immediateProperties' => 
    array (
      'timestamps' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'timestamps',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 65,
            'startFilePos' => 448,
            'endTokenPos' => 65,
            'endFilePos' => 452,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'guarded' => 
      array (
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'name' => 'guarded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 74,
            'startFilePos' => 481,
            'endTokenPos' => 75,
            'endFilePos' => 482,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 28,
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
        'startLine' => 71,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'message' => 
      array (
        'name' => 'message',
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
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'pendingConfirmation' => 
      array (
        'name' => 'pendingConfirmation',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasOne',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 88,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'scopePending' => 
      array (
        'name' => 'scopePending',
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
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 34,
            'endColumn' => 47,
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
        'startLine' => 93,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'scopeExecuting' => 
      array (
        'name' => 'scopeExecuting',
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
            'startLine' => 98,
            'endLine' => 98,
            'startColumn' => 36,
            'endColumn' => 49,
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
        'startLine' => 98,
        'endLine' => 101,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
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
            'startLine' => 103,
            'endLine' => 103,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 103,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'isPending' => 
      array (
        'name' => 'isPending',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 108,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'isExecuting' => 
      array (
        'name' => 'isExecuting',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 113,
        'endLine' => 116,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'isTerminal' => 
      array (
        'name' => 'isTerminal',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 118,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'isDestructive' => 
      array (
        'name' => 'isDestructive',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 123,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'requiresConfirmation' => 
      array (
        'name' => 'requiresConfirmation',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 128,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
        'aliasName' => NULL,
      ),
      'booted' => 
      array (
        'name' => 'booted',
        'parameters' => 
        array (
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
        'startLine' => 133,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ChatAction',
        'implementingClassName' => 'App\\Models\\ChatAction',
        'currentClassName' => 'App\\Models\\ChatAction',
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