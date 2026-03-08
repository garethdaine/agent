<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Contracts/Process/InvokedProcess.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Contracts\Process\InvokedProcess
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-1c9a23da868837bc48bbd00e38e79ce9e222b844a5c55c70c5d3aa1386e627aa-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Contracts/Process/InvokedProcess.php',
      ),
    ),
    'namespace' => 'Illuminate\\Contracts\\Process',
    'name' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
    'shortName' => 'InvokedProcess',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 72,
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
      'id' => 
      array (
        'name' => 'id',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the process ID if the process is still running.
 *
 * @return int|null
 */',
        'startLine' => 12,
        'endLine' => 12,
        'startColumn' => 5,
        'endColumn' => 25,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'signal' => 
      array (
        'name' => 'signal',
        'parameters' => 
        array (
          'signal' => 
          array (
            'name' => 'signal',
            'default' => NULL,
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 28,
            'endColumn' => 38,
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
 * Send a signal to the process.
 *
 * @param  int  $signal
 * @return $this
 */',
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 40,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'running' => 
      array (
        'name' => 'running',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the process is still running.
 *
 * @return bool
 */',
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 30,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'output' => 
      array (
        'name' => 'output',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the standard output for the process.
 *
 * @return string
 */',
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 29,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'errorOutput' => 
      array (
        'name' => 'errorOutput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the error output for the process.
 *
 * @return string
 */',
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'latestOutput' => 
      array (
        'name' => 'latestOutput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the latest standard output for the process.
 *
 * @return string
 */',
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 35,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'latestErrorOutput' => 
      array (
        'name' => 'latestErrorOutput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the latest error output for the process.
 *
 * @return string
 */',
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 40,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'wait' => 
      array (
        'name' => 'wait',
        'parameters' => 
        array (
          'output' => 
          array (
            'name' => 'output',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 63,
                'endLine' => 63,
                'startTokenPos' => 108,
                'startFilePos' => 1204,
                'endTokenPos' => 108,
                'endFilePos' => 1207,
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
                      'name' => 'callable',
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
            'startLine' => 63,
            'endLine' => 63,
            'startColumn' => 26,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Wait for the process to finish.
 *
 * @param  callable|null  $output
 * @return \\Illuminate\\Process\\ProcessResult
 */',
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'aliasName' => NULL,
      ),
      'waitUntil' => 
      array (
        'name' => 'waitUntil',
        'parameters' => 
        array (
          'output' => 
          array (
            'name' => 'output',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 71,
                'endLine' => 71,
                'startTokenPos' => 127,
                'startFilePos' => 1423,
                'endTokenPos' => 127,
                'endFilePos' => 1426,
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
                      'name' => 'callable',
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
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 31,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Wait until the given callback returns true.
 *
 * @param  callable|null  $output
 * @return \\Illuminate\\Process\\ProcessResult
 */',
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Contracts\\Process',
        'declaringClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'implementingClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
        'currentClassName' => 'Illuminate\\Contracts\\Process\\InvokedProcess',
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