<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Models/DelegationVerificationResult.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\DelegationVerificationResult
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-f4404025879faddd46fa90de0b96610315c8645abb953ddb5166586f29342e17',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\DelegationVerificationResult',
        'filename' => '/Users/garethdaine/Code/agent/app/Models/DelegationVerificationResult.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\DelegationVerificationResult',
    'shortName' => 'DelegationVerificationResult',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Represents the result of a verification step for a delegation task.
 *
 * @property int $id
 * @property int $delegation_task_id
 * @property int|null $delegation_attempt_id
 * @property string $step_type
 * @property int $step_order
 * @property string $verdict
 * @property array|null $evidence_json
 * @property \\Carbon\\Carbon|null $started_at
 * @property \\Carbon\\Carbon|null $finished_at
 * @property \\Carbon\\Carbon|null $expires_at
 * @property \\Carbon\\Carbon $created_at
 * @property \\Carbon\\Carbon $updated_at
 *
 * @mixin Builder
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 165,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
    ),
    'immediateConstants' => 
    array (
      'STEP_TYPE_AUTOMATED_CHECK' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'STEP_TYPE_AUTOMATED_CHECK',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'automated_check\'',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 56,
            'startFilePos' => 948,
            'endTokenPos' => 56,
            'endFilePos' => 964,
          ),
        ),
        'docComment' => '/**
 * Step types for verification.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 70,
      ),
      'STEP_TYPE_AI_CRITIC' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'STEP_TYPE_AI_CRITIC',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'ai_critic\'',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 69,
            'startFilePos' => 1014,
            'endTokenPos' => 69,
            'endFilePos' => 1024,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 58,
      ),
      'STEP_TYPE_HUMAN_APPROVAL' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'STEP_TYPE_HUMAN_APPROVAL',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'human_approval\'',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 82,
            'startFilePos' => 1079,
            'endTokenPos' => 82,
            'endFilePos' => 1094,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 68,
      ),
      'VERDICT_PASSED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'VERDICT_PASSED',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'passed\'',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 44,
            'startTokenPos' => 97,
            'startFilePos' => 1197,
            'endTokenPos' => 97,
            'endFilePos' => 1204,
          ),
        ),
        'docComment' => '/**
 * Verdicts for verification results.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'VERDICT_FAILED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'VERDICT_FAILED',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'failed\'',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 110,
            'startFilePos' => 1249,
            'endTokenPos' => 110,
            'endFilePos' => 1256,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'VERDICT_SKIPPED' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'VERDICT_SKIPPED',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'skipped\'',
          'attributes' => 
          array (
            'startLine' => 48,
            'endLine' => 48,
            'startTokenPos' => 123,
            'startFilePos' => 1302,
            'endTokenPos' => 123,
            'endFilePos' => 1310,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'VERDICT_PENDING' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'VERDICT_PENDING',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '\'pending\'',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 136,
            'startFilePos' => 1356,
            'endTokenPos' => 136,
            'endFilePos' => 1364,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'STEP_TYPES' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'STEP_TYPES',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '[self::STEP_TYPE_AUTOMATED_CHECK, self::STEP_TYPE_AI_CRITIC, self::STEP_TYPE_HUMAN_APPROVAL]',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 59,
            'startTokenPos' => 151,
            'startFilePos' => 1452,
            'endTokenPos' => 168,
            'endFilePos' => 1574,
          ),
        ),
        'docComment' => '/**
 * All possible step types.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'VERDICTS' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'VERDICTS',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '[self::VERDICT_PASSED, self::VERDICT_FAILED, self::VERDICT_SKIPPED, self::VERDICT_PENDING]',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 69,
            'startTokenPos' => 183,
            'startFilePos' => 1658,
            'endTokenPos' => 205,
            'endFilePos' => 1786,
          ),
        ),
        'docComment' => '/**
 * All possible verdicts.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'TERMINAL_VERDICTS' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'TERMINAL_VERDICTS',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '[self::VERDICT_PASSED, self::VERDICT_FAILED, self::VERDICT_SKIPPED]',
          'attributes' => 
          array (
            'startLine' => 74,
            'endLine' => 78,
            'startTokenPos' => 220,
            'startFilePos' => 1895,
            'endTokenPos' => 237,
            'endFilePos' => 1992,
          ),
        ),
        'docComment' => '/**
 * Terminal verdicts (no longer pending).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 74,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'guarded' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'guarded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 80,
            'endLine' => 80,
            'startTokenPos' => 246,
            'startFilePos' => 2021,
            'endTokenPos' => 247,
            'endFilePos' => 2022,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 80,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'casts' => 
      array (
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'name' => 'casts',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'evidence_json\' => \'array\', \'step_order\' => \'integer\', \'started_at\' => \'datetime\', \'finished_at\' => \'datetime\', \'expires_at\' => \'datetime\']',
          'attributes' => 
          array (
            'startLine' => 85,
            'endLine' => 91,
            'startTokenPos' => 258,
            'startFilePos' => 2099,
            'endTokenPos' => 295,
            'endFilePos' => 2285,
          ),
        ),
        'docComment' => '/**
 * @var array<string, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 91,
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
      'task' => 
      array (
        'name' => 'task',
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
        'docComment' => '/**
 * Get the task this verification result belongs to.
 */',
        'startLine' => 96,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
        'aliasName' => NULL,
      ),
      'attempt' => 
      array (
        'name' => 'attempt',
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
        'docComment' => '/**
 * Get the attempt this verification result belongs to.
 */',
        'startLine' => 104,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
        'aliasName' => NULL,
      ),
      'isPassed' => 
      array (
        'name' => 'isPassed',
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
        'docComment' => '/**
 * Check if the verdict is passed.
 */',
        'startLine' => 112,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
        'aliasName' => NULL,
      ),
      'isFailed' => 
      array (
        'name' => 'isFailed',
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
        'docComment' => '/**
 * Check if the verdict is failed.
 */',
        'startLine' => 120,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
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
        'docComment' => '/**
 * Check if the verdict is pending.
 */',
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
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
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
        'docComment' => '/**
 * Check if the verdict is terminal (no longer pending).
 */',
        'startLine' => 136,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
        'aliasName' => NULL,
      ),
      'isExpired' => 
      array (
        'name' => 'isExpired',
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
        'docComment' => '/**
 * Check if this result has expired (for human approval steps).
 */',
        'startLine' => 144,
        'endLine' => 147,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
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
            'startLine' => 152,
            'endLine' => 152,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Scope to filter by pending verdicts.
 */',
        'startLine' => 152,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
        'aliasName' => NULL,
      ),
      'scopeExpired' => 
      array (
        'name' => 'scopeExpired',
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
            'startLine' => 160,
            'endLine' => 160,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Scope to filter by expired human approval results.
 */',
        'startLine' => 160,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DelegationVerificationResult',
        'implementingClassName' => 'App\\Models\\DelegationVerificationResult',
        'currentClassName' => 'App\\Models\\DelegationVerificationResult',
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