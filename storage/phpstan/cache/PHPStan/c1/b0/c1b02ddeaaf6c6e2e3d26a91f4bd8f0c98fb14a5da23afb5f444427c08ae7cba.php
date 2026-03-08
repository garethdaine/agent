<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Support/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-retry
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-03e08f2db7a9486e64b7887f9a1c50b544b68fe8794284259fa985a40534a5b8',
   'data' => 
  array (
    'name' => 'retry',
    'parameters' => 
    array (
      'times' => 
      array (
        'name' => 'times',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 310,
        'endLine' => 310,
        'startColumn' => 20,
        'endColumn' => 25,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'callback' => 
      array (
        'name' => 'callback',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'callable',
            'isIdentifier' => true,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 310,
        'endLine' => 310,
        'startColumn' => 28,
        'endColumn' => 45,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'sleepMilliseconds' => 
      array (
        'name' => 'sleepMilliseconds',
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 310,
            'endLine' => 310,
            'startTokenPos' => 1356,
            'startFilePos' => 7852,
            'endTokenPos' => 1356,
            'endFilePos' => 7852,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 310,
        'endLine' => 310,
        'startColumn' => 48,
        'endColumn' => 69,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'when' => 
      array (
        'name' => 'when',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 310,
            'endLine' => 310,
            'startTokenPos' => 1363,
            'startFilePos' => 7863,
            'endTokenPos' => 1363,
            'endFilePos' => 7866,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 310,
        'endLine' => 310,
        'startColumn' => 72,
        'endColumn' => 83,
        'parameterIndex' => 3,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => NULL,
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Retry an operation a given number of times.
 *
 * @template TValue
 *
 * @param  int|array<int, int>  $times
 * @param  callable(int): TValue  $callback
 * @param  int|\\Closure(int, \\Throwable): int  $sleepMilliseconds
 * @param  (callable(\\Throwable): bool)|null  $when
 * @return TValue
 *
 * @throws \\Throwable
 */',
    'startLine' => 310,
    'endLine' => 341,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => false,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'retry',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Support/helpers.php',
      ),
    ),
  ),
));