<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/NlSchedule/NextRunsCalculator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\NlSchedule\NextRunsCalculator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-994e79f09a6d7fd3a6aead2698baddf726abbe66276aba67c9c1f7826a0c00c9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/NlSchedule/NextRunsCalculator.php',
      ),
    ),
    'namespace' => 'App\\Support\\NlSchedule',
    'name' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
    'shortName' => 'NextRunsCalculator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Calculates the next N scheduled runs for a cron expression,
 * optionally filtering by active hours configuration.
 *
 * Returns array of {local, utc} timestamp pairs representing the
 * true dispatch times (i.e., times when jobs will actually execute).
 *
 * Known limitations:
 * - Maximum 1000 cron iterations to prevent infinite loops
 * - Active hours overnight windows not supported
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 105,
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
      'MAX_ITERATIONS' => 
      array (
        'declaringClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'implementingClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'name' => 'MAX_ITERATIONS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1000',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 47,
            'startFilePos' => 768,
            'endTokenPos' => 47,
            'endFilePos' => 771,
          ),
        ),
        'docComment' => '/**
 * Maximum cron iterations to prevent infinite loops.
 * E.g., if active hours config has no valid days, we stop after this many attempts.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
    ),
    'immediateProperties' => 
    array (
      'evaluator' => 
      array (
        'declaringClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'implementingClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'name' => 'evaluator',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\NlSchedule\\ActiveHoursEvaluator',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 9,
        'endColumn' => 56,
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
          'evaluator' => 
          array (
            'name' => 'evaluator',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\NlSchedule\\ActiveHoursEvaluator',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 9,
            'endColumn' => 56,
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
        'startLine' => 29,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\NlSchedule',
        'declaringClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'implementingClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'currentClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'aliasName' => NULL,
      ),
      'calculate' => 
      array (
        'name' => 'calculate',
        'parameters' => 
        array (
          'cronExpression' => 
          array (
            'name' => 'cronExpression',
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 9,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'timezone' => 
          array (
            'name' => 'timezone',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'activeHours' => 
          array (
            'name' => 'activeHours',
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
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
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
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'from' => 
          array (
            'name' => 'from',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 48,
                'endLine' => 48,
                'startTokenPos' => 103,
                'startFilePos' => 1583,
                'endTokenPos' => 103,
                'endFilePos' => 1586,
              ),
            ),
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
                      'name' => 'Carbon\\CarbonImmutable',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'count' => 
          array (
            'name' => 'count',
            'default' => 
            array (
              'code' => '5',
              'attributes' => 
              array (
                'startLine' => 49,
                'endLine' => 49,
                'startTokenPos' => 112,
                'startFilePos' => 1610,
                'endTokenPos' => 112,
                'endFilePos' => 1610,
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
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 9,
            'endColumn' => 22,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
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
        'docComment' => '/**
 * Calculate the next N runs for a cron expression, filtered by active hours.
 *
 * @param string $cronExpression 5-part cron expression
 * @param string $timezone IANA timezone for the schedule
 * @param array|null $activeHours Active hours configuration (null = no filtering)
 * @param CarbonImmutable|null $from Starting point for calculation (defaults to now)
 * @param int $count Number of runs to return (default 5)
 * @return array<int, array{local: string, utc: string}> Array of timestamp pairs
 */',
        'startLine' => 44,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\NlSchedule',
        'declaringClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'implementingClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
        'currentClassName' => 'App\\Support\\NlSchedule\\NextRunsCalculator',
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